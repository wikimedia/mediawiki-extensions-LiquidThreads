<?php
/**
* @file
* @ingroup LiquidThreads
* @author David McCabe <davemccabe@gmail.com>
* @licence GPL2
*/

if ( !defined( 'MEDIAWIKI' ) ) {
	echo( "This file is an extension to the MediaWiki software and cannot be used standalone.\n" );
	die( - 1 );
}

class LqtView {
	public $article;
	public $output;
	public $user;
	public $title;
	public $request;

	protected $headerLevel = 2; /* h1, h2, h3, etc. */
	protected $lastUnindentedSuperthread;

	public $threadNestingLevel = 0;

	protected $sort_order = LQT_NEWEST_CHANGES;

	static $stylesAndScriptsDone = false;

	function __construct( &$output, &$article, &$title, &$user, &$request ) {
		$this->article = $article;
		$this->output = $output;
		$this->user = $user;
		$this->title = $title;
		$this->request = $request;
		$this->user_colors = array();
		$this->user_color_index = 1;
	}

	static function getView() {
		global $wgOut, $wgArticle, $wgTitle, $wgUser, $wgRequest;
		return new LqtView( $wgOut, $wgArticle, $wgTitle, $wgUser, $wgRequest );
	}

	function setHeaderLevel( $int ) {
		$this->headerLevel = $int;
	}

	/*************************
	 * (1) linking to liquidthreads pages and
	 * (2) figuring out what page you're on and what you need to do.
	*************************/

	function methodAppliesToThread( $method, $thread ) {
		return $this->request->getVal( 'lqt_method' ) == $method &&
			$this->request->getVal( 'lqt_operand' ) == $thread->id();
	}
	function methodApplies( $method ) {
		return $this->request->getVal( 'lqt_method' ) == $method;
	}

	static function permalinkUrl( $thread, $method = null, $operand = null,
									$uquery = array(), $relative = true ) {
		list ( $title, $query ) = self::permalinkData( $thread, $method, $operand );

		$query = array_merge( $query, $uquery );

		$queryString = wfArrayToCGI( $query );

		if( $relative ) {
			return $title->getLocalUrl( $queryString );
		} else {
			return $title->getFullUrl( $queryString );
		}
	}

	/** Gets an array of (title, query-parameters) for a permalink **/
	static function permalinkData( $thread, $method = null, $operand = null ) {
		$query = array();

		if ( $method ) {
			$query['lqt_method'] = $method;
		}
		if ( $operand ) {
			$query['lqt_operand'] = $operand;
		}

		if ( ! $thread ) {
			throw new MWException( "Empty thread passed to ".__METHOD__ );
		}

		return array( $thread->root()->getTitle(), $query );
	}

	/* This is used for action=history so that the history tab works, which is
	   why we break the lqt_method paradigm. */
	static function permalinkUrlWithQuery( $thread, $query, $relative = true ) {
		if ( !is_array( $query ) ) {
			$query = wfCGIToArray( $query );
		}

		return self::permalinkUrl( $thread, null, null, $query, $relative );
	}

	static function permalink( $thread, $text = null, $method = null, $operand = null,
					$sk = null, $attribs = array(), $uquery = array() ) {
		if ( is_null( $sk ) ) {
			global $wgUser;
			$sk = $wgUser->getSkin();
		}

		list( $title, $query ) = self::permalinkData( $thread, $method, $operand );

		$query = array_merge( $query, $uquery );

		return $sk->link( $title, $text, $attribs, $query );
	}

	static function linkInContextData( $thread, $contextType = 'page' ) {
		$query = array();

		if ( ! $thread ) {
			throw new MWException( "Null thread passed to linkInContextData" );
		}

		if ( $contextType == 'page' ) {
			$title = clone $thread->getTitle();

			$dbr = wfGetDB( DB_SLAVE );
			$offset = $thread->topmostThread()->sortkey();
			$offset = wfTimestamp( TS_UNIX, $offset ) + 1;
			$offset = $dbr->timestamp( $offset );
			$query['offset'] = $offset;
		} else {
			$title = clone $thread->title();
		}

		$query['lqt_mustshow'] = $thread->id();

		$title->setFragment( '#' . $thread->getAnchorName() );

		return array( $title, $query );
	}

	static function linkInContext( $thread, $contextType = 'page', $text = null ) {
		list( $title, $query ) = self::linkInContextData( $thread, $contextType );

		if ( is_null( $text ) ) {
			$text = Threads::stripHTML( $thread->formattedSubject() );
		}

		global $wgUser;
		$sk = $wgUser->getSkin();

		return $sk->link( $title, $text, array(), $query );
	}

	static function linkInContextURL( $thread, $contextType = 'page' ) {
		list( $title, $query ) = self::linkInContextData( $thread, $contextType );

		return $title->getLocalURL( $query );
	}

	static function linkInContextFullURL( $thread, $contextType = 'page' ) {
		list( $title, $query ) = self::linkInContextData( $thread, $contextType );

		return $title->getFullURL( $query );
	}

	static function diffQuery( $thread, $revision ) {
		$changed_thread = $revision->getChangeObject();
		$curr_rev_id = $changed_thread->rootRevision();
		$curr_rev = Revision::newFromId( $curr_rev_id );
		$prev_rev = $curr_rev->getPrevious();
		$oldid = $prev_rev ? $prev_rev->getId() : "";

		$query = array(
			'lqt_method' => 'diff',
			'diff' => $curr_rev_id,
			'oldid' => $oldid
		);

		return $query;
	}

	static function diffPermalinkURL( $thread, $revision ) {
		$query = self::diffQuery( $thread, $revision );
		return self::permalinkUrl( $thread, null, null, $query, false );
	}

	static function diffPermalink( $thread, $text, $revision ) {
		$query = self::diffQuery( $thread, $revision );
		return self::permalink( $thread, $text, null, null, null, array(), $query );
	}

	static function talkpageLink( $title, $text = null , $method = null, $operand = null,
					$includeFragment = true, $attribs = array(),
					$options = array(), $perpetuateOffset = true )
	{
		list( $title, $query ) = self::talkpageLinkData(
			$title, $method, $operand,
			$includeFragment,
			$perpetuateOffset
		);

		global $wgUser;
		$sk = $wgUser->getSkin();

		return $sk->link( $title, $text, $attribs, $query, $options );
	}

	static function talkpageLinkData( $title, $method = null, $operand = null,
		$includeFragment = true, $perpetuateOffset = true )
	{
		global $wgRequest;
		$query = array();

		if ( $method ) {
			$query['lqt_method'] = $method;
		}

		if ( $operand ) {
			$query['lqt_operand'] = $operand->id();
		}

		$oldid = $wgRequest->getVal( 'oldid', null );

		if ( $oldid !== null ) {
			// this is an immensely ugly hack to make editing old revisions work.
			$query['oldid'] = $oldid;
		}

		$request = $perpetuateOffset;
		if ( $request === true ) {
			global $wgRequest;
			$request = $wgRequest;
		}

		if ( $perpetuateOffset ) {
			$offset = $request->getVal( 'offset' );

			if ( $offset ) {
				$query['offset'] = $offset;
			}
		}

		// Add fragment if appropriate.
		if ( $operand && $includeFragment ) {
			$title->mFragment = $operand->getAnchorName();
		}

		return array( $title, $query );
	}

	/**
	 * If you want $perpetuateOffset to perpetuate from a specific request,
	 * pass that instead of true
	 */
	static function talkpageUrl( $title, $method = null, $operand = null,
		$includeFragment = true, $perpetuateOffset = true )
	{
		list( $title, $query ) =
			self::talkpageLinkData( $title, $method, $operand, $includeFragment,
						$perpetuateOffset );

		return $title->getLinkUrl( $query );
	}


	/**
	 * Return a URL for the current page, including Title and query vars,
	 * with the given replacements made.
	 * @param $repls array( 'name'=>new_value, ... )
	 */
	function queryReplaceLink( $repls ) {
		$query = $this->getReplacedQuery( $repls );

		return $this->title->getLocalURL( wfArrayToCGI( $vs ) );
	}

	function getReplacedQuery( $replacements ) {
		$values = $this->request->getValues();

		foreach ( $replacements as $k => $v ) {
			$values[$k] = $v;
		}

		return $values;
	}

	/*************************************************************
	 * Editing methods (here be dragons)						  *
	 * Forget dragons: This section distorts the rest of the code *
	 * like a star bending spacetime around itself.				  *
	 *************************************************************/

	/**
	 * Return an HTML form element whose value is gotten from the request.
	 * TODO: figure out a clean way to expand this to other forms.
	 */
	function perpetuate( $name, $as = 'hidden' ) {
		$value = $this->request->getVal( $name, '' );
		if ( $as == 'hidden' ) {
			return Html::hidden( $name, $value );
		}
	}

	function showReplyProtectedNotice( $thread ) {
		$log_url = SpecialPage::getTitleFor( 'Log' )->getLocalURL(
			"type=protect&user=&page={$thread->title()->getPrefixedURL()}" );
		$this->output->addHTML( '<p>' . wfMsg( 'lqt_protectedfromreply',
			'<a href="' . $log_url . '">' . wfMsg( 'lqt_protectedfromreply_link' ) . '</a>' ) );
	}

	function doInlineEditForm() {
		$method = $this->request->getVal( 'lqt_method' );
		$operand = $this->request->getVal( 'lqt_operand' );

		$thread = Threads::withId( intval( $operand ) );
		
		// Yuck.
		global $wgOut, $wgRequest, $wgTitle;
		$oldOut = $wgOut;
		$oldRequest = $wgRequest;
		$oldTitle = $wgTitle;
		$wgOut = $this->output;
		$wgRequest = $this->request;
		$wgTitle = $this->title;

		$hookResult = wfRunHooks( 'LiquidThreadsDoInlineEditForm',
					array(
						$thread,
						$this->request,
						&$this->output
					) );

		if ( !$hookResult ) {
			// Handled by a hook.
		} elseif ( $method == 'reply' ) {
			$this->showReplyForm( $thread );
		} elseif ( $method == 'talkpage_new_thread' ) {
			$this->showNewThreadForm( $this->article );
		} elseif ( $method == 'edit' ) {
			$this->showPostEditingForm( $thread );
		} else {
			throw new MWException( "Invalid thread method $method" );
		}
		
		$wgOut = $oldOut;
		$wgRequest = $oldRequest;
		$wgTitle = $oldTitle;

		$this->output->setArticleBodyOnly( true );
	}
	
	static function getInlineEditForm( $talkpage, $method, $operand ) {
		$output = new OutputPage;
		$request = new FauxRequest( array() );
		$title = null;
		
		if ( $talkpage ) {
			$title = $talkpage->getTitle();
		} elseif ( $operand ) {
			$thread = Threads::withId( $operand );
			if ( $thread ) {
				$talkpage = $thread->article();
				$title = $talkpage->getTitle();
			}
		}

		$output->setTitle( $title );
		$request->setVal( 'lqt_method', $method );
		$request->setVal( 'lqt_operand', $operand );
		
		global $wgUser;
		$view = new LqtView( $output, $talkpage, $title, $wgUser, $request );
		
		$view->doInlineEditForm();
		
		return $output->getHTML();
	}

	function showNewThreadForm( $talkpage ) {
		$submitted_nonce = $this->request->getVal( 'lqt_nonce' );
		$nonce_key = wfMemcKey( 'lqt-nonce', $submitted_nonce, $this->user->getName() );
		if ( ! $this->handleNonce( $submitted_nonce, $nonce_key ) ) return;

		if ( Thread::canUserPost( $this->user, $this->article ) !== true ) {
			$this->output->addWikiMsg( 'lqt-protected-newthread' );
			return;
		}
		$subject = $this->request->getVal( 'lqt_subject_field', false );

		$t = null;

		$subjectOk = Thread::validateSubject( $subject, $t,
					null, $this->article );
		if ( ! $subjectOk ) {
			try {
				$t = $this->newThreadTitle( $subject );
			} catch ( MWException $excep ) {
				$t = $this->scratchTitle();
			}
		}

		$article = new Article( $t );

		LqtHooks::$editTalkpage = $talkpage;
		LqtHooks::$editArticle = $article;
		LqtHooks::$editThread = null;
		LqtHooks::$editType = 'new';
		LqtHooks::$editAppliesTo = null;

		$e = new EditPage( $article );
		wfRunHooks( 'LiquidThreadsShowNewThreadForm', array( &$e, $talkpage ) );

		global $wgRequest;
		// Quietly force a preview if no subject has been specified.
		if ( !$subjectOk ) {
			// Dirty hack to prevent saving from going ahead
			$wgRequest->setVal( 'wpPreview', true );

			if ( $this->request->wasPosted() ) {
				if ( !$subject ) {
					$msg = 'lqt_empty_subject';
				} else {
					$msg = 'lqt_invalid_subject';
				}

				$e->editFormPageTop .=
					Xml::tags( 'div', array( 'class' => 'error' ),
						wfMsgExt( $msg, 'parse' ) );
			}
		}

		$e->suppressIntro = true;
		$e->editFormTextBeforeContent .=
			$this->perpetuate( 'lqt_method', 'hidden' ) .
			$this->perpetuate( 'lqt_operand', 'hidden' ) .
			Html::hidden( 'lqt_nonce', wfGenerateToken() );

		$e->mShowSummaryField = false;

		$summary = wfMsgForContent( 'lqt-newpost-summary', $subject );
		$wgRequest->setVal( 'wpSummary', $summary );

		list( $signatureEditor, $signatureHTML ) = $this->getSignatureEditor( $this->user );

		$e->editFormTextAfterContent .=
			$signatureEditor;
		$e->previewTextAfterContent .=
			Xml::tags( 'p', null, $signatureHTML );

		$e->editFormTextBeforeContent .= $this->getSubjectEditor( '', $subject );

		wfRunHooks( 'LiquidThreadsAfterShowNewThreadForm', array( &$e, $talkpage ) );

		$e->edit();

		if ( $e->didSave ) {
			$signature = $this->request->getVal( 'wpLqtSignature', null );

			$info =
				array(
					'talkpage' => $talkpage,
					'text' => $e->textbox1,
					'summary' => $e->summary,
					'signature' => $signature,
					'root' => $article,
					'subject' => $subject,
				);

			wfRunHooks( 'LiquidThreadsSaveNewThread',
					array( &$info, &$e, &$talkpage ) );

			$thread = LqtView::newPostMetadataUpdates( $info );

			if ( $submitted_nonce && $nonce_key ) {
				global $wgMemc;
				$wgMemc->set( $nonce_key, 1, 3600 );
			}
		}

		if ( $this->output->getRedirect() != '' ) {
			   $redirectTitle = clone $talkpage->getTitle();
			   if ( !empty($thread) ) {
				   $redirectTitle->setFragment( '#' . $this->anchorName( $thread ) );
			   }
			   $this->output->redirect( $this->title->getLocalURL() );
		}

	}

	function showReplyForm( $thread ) {
		global $wgRequest;

		$submitted_nonce = $this->request->getVal( 'lqt_nonce' );
		$nonce_key = wfMemcKey( 'lqt-nonce', $submitted_nonce, $this->user->getName() );
		if ( ! $this->handleNonce( $submitted_nonce, $nonce_key ) ) return;

		$perm_result = $thread->canUserReply( $this->user );
		if ( $perm_result !== true ) {
			$this->showReplyProtectedNotice( $thread );
			return;
		}

		$html = Xml::openElement( 'div',
					array( 'class' => 'lqt-reply-form' ) );
		$this->output->addHTML( $html );


		try {
			$t = $this->newReplyTitle( null, $thread );
		} catch ( MWException $excep ) {
			$t = $this->scratchTitle();
			$valid_subject = false;
		}

		$article = new Article( $t );
		$talkpage = $thread->article();

		LqtHooks::$editTalkpage = $talkpage;
		LqtHooks::$editArticle = $article;
		LqtHooks::$editThread = $thread;
		LqtHooks::$editType = 'reply';
		LqtHooks::$editAppliesTo = $thread;

		$e = new EditPage( $article );

		$e->mShowSummaryField = false;

		$reply_subject = $thread->subject();
		$reply_title = $thread->title()->getPrefixedText();
		$summary = wfMsgForContent(
			'lqt-reply-summary',
			$reply_subject,
			$reply_title
		);
		$wgRequest->setVal( 'wpSummary', $summary );

		// Add an offset so it works if it's on the wrong page.
		$dbr = wfGetDB( DB_SLAVE );
		$offset = wfTimestamp( TS_UNIX, $thread->topmostThread()->sortkey() );
		$offset++;
		$offset = $dbr->timestamp( $offset );

		$e->suppressIntro = true;
		$e->editFormTextBeforeContent .=
			$this->perpetuate( 'lqt_method', 'hidden' ) .
			$this->perpetuate( 'lqt_operand', 'hidden' ) .
			Html::hidden( 'lqt_nonce', wfGenerateToken() ) .
			Html::hidden( 'offset', $offset ) .
			Html::hidden( 'wpMinorEdit', '' );

		list( $signatureEditor, $signatureHTML ) = $this->getSignatureEditor( $this->user );

		$e->editFormTextAfterContent .=
			$signatureEditor;
		$e->previewTextAfterContent .=
			Xml::tags( 'p', null, $signatureHTML );

		$wgRequest->setVal( 'wpWatchThis', false );

		wfRunHooks( 'LiquidThreadsShowReplyForm', array( &$e, $thread ) );

		$e->edit();

		if ( $e->didSave ) {
			$bump = $this->request->getBool( 'wpBumpThread' );
			$signature = $this->request->getVal( 'wpLqtSignature', null );

			$info = array(
					'replyTo' => $thread,
					'text' => $e->textbox1,
					'summary' => $e->summary,
					'bump' => $bump,
					'signature' => $signature,
					'root' => $article,
				);

			wfRunHooks( 'LiquidThreadsSaveReply',
					array( &$info, &$e, &$thread ) );

			$newThread = LqtView::replyMetadataUpdates( $info );

			if ( $submitted_nonce && $nonce_key ) {
				global $wgMemc;
				$wgMemc->set( $nonce_key, 1, 3600 );
			}
		}

		if ( $this->output->getRedirect() != '' ) {
			   $redirectTitle = clone $talkpage->getTitle();
			   if ( !empty( $newThread ) ) {
				   $redirectTitle->setFragment( '#' .
					$this->anchorName( $newThread ) );
			   }
			   $this->output->redirect( $this->title->getLocalURL() );
		}

		$this->output->addHTML( '</div>' );
	}

	function showPostEditingForm( $thread ) {
		$submitted_nonce = $this->request->getVal( 'lqt_nonce' );
		$nonce_key = wfMemcKey( 'lqt-nonce', $submitted_nonce, $this->user->getName() );
		if ( ! $this->handleNonce( $submitted_nonce, $nonce_key ) ) return;

		$subject_expected = $thread->isTopmostThread();
		$subject = $this->request->getVal( 'lqt_subject_field', '' );

		if ( !$subject ) {
			$subject = $thread->subject();
		}

		$t = null;
		$subjectOk = Thread::validateSubject( $subject, $t,
					$thread->superthread(), $this->article );
		if ( ! $subjectOk ) {
			$subject = false;
		}

		$article = $thread->root();
		$talkpage = $thread->article();

		wfRunHooks( 'LiquidThreadsEditFormContent', array( $thread, &$article, $talkpage ) );

		LqtHooks::$editTalkpage = $talkpage;
		LqtHooks::$editArticle = $article;
		LqtHooks::$editThread = $thread;
		LqtHooks::$editType = 'edit';
		LqtHooks::$editAppliesTo = $thread;

		$e = new EditPage( $article );

		global $wgRequest;
		// Quietly force a preview if no subject has been specified.
		if ( !$subjectOk ) {
			// Dirty hack to prevent saving from going ahead
			$wgRequest->setVal( 'wpPreview', true );

			if ( $this->request->wasPosted() ) {
				$e->editFormPageTop .=
					Xml::tags( 'div', array( 'class' => 'error' ),
						wfMsgExt( 'lqt_invalid_subject', 'parse' ) );
			}
		}

		// Add an offset so it works if it's on the wrong page.
		$dbr = wfGetDB( DB_SLAVE );
		$offset = wfTimestamp( TS_UNIX, $thread->topmostThread()->sortkey() );
		$offset++;
		$offset = $dbr->timestamp( $offset );

		$e->suppressIntro = true;
		$e->editFormTextBeforeContent .=
			$this->perpetuate( 'lqt_method', 'hidden' ) .
			$this->perpetuate( 'lqt_operand', 'hidden' ) .
			Html::hidden( 'lqt_nonce', wfGenerateToken() ) .
			Html::hidden( 'offset', $offset );

		list( $signatureEditor, $signatureHTML ) = $this->getSignatureEditor( $thread );

		$e->editFormTextAfterContent .=
			$signatureEditor;
		$e->previewTextAfterContent .=
			Xml::tags( 'p', null, $signatureHTML );

		if ( $thread->isTopmostThread() ) {
			$e->editFormTextBeforeContent .=
				$this->getSubjectEditor( $thread->subject(), $subject );
		}

		$e->edit();

		if ( $e->didSave ) {
			$bump = $this->request->getBool( 'wpBumpThread' );
			$signature = $this->request->getVal( 'wpLqtSignature', null );

			LqtView::editMetadataUpdates(
				array(
					'thread' => $thread,
					'text' => $e->textbox1,
					'summary' => $e->summary,
					'bump' => $bump,
					'subject' => $subject,
					'signature' => $signature,
					'root' => $article,
				)
			);

			if ( $submitted_nonce && $nonce_key ) {
				global $wgMemc;
				$wgMemc->set( $nonce_key, 1, 3600 );
			}
		}

		if ( $this->output->getRedirect() != '' ) {
			   $redirectTitle = clone $talkpage->getTitle();
			   $redirectTitle->setFragment( '#' . $this->anchorName( $thread ) );
			   $this->output->redirect( $this->title->getLocalURL() );
		}

	}

	function showSummarizeForm( $thread ) {
		$submitted_nonce = $this->request->getVal( 'lqt_nonce' );
		$nonce_key = wfMemcKey( 'lqt-nonce', $submitted_nonce, $this->user->getName() );
		if ( ! $this->handleNonce( $submitted_nonce, $nonce_key ) ) return;

		if ( $thread->summary() ) {
			$article = $thread->summary();
		} else {
			$t = $this->newSummaryTitle( $thread );
			$article = new Article( $t );
		}

		$this->output->addWikiMsg( 'lqt-summarize-intro' );

		$talkpage = $thread->article();

		LqtHooks::$editTalkpage = $talkpage;
		LqtHooks::$editArticle = $article;
		LqtHooks::$editThread = $thread;
		LqtHooks::$editType = 'summarize';
		LqtHooks::$editAppliesTo = $thread;

		$e = new EditPage( $article );

		// Add an offset so it works if it's on the wrong page.
		$dbr = wfGetDB( DB_SLAVE );
		$offset = wfTimestamp( TS_UNIX, $thread->topmostThread()->sortkey() );
		$offset++;
		$offset = $dbr->timestamp( $offset );

		$e->suppressIntro = true;
		$e->editFormTextBeforeContent .=
			$this->perpetuate( 'lqt_method', 'hidden' ) .
			$this->perpetuate( 'lqt_operand', 'hidden' ) .
			Html::hidden( 'lqt_nonce', wfGenerateToken() ) .
			Html::hidden( 'offset', $offset );

		$e->edit();

		if ( $e->didSave ) {
			$bump = $this->request->getBool( 'wpBumpThread' );

			LqtView::summarizeMetadataUpdates(
				array(
					'thread' => $thread,
					'article' => $article,
					'summary' => $e->summary,
					'bump' => $bump,
				)
			);

			if ( $submitted_nonce && $nonce_key ) {
				global $wgMemc;
				$wgMemc->set( $nonce_key, 1, 3600 );
			}
		}

		if ( $this->output->getRedirect() != '' ) {
			   $redirectTitle = clone $talkpage->getTitle();
			   $redirectTitle->setFragment( '#' . $this->anchorName( $thread ) );
			   $this->output->redirect( $this->title->getLocalURL() );
		}

	}

	public function handleNonce( $submitted_nonce, $nonce_key ) {
		// Add a one-time random string to a hidden field. Store the random string
		//	in memcached on submit and don't allow the edit to go ahead if it's already
		//	been added.
		if ( $submitted_nonce ) {
			global $wgMemc;

			if ( $wgMemc->get( $nonce_key ) ) {
				$this->output->redirect( $this->article->getTitle()->getLocalURL() );
				return false;
			}
		}

		return true;
	}

	public function getSubjectEditor( $db_subject, $subject ) {
		if ( $subject === false ) $subject = $db_subject;

		$subject_label = wfMsg( 'lqt_subject' );

		$attr = array( 'tabindex' => 1 );

		return Xml::inputLabel( $subject_label, 'lqt_subject_field',
				'lqt_subject_field', 60, $subject, $attr ) .
			Xml::element( 'br' );
	}

	public function getSignatureEditor( $from ) {
		$signatureText = $this->request->getVal( 'wpLqtSignature', null );

		if ( is_null( $signatureText ) ) {
			if ( $from instanceof User ) {
				$signatureText = LqtView::getUserSignature( $from );
			} elseif ( $from instanceof Thread ) {
				$signatureText = $from->signature();
			}
		}

		$signatureHTML = LqtView::parseSignature( $signatureText );

		// Signature edit box
		$signaturePreview = Xml::tags(
			'span',
			array(
				'class' => 'lqt-signature-preview',
				'style' => 'display: none;'
			),
			$signatureHTML
		);
		$signatureEditBox = Xml::input(
			'wpLqtSignature', 45, $signatureText,
			array( 'class' => 'lqt-signature-edit' )
		);

		$signatureEditor = $signaturePreview . $signatureEditBox;

		return array( $signatureEditor, $signatureHTML );
	}

	static function replyMetadataUpdates( $data = array() ) {
		$requiredFields = array( 'replyTo', 'root', 'text' );

		foreach ( $requiredFields as $f ) {
			if ( !isset( $data[$f] ) ) {
				throw new MWException( "Missing required field $f" );
			}
		}

		if ( isset( $data['signature'] ) ) {
			$signature = $data['signature'];
		} else {
			global $wgUser;
			$signature = LqtView::getUserSignature( $wgUser );
		}

		$summary = isset( $data['summary'] ) ? $data['summary'] : '';

		$replyTo = $data['replyTo'];
		$root = $data['root'];
		$text = $data['text'];
		$bump = !empty( $data['bump'] );

		$subject = $replyTo->subject();
		$talkpage = $replyTo->article();

		$thread = Thread::create(
			$root, $talkpage, $replyTo, Threads::TYPE_NORMAL, $subject,
			$summary, $bump, $signature
		);

		wfRunHooks( 'LiquidThreadsAfterReplyMetadataUpdates', array( &$thread ) );

		return $thread;
	}

	static function summarizeMetadataUpdates( $data = array() ) {
		$requiredFields = array( 'thread', 'article', 'summary' );

		foreach ( $requiredFields as $f ) {
			if ( !isset( $data[$f] ) ) {
				throw new MWException( "Missing required field $f" );
			}
		}

		$thread = $data["thread"];
		$article = $data["article"];
		$summary = $data["summary"];

		$bump = isset( $bump ) ? $bump : null;

		$thread->setSummary( $article );
		$thread->commitRevision(
			Threads::CHANGE_EDITED_SUMMARY, $thread, $summary, $bump );

		return $thread;
	}

	static function editMetadataUpdates( $data = array() ) {
		$requiredFields = array( 'thread', 'text', 'summary' );

		foreach ( $requiredFields as $f ) {
			if ( !isset( $data[$f] ) ) {
				throw new MWException( "Missing required field $f" );
			}
		}

		$thread = $data['thread'];

		// Use a separate type if the content is blanked.
		$type = strlen( trim( $data['text'] ) )
				? Threads::CHANGE_EDITED_ROOT
				: Threads::CHANGE_ROOT_BLANKED;

		if ( isset( $data['signature'] ) ) {
			$thread->setSignature( $data['signature'] );
		}

		$bump = !empty( $data['bump'] );

		// Add the history entry.
		$thread->commitRevision( $type, $thread, $data['summary'], $bump );

		// Update subject if applicable.
		if ( $thread->isTopmostThread() && !empty( $data['subject'] ) &&
				$data['subject'] != $thread->subject() ) {
			$thread->setSubject( $data['subject'] );
			$thread->commitRevision( Threads::CHANGE_EDITED_SUBJECT,
						$thread, $data['summary'] );

			// Disabled page-moving for now.
			// $this->renameThread( $thread, $subject, $e->summary );
		}

		return $thread;
	}

	static function newPostMetadataUpdates( $data )
	{
		$requiredFields = array( 'talkpage', 'root', 'text', 'subject' );

		foreach ( $requiredFields as $f ) {
			if ( !isset( $data[$f] ) ) {
				throw new MWException( "Missing required field $f" );
			}
		}

		if ( isset( $data['signature'] ) ) {
			$signature = $data['signature'];
		} else {
			global $wgUser;
			$signature = LqtView::getUserSignature( $wgUser );
		}

		$summary = isset( $data['summary'] ) ? $data['summary'] : '';

		$talkpage = $data['talkpage'];
		$root = $data['root'];
		$text = $data['text'];
		$subject = $data['subject'];

		$thread = Thread::create(
			$root, $talkpage, null,
			Threads::TYPE_NORMAL, $subject,
			$summary, null, $signature
		);

		wfRunHooks( 'LiquidThreadsAfterNewPostMetadataUpdates', array( &$thread ) );

		return $thread;
	}

	function renameThread( $t, $s, $reason ) {
		$this->simplePageMove( $t->root()->getTitle(), $s, $reason );
		// TODO here create a redirect from old page to new.

		foreach ( $t->subthreads() as $st ) {
			$this->renameThread( $st, $s, $reason );
		}
	}

	function newThreadTitle( $subject ) {
		return Threads::newThreadTitle( $subject, $this->article );
	}

	function newSummaryTitle( $thread ) {
		return Threads::newSummaryTitle( $thread );
	}

	function newReplyTitle( $unused, $thread ) {
		return Threads::newReplyTitle( $thread, $this->user );
	}

	/* Adapted from MovePageForm::doSubmit in SpecialMovepage.php. */
	function simplePageMove( $old_title, $new_subject, $reason ) {
		if ( $this->user->pingLimiter( 'move' ) ) {
			$this->out->rateLimited();
			return false;
		}

		# Variables beginning with 'o' for old article 'n' for new article
		$ot = $old_title;
		$nt = $this->incrementedTitle( $new_subject, $old_title->getNamespace() );

		self::$occupied_titles[] = $nt->getPrefixedDBkey();

		$error = $ot->moveTo( $nt, true, "Changed thread subject: $reason" );
		if ( $error !== true ) {
			throw new MWException( "Got error $error trying to move pages." );
		}

		# Move the talk page if relevant, if it exists, and if we've been told to
		 // TODO we need to implement correct moving of talk pages everywhere later.
		// Snipped.

		return true;
	}

	function scratchTitle() {
		return Title::makeTitle( NS_LQT_THREAD, wfGenerateToken() );
	}

	static function anchorName( $thread ) {
		return $thread->getAnchorName();
	}

	function getMustShowThreads( $threads = array() ) {
		if ( $this->request->getCheck( 'lqt_operand' ) ) {
			$operands = explode( ',', $this->request->getVal( 'lqt_operand' ) );
			$threads = array_merge( $threads, $operands );
		}

		if ( $this->request->getCheck( 'lqt_mustshow' ) ) {
			// Check for must-show in the request
			$specifiedMustShow = $this->request->getVal( 'lqt_mustshow' );
			$specifiedMustShow = explode( ',', $specifiedMustShow );

			$threads = array_merge( $threads, $specifiedMustShow );
		}

		foreach ( $threads as $walk_thread ) {
			do {
				if ( !is_object( $walk_thread ) ) {
					$old_walk_thread = $walk_thread;
					$walk_thread = Threads::withId( $walk_thread );
				}

				if ( !is_object( $walk_thread ) ) {
					continue;
				}

				$threads[$walk_thread->id()] = $walk_thread;
				$walk_thread = $walk_thread->superthread();
			} while ( $walk_thread );
		}

		return $threads;
	}

	function getShowMore( $thread, $st, $i ) {
		$sk = $this->user->getSkin();

		$linkText = wfMsgExt( 'lqt-thread-show-more', 'parseinline' );
		$linkTitle = clone $thread->topmostThread()->title();
		$linkTitle->setFragment( '#' . $st->getAnchorName() );

		$link = $sk->link( $linkTitle, $linkText,
				array( 'class' => 'lqt-show-more-posts' ) );
		$link .= Html::hidden( 'lqt-thread-start-at', $i,
				array( 'class' => 'lqt-thread-start-at' ) );

		return $link;
	}

	function getShowReplies( $thread ) {
		global $wgLang;

		$sk = $this->user->getSkin();

		$replyCount = $wgLang->formatNum( $thread->replyCount() );
		$linkText = wfMsgExt( 'lqt-thread-show-replies', 'parseinline', $replyCount );
		$linkTitle = clone $thread->topmostThread()->title();
		$linkTitle->setFragment( '#' . $thread->getAnchorName() );

		$link = $sk->link( $linkTitle, $linkText,
				array( 'class' => 'lqt-show-replies' ) );
		$link = Xml::tags( 'div', array( 'class' => 'lqt-thread-replies' ), $link );

		return $link;
	}

	function showReplyBox( $thread ) {
		// Check if we're actually replying to this thread.
		if ( $this->methodAppliesToThread( 'reply', $thread ) ) {
			$this->showReplyForm( $thread );
			return;
		} elseif ( !$thread->canUserReply( $this->user ) ) {
			return;
		}

		$html = '';
		$html .= Xml::tags( 'div',
				array( 'class' => 'lqt-reply-form lqt-edit-form',
					'style' => 'display: none;'	 ),
				'' );

		$this->output->addHTML( $html );
	}

	function threadDivClass( $thread ) {
		$levelClass = 'lqt-thread-nest-' . $this->threadNestingLevel;
		$alternatingType = ( $this->threadNestingLevel % 2 ) ? 'odd' : 'even';
		$alternatingClass = "lqt-thread-$alternatingType";
		$topmostClass = $thread->isTopmostThread() ? ' lqt-thread-topmost' : '';

		return "lqt_thread $levelClass $alternatingClass$topmostClass";
	}

	function getSummary( $t ) {
		if ( !$t->summary() ) {
			return;
		}
		if ( !$t->summary()->getContent() ) {
			return; // Blank summary
		}
		global $wgUser;
		$sk = $wgUser->getSkin();

		$label = wfMsgExt( 'lqt_summary_label', 'parseinline' );
		$edit_text = wfMsgExt( 'edit', 'parseinline' );
		$link_text = wfMsg( 'lqt_permalink', 'parseinline' );

		$html = '';

		$html .= Xml::tags(
			'span',
			array( 'class' => 'lqt_thread_permalink_summary_title' ),
			$label
		);

		$link = $sk->link( $t->summary()->getTitle(), $link_text,
				array( 'class' => 'lqt-summary-link' ) );
		$link .= Html::hidden( 'summary-title', $t->summary()->getTitle()->getPrefixedText() );
		$edit_link = self::permalink( $t, $edit_text, 'summarize', $t->id() );
		$links = "[$link]\n[$edit_link]";
		$html .= Xml::tags(
			'span',
			array( 'class' => 'lqt_thread_permalink_summary_actions' ),
			$links
		);

		$summary_body = $this->showPostBody( $t->summary() );
		$html .= Xml::tags(
			'div',
			array( 'class' => 'lqt_thread_permalink_summary_body' ),
			$summary_body
		);

		$html = Xml::tags(
			'div',
			array( 'class' => 'lqt_thread_permalink_summary' ),
			$html
		);

		return $html;
	}

	function showSummary( $t ) {
		$this->output->addHTML( $this->getSummary( $t ) );
	}

	function getSignature( $user ) {
		if ( is_object( $user ) ) {
			$uid = $user->getId();
			$name = $user->getName();
		} elseif ( is_integer( $user ) ) {
			$uid = $user;
			$user = User::newFromId( $uid );
			$name = $user->getName();
		} else {
			$user = User::newFromName( $user );
			$name = $user->getName();
			$uid = $user->getId();
		}

		if ( $this->user->getOption( 'lqtcustomsignatures' ) ) {
			return $this->getUserSignature( $user, $uid, $name );
		} else {
			return $this->getBoringSignature( $user, $uid, $name );
		}
	}

	static function getUserSignature( $user, $uid = null ) {
		global $wgParser;
		if ( !$user ) {
			$user = User::newFromId( $uid );
		}

		$wgParser->Options( new ParserOptions );

		$sig = $wgParser->getUserSig( $user );

		return $sig;
	}

	static function parseSignature( $sig ) {
		global $wgParser, $wgOut, $wgTitle;

		static $parseCache = array();
		$sigKey = md5( $sig );

		if ( isset( $parseCache[$sigKey] ) ) {
			return $parseCache[$sigKey];
		}

		// Parser gets antsy about parser options here if it hasn't parsed anything before.
		$wgParser->clearState();
		$wgParser->setTitle( $wgTitle );
		$wgParser->mOptions = new ParserOptions;

		$sig = $wgOut->parseInline( $sig );

		$parseCache[$sigKey] = $sig;

		return $sig;
	}

	static function signaturePST( $sig, $user ) {
		global $wgParser, $wgTitle;

		$title = $wgTitle ? $wgTitle : $user->getUserPage();

		// Parser gets antsy about parser options here if it hasn't parsed anything before.
		$wgParser->clearState();
		$wgParser->setTitle( $title );
		$wgParser->mOptions = new ParserOptions;

		$sig = $wgParser->preSaveTransform(
			$sig,
			$title,
			$user,
			$wgParser->mOptions,
			false
		);

		return $sig;
	}

	function customizeTabs( $skin, &$links ) {
		// No-op
	}

	function customizeNavigation( $skin, &$links ) {
		// No-op
	}

	function show() {
		return true; // No-op
	}
}
