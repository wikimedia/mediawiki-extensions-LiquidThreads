<?php

/**
* @package MediaWiki
* @subpackage LiquidThreads
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

	protected $headerLevel = 2; 	/* h1, h2, h3, etc. */
	protected $lastUnindentedSuperthread;

	public $threadNestingLevel = 0;

	protected $sort_order = LQT_NEWEST_CHANGES;
	
	static $stylesAndScriptsDone = false;
	
	static $userSignatureCache = array();
	static $boringSignatureCache = array();

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
									$uquery = array() ) {
		list ( $title, $query ) = self::permalinkData( $thread, $method, $operand );
		
		$query = array_merge( $query, $uquery );
		
		$queryString = wfArrayToCGI( $query );
		
		return $title->getFullUrl( $queryString );
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
		
		return array( $thread->root()->getTitle(), $query );
	}

	/* This is used for action=history so that the history tab works, which is
	   why we break the lqt_method paradigm. */
	static function permalinkUrlWithQuery( $thread, $query ) {
		if ( !is_array( $query ) ) {
			$query = wfCGIToArray( $query );
		}
		
		return self::permalinkUrl( $thread, null, null, $query );
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
		
		if ( $contextType == 'page' ) {
			$title = clone $thread->article()->getTitle();
			
			$dbr = wfGetDB( DB_SLAVE );
			$offset = $thread->topmostThread()->sortkey();
			$offset = wfTimestamp( TS_UNIX, $offset ) + 1;
			$offset = $dbr->timestamp( $offset );
			$query['offset'] = $offset;
		} else {
			$title = clone $thread->title();
		}
		
		$query['lqt_mustshow'] = $thread->id();
		
		$title->setFragment( '#'.$thread->getAnchorName() );
		
		return array( $title, $query );
	}
	
	static function linkInContext( $thread, $contextType = 'page', $text = null ) {
		list( $title, $query ) = self::linkInContextData( $thread, $contextType );
		
		global $wgUser;
		$sk = $wgUser->getSkin();
		
		return $sk->link( $title, $text, array(), $query );
	}
	
	static function linkInContextURL( $thread, $contextType = 'page' ) {
		list( $title, $query ) = self::linkInContextData( $thread, $contextType );
		
		return $title->getFullURL( $query );
	}
	
	static function diffQuery( $thread, $revision ) {
		$changed_thread = $revision->getChangeObject();
		$curr_rev_id = $changed_thread->rootRevision();
		$curr_rev = Revision::newFromId( $curr_rev_id );
		$prev_rev = $curr_rev->getPrevious();
		$oldid = $prev_rev ? $prev_rev->getId() : "";
		
		$query = array( 'lqt_method' => 'diff',
						'diff' => $curr_rev_id,
						'oldid' => $oldid );
		
		return $query;
	}

	static function diffPermalinkURL( $thread, $revision ) {
		$query = self::diffQuery( $thread, $revision );
		return self::permalinkUrl( $thread, null, null, $query );
	}
	
	static function diffPermalink( $thread, $text, $revision ) {
		$query = self::diffQuery( $thread, $revision );
		return self::permalink( $thread, $text, null, null, null, array(), $query );
	}
	
	static function talkpageLink( $title, $text = null , $method = null, $operand = null,
					$includeFragment = true, $attribs = array(),
					$options = array() )
	{
		list( $title, $query ) = self::talkpageLinkData( $title, $method, $operand,
								$includeFragment );
		
		global $wgUser;
		$sk = $wgUser->getSkin();
		
		return $sk->link( $title, $text, $attribs, $query, $options );
	}
	
	static function talkpageLinkData( $title, $method = null, $operand = null,
						$includeFragment = true ) {
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
		
		// Add fragment if appropriate.
		if ( $operand && $includeFragment ) {
			$title->mFragment = 'lqt_thread_' . $operand->id();
		}
		
		return array( $title, $query );
	}

	/* If you want $perpetuateOffset to perpetuate from a specific request, pass that instead
	   of true */
	static function talkpageUrl( $title, $method = null, $operand = null,
					$includeFragment = true, $perpetuateOffset = true ) {
		global $wgUser;
		$sk = $wgUser->getSkin();
		
		list( $title, $query ) =
			self::talkpageLinkData( $title, $method, $operand, $includeFragment );
		
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
		
		return $title->getLinkUrl( $query );
	}


	/**
     * Return a URL for the current page, including Title and query vars,
	 * with the given replacements made.
     * @param $repls array( 'name'=>new_value, ... )
	*/
	function queryReplaceLink( $repls ) {
		$query = $this->getReplacedQuery( $repls );
		
		return $this->title->getFullURL( wfArrayToCGI( $vs ) );
	}
	
	function getReplacedQuery( $replacements ) {
		$values = $this->request->getValues();
		
		foreach ( $replacements as $k => $v ) {
			$values[$k] = $v;
		}
		
		return $values;
	}

	/*************************************************************
	* Editing methods (here be dragons)                          *
    * Forget dragons: This section distorts the rest of the code *
    * like a star bending spacetime around itself.               *
	*************************************************************/

	/**
	 * Return an HTML form element whose value is gotten from the request.
	 * TODO: figure out a clean way to expand this to other forms.
	 */
	function perpetuate( $name, $as ) {
		$value = $this->request->getVal( $name, '' );
		if ( $as == 'hidden' ) {
			return Xml::hidden( $name, $value );
		}
	}

	function showReplyProtectedNotice( $thread ) {
		wfLoadExtensionMessages( 'LiquidThreads' );
		$log_url = SpecialPage::getTitleFor( 'Log' )->getFullURL(
			"type=protect&user=&page={$thread->title()->getPrefixedURL()}" );
		$this->output->addHTML( '<p>' . wfMsg( 'lqt_protectedfromreply',
			'<a href="' . $log_url . '">' . wfMsg( 'lqt_protectedfromreply_link' ) . '</a>' ) );
	}

	function showNewThreadForm() {
		$this->showEditingFormInGeneral( null, 'new', null );
	}

	function showPostEditingForm( $thread ) {
		$this->showEditingFormInGeneral( $thread, 'editExisting', null );
	}

	function showReplyForm( $thread ) {
		if ( $thread->root()->getTitle()->userCan( 'edit' ) ) {
			$this->showEditingFormInGeneral( null, 'reply', $thread );
		} else {
			$this->showReplyProtectedNotice( $thread );
		}
	}

	function showSummarizeForm( $thread ) {
		$this->output->addWikiMsg( 'lqt-summarize-intro' );
		$this->showEditingFormInGeneral( $thread, 'summarize', $thread );
	}
	
	function doInlineEditForm() {
		$method = $this->request->getVal( 'lqt_method' );
		$operand = $this->request->getVal( 'lqt_operand' );
		
		$thread = Threads::withId( intval( $operand ) );
		
		if ( $method == 'reply' ) {
			$this->showReplyForm( $thread );
		} elseif ( $method == 'talkpage_new_thread' ) {
			$this->showNewThreadForm();
		} elseif ( $method == 'edit' ) {
			$this->showPostEditingForm( $thread );
		}
		
		$this->output->setArticleBodyOnly( true );
	}

	private function showEditingFormInGeneral( $thread, $edit_type, $edit_applies_to ) {
		/*
		 EditPage needs an Article. If there isn't a real one, as for new posts,
		 replies, and new summaries, we need to generate a title. Auto-generated
		 titles are based on the subject line. If the subject line is blank, we
		 can temporarily use a random scratch title. It's fine if the title changes
		 throughout the edit cycle, since the article doesn't exist yet anyways.
		*/
		
		// Check permissions
		if ( $edit_type == 'new' ) {
			if ( Thread::canUserPost( $this->user, $this->article ) !== true ) {
				$this->output->addWikiMsg( 'lqt-protected-newthread' );
				return;
			}
		} elseif ( $edit_type == 'reply' ) {
			$perm_result = $edit_applies_to->canUserReply( $this->user );
			if ( $perm_result !== true ) {
				$msg = "lqt-protected-reply-$perm_result";
				$this->output->addWikiMsg( $msg );
				return;
			}
		}

		// Check if we actually want a subject, pull the submitted subject, and validate it.
		$subject_expected = ( $edit_type == 'new' ||
						$thread && $thread->isTopmostThread() ) &&
					$edit_type != 'summarize';
		$subject = $this->request->getVal( 'lqt_subject_field', '' );
		$valid_subject = true;
		
		if ( $edit_type == 'summarize' && $edit_applies_to->summary() ) {
			$article = $edit_applies_to->summary();
		} elseif ( $edit_type == 'summarize' ) {
			$t = $this->newSummaryTitle( $edit_applies_to );
			$article = new Article( $t );
		} elseif ( !$thread ) {
			$t = null;
			
			$subjectOk = Thread::validateSubject( $subject, $t,
						$edit_applies_to, $this->article );
			if ( ! $subjectOk ) {
				$subject = false;
			}
			
			if ( !$subject && $subject_expected ) {
				// Dodgy title
				$valid_subject = false;
			} elseif ( ! $t ) {
				try {
					if ( $edit_type == 'new' ) {
						$t = $this->newThreadTitle( $subject );
					} elseif ( $edit_type == 'reply' ) {
						$t = $this->newReplyTitle( $subject, $edit_applies_to );
					}
				} catch ( MWException $excep ) {
					$t = $this->scratchTitle();
					$valid_subject = false;
				}
			}
			$article = new Article( $t );
		} else {
			$article = $thread->root();
		}
		
		$talkpage = $this->article;
		if ( $thread ) {
			$talkpage = $thread->article();
		} elseif ( $edit_applies_to ) {
			$talkpage = $edit_applies_to->article();
		}
		
		LqtHooks::$editTalkpage = $talkpage;
		LqtHooks::$editArticle = $article;
		LqtHooks::$editThread = $thread;
		LqtHooks::$editType = $edit_type;
		LqtHooks::$editAppliesTo = $edit_applies_to;

		$e = new EditPage( $article );
		
		// Display an error if a subject is specified but it's invalid
		if ( $subject_expected && $this->request->wasPosted() && !$valid_subject ) {
			if ( !$subject ) {
				$msg = 'lqt_empty_subject';
 			} else {
				$msg = 'lqt_invalid_subject';
			}
			
			$e->editFormPageTop .=
				Xml::tags( 'div', array( 'class' => 'error' ),
					wfMsgExt( $msg, 'parse' ) );
		}
		
		global $wgRequest;
		// Quietly force a preview if no subject has been specified.
		if ( ( !$valid_subject && $subject ) || ( $subject_expected && !$subject ) ) {
			// Dirty hack to prevent saving from going ahead
			$wgRequest->setVal( 'wpPreview', true );
		}
		
		// For new posts and replies, remove the summary field and use a boilerplate
		//  default.
		if ( $edit_type == 'new' ) {
			$e->mShowSummaryField = false;
			
			$summary = wfMsgForContent( 'lqt-newpost-summary', $subject );
			$wgRequest->setVal( 'wpSummary', $summary );
		} elseif ( $edit_type == 'reply' ) {
			$e->mShowSummaryField = false;
			
			$reply_subject = $edit_applies_to->subject();
			$reply_title = $edit_applies_to->title()->getPrefixedText();
			$summary = wfMsgForContent( 'lqt-reply-summary',
					$reply_subject, $reply_title );
			$wgRequest->setVal( 'wpSummary', $summary );
		}
		
		// Add an offset so it works if it's on the wrong page.
		if ( $edit_applies_to ) {
			$dbr = wfGetDB( DB_SLAVE );
			$offset = wfTimestamp( TS_UNIX, $edit_applies_to->topmostThread()->modified() );
			$offset++;
			$offset = $dbr->timestamp( $offset );
		} else $offset = '';

		$e->suppressIntro = true;
		$e->editFormTextBeforeContent .=
			$this->perpetuate( 'lqt_method', 'hidden' ) .
			$this->perpetuate( 'lqt_operand', 'hidden' ) .
			Xml::hidden( 'lqt_nonce', wfGenerateToken() ) .
			Xml::hidden( 'offset', $offset );
		
		$e->editFormTextAfterContent .=
			Xml::tags( 'p', null, $this->getSignature( $this->user ) );
		$e->previewTextAfterContent .=
			Xml::tags( 'p', null, $this->getSignature( $this->user ) );
			
		// Add a one-time random string to a hidden field. Store the random string
		//  in memcached on submit and don't allow the edit to go ahead if it's already
		//  been added.
		$submitted_nonce = $this->request->getVal( 'lqt_nonce' );
		if ( $submitted_nonce ) {
			global $wgMemc;
			
			$nonce_key = wfMemcKey( 'lqt-nonce', $submitted_nonce, $this->user->getName() );
			if ( $wgMemc->get( $nonce_key ) ) {
				$this->output->redirect( $this->article->getTitle()->getFullURL() );
				return;
			}
		}

		if ( $subject_expected ) {
			wfLoadExtensionMessages( 'LiquidThreads' );
			// This is a top-level post; show the subject line.
			$db_subject = $thread ? $thread->subjectWithoutIncrement() : '';
			$subject = $this->request->getVal( 'lqt_subject_field', $db_subject );
			$subject_label = wfMsg( 'lqt_subject' );
			
			$attr = array( 'tabindex' => 1 );
			
			$e->editFormTextBeforeContent .=
				Xml::inputLabel( $subject_label, 'lqt_subject_field',
					'lqt_subject_field', 60, $subject, $attr ) .
				Xml::element( 'br' );
		}

		$e->edit();

		// Override what happens in EditPage::showEditForm, called from $e->edit():

		$this->output->setArticleFlag( false );
		
		if ( $e->didSave ) {
			// Move the thread and replies if subject changed.
			if ( $edit_type == 'editExisting' && $subject &&
					$subject != $thread->subject() ) {
				$thread->setSubject( $subject );
				$thread->commitRevision( Threads::CHANGE_EDITED_SUBJECT,
							$thread, $e->summary );
				
				// Disabled page-moving for now.
				// $this->renameThread( $thread, $subject, $e->summary );
			}
			
			$thread = self::postEditUpdates(
					$edit_type, $edit_applies_to, $article,
					$this->article,	$subject, $e->summary, $thread,
					$e->textbox1
				);
			
			if ( $submitted_nonce && $nonce_key ) {
				$wgMemc->set( $nonce_key, 1, 3600 );
			}
		}

		// A redirect without $e->didSave will happen if the new text is blank (EditPage::attemptSave).
		// This results in a new Thread object not being created for replies and new discussions,
		// so $thread is null. In that case, just allow editpage to redirect back to the talk page.
		if ( $this->output->getRedirect() != '' && $thread ) {
			$redirectTitle = clone $thread->article()->getTitle();
			$redirectTitle->setFragment( '#' . $this->anchorName( $thread ) );
			$this->output->redirect( $this->title->getFullURL() );
		} else if ( $this->output->getRedirect() != '' && $edit_applies_to ) {
			// For summaries:
			$redirectTitle = clone $edit_applies_to->article()->getTitle();
			$redirectTitle->setFragment( '#' . $this->anchorName( $edit_applies_to ) );
			$this->output->redirect( $redirectTitle->getFullURL() );
		}
	}
	
	static function postEditUpdates( $edit_type, $edit_applies_to, $edit_page, $article,
					$subject, $edit_summary, $thread, $new_text ) {
		// Update metadata - create and update thread and thread revision objects as
		//  appropriate.

		if ( $edit_type == 'reply' ) {
			$subject = $edit_applies_to->subject();
			
			$thread = Threads::newThread( $edit_page, $article, $edit_applies_to,
							Threads::TYPE_NORMAL, $subject );
			
			global $wgUser;
			NewMessages::markThreadAsReadByUser( $edit_applies_to, $wgUser );
		} elseif ( $edit_type == 'summarize' ) {
			$edit_applies_to->setSummary( $edit_page );
			$edit_applies_to->commitRevision( Threads::CHANGE_EDITED_SUMMARY,
							$edit_applies_to, $edit_summary );
		} elseif ( $edit_type == 'editExisting' ) {
			// Use a separate type if the content is blanked.
			$type = strlen( trim( $new_text ) )
					? Threads::CHANGE_EDITED_ROOT
					: Threads::CHANGE_ROOT_BLANKED;
			
			// Add the history entry.
			$thread->commitRevision( $type, $thread, $edit_summary );
		} else {
			$thread = Threads::newThread( $edit_page, $article, null,
							Threads::TYPE_NORMAL, $subject );
		}
		
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
			return false;
		}

		# Move the talk page if relevant, if it exists, and if we've been told to
		 // TODO we need to implement correct moving of talk pages everywhere later.
		// Snipped.

		return true;
	}

	/**
	* Example return value:
	*   array (
	*       edit => array( 'label'   => 'Edit',
	*                   'href'    => 'http...',
	*                   'enabled' => false ),
	*       reply => array( 'label'   => 'Reply',
	*                   'href'    => 'http...',
	*                   'enabled' => true )
	*   )
	*/
	function threadCommands( $thread ) {
		wfLoadExtensionMessages( 'LiquidThreads' );
		$commands = array();
		
		$history_url = self::permalinkUrlWithQuery( $thread, array( 'action' => 'history' ) );
		$commands['history'] = array( 'label' => wfMsgExt( 'history_short', 'parseinline' ),
						 'href' => $history_url,
						 'enabled' => true );

		if ( $thread->isHistorical() ) {
			return array();
		}
		$user_can_edit = $thread->root()->getTitle()->quickUserCan( 'edit' );
		$editMsg = $user_can_edit ? 'edit' : 'viewsource';

		$commands['edit'] = array( 'label' => wfMsgExt( $editMsg, 'parseinline' ),
		                     'href' => $this->talkpageUrl( $this->title, 'edit', $thread,
		                     	true /* include fragment */ , $this->request ),
		                     'enabled' => true );

		if ( $this->user->isAllowed( 'delete' ) ) {
			$delete_url = $thread->title()->getFullURL( 'action=delete' );
			$deleteMsg = $thread->type() == Threads::TYPE_DELETED ? 'lqt_undelete' : 'delete';
				
			$commands['delete'] = array( 'label' => wfMsgExt( $deleteMsg, 'parseinline' ),
							 'href' => $delete_url,
							 'enabled' => true );
		}
		
		if ( !$thread->isTopmostThread() && $this->user->isAllowed( 'lqt-split' ) ) {
			$splitUrl = SpecialPage::getTitleFor( 'SplitThread',
					$thread->title()->getPrefixedText() )->getFullURL();
			$commands['split'] = array( 'label' => wfMsgExt( 'lqt-thread-split', 'parseinline' ),
							'href' => $splitUrl, 'enabled' => true );
		}
		
		if ( $this->user->isAllowed( 'lqt-merge' ) ) {
			$mergeParams = $_GET;
			$mergeParams['lqt_merge_from'] = $thread->id();
			
			unset( $mergeParams['title'] );
			
			$mergeUrl = $this->title->getFullURL( wfArrayToCGI( $mergeParams ) );
			$label = wfMsgExt( 'lqt-thread-merge', 'parseinline' );
			
			$commands['merge'] = array( 'label' => $label,
							'href' => $mergeUrl, 'enabled' => true );
		}

		return $commands;
	}
	
	// Commands for the bottom.
	function threadMajorCommands( $thread ) {
		wfLoadExtensionMessages( 'LiquidThreads' );
		
		if ( $thread->isHistorical() ) {
			// No links for historical threads.
			$history_url = self::permalinkUrlWithQuery( $thread,
					array( 'action' => 'history' ) );
			$commands = array();
			
			$commands['history'] = array(
				'label' => wfMsgExt( 'history_short', 'parseinline' ),
				 'href' => $history_url,
				 'enabled' => true );
				 
			return $commands;
		}
		
		$commands = array();
		
		if ( $this->user->isAllowed( 'lqt-merge' ) &&
				$this->request->getCheck( 'lqt_merge_from' ) ) {
			$srcThread = Threads::withId( $this->request->getVal( 'lqt_merge_from' ) );
			$par = $srcThread->title()->getPrefixedText();
			$mergeTitle = SpecialPage::getTitleFor( 'MergeThread', $par );
			$mergeUrl = $mergeTitle->getFullURL( 'dest=' . $thread->id() );
			$label = wfMsgExt( 'lqt-thread-merge-to', 'parseinline' );
			
			$commands['merge-to'] = array( 'label' => $label, 'href' => $mergeUrl,
							'enabled' => true, 'tooltip' => $label );
		}
		
		if ( $thread->canUserReply( $this->user ) === true ) {
			$commands['reply'] = array(
				'label' => wfMsgExt( 'lqt_reply', 'parseinline' ),
				 'href' => $this->talkpageUrl( $this->title, 'reply', $thread,
					true /* include fragment */, $this->request ),
				 'enabled' => true,
				 'icon' => 'reply.png',
				 'showlabel' => 1,
				 'tooltip' => wfMsg( 'lqt_reply' )
			);
		}
		
		$commands['link'] = array(
			'label' => wfMsgExt( 'lqt_permalink', 'parseinline' ),
			'href' => $thread->title()->getFullURL(),
			'enabled' => true,
			'icon' => 'link.png',
			'showlabel' => true,
			'tooltip' => wfMsgExt( 'lqt_permalink', 'parseinline' )
		);
		/*
		if ( $thread->root()->getTitle()->quickUserCan( 'edit' ) ) {
			$commands['edit'] = array(
				'label' => wfMsgExt( 'edit', 'parseinline' ),
				'href' => $this->talkpageUrl( $this->title, 'edit', $thread,
					true, $this->request ),
				'enabled' => true, 'icon' => 'edit.png',
				'tooltip' => wfMsgExt( 'edit', 'parseinline' ) );
		}
		*/
		
		return $commands;
	}

	function topLevelThreadCommands( $thread ) {
		wfLoadExtensionMessages( 'LiquidThreads' );
		$commands = array();

		$commands['history'] = array( 'label' => wfMsg( 'history_short' ),
		                     'href' => self::permalinkUrl( $thread, 'thread_history' ),
		                     'enabled' => true );

		if ( $this->user->isAllowed( 'move' ) ) {
			$move_href = SpecialPage::getTitleFor( 'MoveThread' )->getFullURL()
				. '/' . $thread->title()->getPrefixedURL();
			$commands['move'] = array( 'label' => wfMsg( 'lqt-movethread' ),
			                     'href' => $move_href,
			                     'enabled' => true );
		}
		
		if ( $this->user->isAllowed( 'protect' ) ) {
			$protect_href = $thread->title()->getFullURL( 'action=protect' );
			
			// Check if it's already protected
			if ( !$thread->title()->isProtected() ) {
				$label = wfMsg( 'protect' );
			} else {
				$label = wfMsg( 'unprotect' );
			}
			
			$commands['protect'] = array( 'label' => $label,
							'href' => $protect_href,
							'enabled' => true, );
		}
		
		if ( !$this->user->isAnon() && !$thread->title()->userIsWatching() ) {
			$commands['watch'] = array( 'label' => wfMsg( 'watch' ),
			                     'href' => self::permalinkUrlWithQuery( $thread, 'action=watch' ),
			                     'enabled' => true );
		} else if ( !$this->user->isAnon() ) {
			$commands['unwatch'] = array( 'label' => wfMsg( 'unwatch' ),
                                 'href' => self::permalinkUrlWithQuery( $thread, 'action=unwatch' ),
			                     'enabled' => true );
		}
		
		$summarizeUrl = self::permalinkUrl( $thread, 'summarize', $thread->id() );
		$commands['summarize'] = array(
			'label' => wfMsgExt( 'lqt_summarize_link', 'parseinline' ),
			'href' => $summarizeUrl,
			'enabled' => true,
		);

		return $commands;
	}

	/*************************
	* Output methods         *
	*************************/

	static function addJSandCSS() {
		if ( self::$stylesAndScriptsDone ) {
			return;
		}
		
		global $wgOut, $wgStylePath;
		global $wgScriptPath, $wgStyleVersion;
		global $wgEnableJS2system;
		global $wgLiquidThreadsExtensionName;

		$wgOut->addInlineScript( 'var wgLqtMessages = ' . self::exportJSLocalisation() . ';' );
		
		$basePath = "$wgScriptPath/extensions/$wgLiquidThreadsExtensionName";
		
		if ( !$wgEnableJS2system ) {
			$wgOut->addScriptFile( "{$wgScriptPath}/js2/js2stopgap.js" );
			$wgOut->addExtensionStyle( "$basePath/jquery/jquery-ui-1.7.2.css" );
		}
		
		$wgOut->addScriptFile( "$basePath/jquery/jquery.autogrow.js" );
		
		$wgOut->addScriptFile( "$basePath/lqt.js" );
		$wgOut->addExtensionStyle( "$basePath/lqt.css?{$wgStyleVersion}" );
		$wgOut->addScriptFile( "{$wgStylePath}/common/preview.js" );
		
		self::$stylesAndScriptsDone = true;
	}
	
	static function exportJSLocalisation() {
		wfLoadExtensionMessages( 'LiquidThreads' );
		
		$messages = array(
				'lqt-quote-intro',
				'lqt-quote',
				'lqt-ajax-updated',
				'lqt-ajax-update-link',
				'watch',
				'unwatch',
				'lqt-thread-link-url',
				'lqt-thread-link-title',
				'lqt-thread-link-copy',
				'lqt-sign-not-necessary',
				'lqt-marked-as-read-placeholder',
				'lqt-email-undo',
			);
				
		$data = array();
		
		foreach ( $messages as $msg ) {
			$data[$msg] = wfMsgNoTrans( $msg );
		}
		
		return json_encode( $data );
	}

	/* @return False if the article and revision do not exist. The HTML of the page to
	 * display if it exists. Note that this impacts the state out OutputPage by adding
	 * all the other relevant parts of the parser output. If you don't want this, call
	 * $post->getParserOutput. */
	function showPostBody( $post, $oldid = null ) {
		global $wgOut;
		
		// Load compatibility layer for older versions
		if ( !( $post instanceof Article_LQT_Compat ) ) {
			wfWarn( "No article compatibility layer loaded, inefficiently duplicating information." );
			$post = new Article_LQT_Compat( $post->getTitle() );
		}
		
		$parserOutput = $post->getParserOutput( $oldid );
		$wgOut->addParserOutputNoText( $parserOutput );
		
		return $parserOutput->getText();
	}
	
	function showThreadToolbar( $thread ) {
		global $wgLang;
		
		$sk = $this->user->getSkin();
		$html = '';

		$headerParts = array();
		
		foreach ( $this->threadMajorCommands( $thread ) as $key => $cmd ) {
			$content = $this->contentForCommand( $cmd, false /* No icon divs */ );
			$headerParts[] = Xml::tags( 'li',
						array( 'class' => "lqt-command lqt-command-$key" ),
						$content );
		}
		
		// Drop-down menu
		$commands = $this->threadCommands( $thread );
		$menuHTML = Xml::tags( 'ul', array( 'class' => 'lqt-thread-toolbar-command-list' ),
					$this->listItemsForCommands( $commands ) );
		
		$triggerText =	Xml::tags( 'span', array( 'class' => 'lqt-thread-actions-icon' ),
					wfMsgHTML( 'lqt-menu-trigger' ) );
		$dropDownTrigger = Xml::tags( 'div',
				array( 'class' => 'lqt-thread-actions-trigger ' .
					'lqt-command-icon', 'style' => 'display: none;' ),
				$triggerText );
		
		if ( count($commands) ) {
			$headerParts[] = Xml::tags( 'li',
						array( 'class' => 'lqt-thread-toolbar-menu' ),
						$dropDownTrigger );
		}
		
		$html .= implode( ' ', $headerParts );
		
		$html = Xml::tags( 'ul', array( 'class' => 'lqt-thread-toolbar-commands' ), $html );
		$html .= Xml::tags( 'div', array( 'style' => 'clear: both; height: 0;' ), '&nbsp;' );
							
		$html = Xml::tags( 'div', array( 'class' => 'lqt-thread-toolbar' ), $html ) .
				$menuHTML;
		
		return $html;
	}

	function listItemsForCommands( $commands ) {
		$result = array();
		foreach ( $commands as $key => $command ) {
			$thisCommand = $this->contentForCommand( $command );
			
			$thisCommand = Xml::tags( 'li',
						array( 'class' => 'lqt-command lqt-command-' . $key ),
						$thisCommand );
			
			$result[] = $thisCommand;
		}
		return join( ' ', $result );
	}
	
	function contentForCommand( $command, $icon_divs = true ) {
		$label = $command['label'];
		$href = $command['href'];
		$enabled = $command['enabled'];
		$tooltip = isset( $command['tooltip'] ) ? $command['tooltip'] : '';
		
		if ( isset( $command['icon'] ) ) {
			global $wgScriptPath;
			$icon = Xml::tags( 'div', array( 'title' => $label,
					'class' => 'lqt-command-icon' ), '&nbsp;' );
			if ( $icon_divs ) {
				if ( !empty( $command['showlabel'] ) ) {
					$label = $icon . '&nbsp;' . $label;
				} else {
					$label = $icon;
				}
			} else {
				if ( empty( $command['showlabel'] ) ) {
					$label = '';
				}
			}
		}
		
		$thisCommand = '';
	
		if ( $enabled ) {
			$thisCommand = Xml::tags( 'a', array( 'href' => $href, 'title' => $tooltip ),
					$label );
		} else {
			$thisCommand = Xml::tags( 'span', array( 'class' => 'lqt_command_disabled',
						'title' => $tooltip ), $label );
		}
		
		return $thisCommand;
	}

	/** Shows a normal (i.e. not deleted or moved) thread body */
	function showThreadBody( $thread ) {
	
		// Remove 'editsection', it won't work.
		$popts = $this->output->parserOptions();
		$previous_editsection = $popts->getEditSection();
		$popts->setEditSection( false );
		$this->output->parserOptions( $popts );

		$post = $thread->root();
		
		$divClass = $this->postDivClass( $thread );
		$html = '';

		// This is a bit of a hack to have individual histories work.
		// We can grab oldid either from lqt_oldid (which is a thread rev),
		// or from oldid (which is a page rev). But oldid only applies to the
		// thread being requested, not any replies.
		$page_rev = $this->request->getVal( 'oldid', null );
		if ( $page_rev !== null && $this->title->equals( $thread->root()->getTitle() ) ) {
			$oldid = $page_rev;
		} else {
			$oldid = $thread->isHistorical() ? $thread->rootRevision() : null;
		}

		// If we're editing the thread, show the editing form.
		if ( $this->methodAppliesToThread( 'edit', $thread ) ) {
			$html = Xml::openElement( 'div', array( 'class' => $divClass ) );
			$this->output->addHTML( $html );
			$html = '';
			
			// No way am I refactoring EditForm to send its output as HTML.
			//  so I'm just flushing the HTML and displaying it as-is.
			$this->showPostEditingForm( $thread );
			$html .= Xml::closeElement( 'div' );
		} else {
			$html .= Xml::openElement( 'div', array( 'class' => $divClass ) );
			$html .= $this->showPostBody( $post, $oldid );
			$html .= Xml::closeElement( 'div' );
			$html .= $this->showThreadToolbar( $thread );
			$html .= $this->threadSignature( $thread );
		}
		
		// If we're replying to this thread, show the reply form after it.
		if ( $this->methodAppliesToThread( 'reply', $thread ) ) {
			// As with above, flush HTML to avoid refactoring EditPage.
			$this->output->addHTML( $html );
			$this->showReplyForm( $thread );
			$html = '';
		} else {
			$html .= Xml::tags( 'div',
					array( 'class' => 'lqt-reply-form lqt-edit-form',
						'style' => 'display: none;'  ),
					'' );
		}
		
		$this->output->addHTML( $html );

		$popts->setEditSection( $previous_editsection );
		$this->output->parserOptions( $popts );
	}
	
	function threadSignature( $thread ) {
		global $wgUser, $wgLang;
		$sk = $wgUser->getSkin();
		
		$author = $thread->author();
		
		$signature = $this->getSignature( $author );
		$signature = Xml::tags( 'span', array( 'class' => 'lqt-thread-user-signature' ),
					$signature );
					
 		$timestamp = $wgLang->timeanddate( $thread->created(), true );
		$signature .= Xml::element( 'span',
					array( 'class' => 'lqt-thread-toolbar-timestamp' ),
					$timestamp );
		
		$signature = Xml::tags( 'div', array( 'class' => 'lqt-thread-signature' ),
					$signature );
		
		return $signature;
	}
	
	function threadInfoPanel( $thread ) {
		global $wgUser, $wgLang;
		
		$sk = $wgUser->getSkin();
		
		$infoElements = array();
									
		// Check for edited flag.
		$editedFlag = $thread->editedness();
		$ebLookup = array( Threads::EDITED_BY_AUTHOR => 'author',
					Threads::EDITED_BY_OTHERS => 'others' );
		if ( isset( $ebLookup[$editedFlag] ) ) {

			$editedBy = $ebLookup[$editedFlag];
			$editedNotice = wfMsgExt( 'lqt-thread-edited-' . $editedBy, 'parseinline' );
			$infoElements[] = Xml::element( 'div', array( 'class' =>
						'lqt-thread-toolbar-edited-' . $editedBy ),
						$editedNotice );
		}
		
		if ( ! count($infoElements) ) {
			return '';
		}
		
		return Xml::tags( 'div', array( 'class' => 'lqt-thread-info-panel' ),
							implode( "\n", $infoElements ) );
	}

	/** Shows the headING for a thread (as opposed to the headeER for a post within
		a thread). */
	function showThreadHeading( $thread ) {
		if ( $thread->hasDistinctSubject() ) {
			if ( $thread->hasSuperthread() ) {
				$commands_html = "";
			} else {
				$commands = $this->topLevelThreadCommands( $thread );
				$lis = $this->listItemsForCommands( $commands );
				$id = 'lqt-threadlevel-commands-' . $thread->id();
				$commands_html = Xml::tags( 'ul',
						array( 'class' => 'lqt_threadlevel_commands',
							'id' => $id ),
						$lis );
			}
			
			$id = 'lqt-header-' . $thread->id();

			$html = $this->output->parseInline( $thread->subjectWithoutIncrement() );
			$html = Xml::tags( 'span', array( 'class' => 'mw-headline' ), $html );
			$html = Xml::tags( 'h' . $this->headerLevel,
					array( 'class' => 'lqt_header', 'id' => $id ),
					$html ) . $commands_html;
			
			return $html;
		}
		
		return '';
	}

	function postDivClass( $thread ) {
		$levelClass = 'lqt-thread-nest-' . $this->threadNestingLevel;
		$alternatingType = ( $this->threadNestingLevel % 2 ) ? 'odd' : 'even';
		$alternatingClass = "lqt-thread-$alternatingType";
		
		return "lqt_post $levelClass $alternatingClass";
	}

	static function anchorName( $thread ) {
		return $thread->getAnchorName();
	}
	
	// Display a moved thread
	function showMovedThread( $thread ) {
		global $wgLang;
		
		$sk = $this->user->getSkin();
	
		// Grab target thread
		if ( !$thread->title() ) {
			return; // Odd case: moved thread with no title?
		}
		
		$article = new Article( $thread->title() );
		$target = Title::newFromRedirect( $article->getContent() );
		$t_thread = Threads::withRoot( new Article( $target ) );
		
		// Grab data about the new post.
		$author = $thread->author();
		$sig = $sk->userLink( $author->getID(), $author->getName() ) .
			   $sk->userToolLinks( $author->getID(), $author->getName() );
		$newTalkpage = $thread->article()->getTitle();
			   
		$html =
			wfMsgExt( 'lqt_move_placeholder',
				array( 'parseinline', 'replaceafter' ),
				$sk->link( $target ),
				$sig,
				$wgLang->date( $thread->modified() ),
				$wgLang->time( $thread->modified() ),
				$sk->link($newTalkpage)
			);
		
		$this->output->addHTML( $html );
	}
	
	/** Shows a deleted thread. Returns true to show the thread body */
	function showDeletedThread( $thread ) {
		$sk = $this->user->getSkin();
		if ( $this->user->isAllowed( 'deletedhistory' ) ) {
			$this->output->addWikiMsg( 'lqt_thread_deleted_for_sysops' );
			return true;
		} else {
			$msg = wfMsgExt( 'lqt_thread_deleted', 'parseinline' );
			$msg = Xml::tags( 'em', null, $msg );
			$msg = Xml::tags( 'p', null, $msg );

			$this->output->addHTML( $msg );
			return false;
		}
	}
	
	// Shows a single thread, rather than a thread tree.
	function showSingleThread( $thread ) {
		wfLoadExtensionMessages( 'LiquidThreads' );
		
		$html = '';
	
		// If it's a 'moved' thread, show the placeholder
		if ( $thread->type() == Threads::TYPE_MOVED ) {
			$this->showMovedThread( $thread );
			return;
		} elseif ( $thread->type() == Threads::TYPE_DELETED ) {
			$res = $this->showDeletedThread( $thread );
			
			if ( !$res ) return;
		}
		
		$this->output->addHTML( $this->threadInfoPanel( $thread ) );
		
		if ( $thread->summary() ) {
			$html .= $this->getSummary( $thread );
		}
		
		// Unfortunately, I can't rewrite showRootPost() to pass back HTML
		//  as it would involve rewriting EditPage, which I do NOT intend to do.

		$this->output->addHTML( $html );
		
		$this->showThreadBody( $thread );
	
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
				
				if (!is_object( $walk_thread ) ) {
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
		$link .= Xml::hidden( 'lqt-thread-start-at', $i,
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
	
	static function threadContainsRepliesWithContent( $thread ) {
		$replies = $thread->replies();
		
		foreach( $replies as $reply ) {
			$content = '';
			if ( $reply->root() ) $content = $reply->root()->getContent();
			
			if ( trim($content) != '' ) {
				return true;
			}
			
			if ( self::threadContainsRepliesWithContent( $reply ) ) {
				return true;
			}
			
			if ( $reply->type() == Threads::TYPE_MOVED ) {
				return true;
			}
		}
		
		return false;
	}
	
	function showThreadReplies( $thread, $startAt, $maxCount, $showThreads,
			$cascadeOptions ) {
		$repliesClass = 'lqt-thread-replies lqt-thread-replies-' .
					$this->threadNestingLevel;
		$div = Xml::openElement( 'div', array( 'class' => $repliesClass ) );
		$this->output->addHTML( $div );
		
		$subthreadCount = count( $thread->subthreads() );
		$i = 0;
		$showCount = 0;
		$showThreads = true;
		
		$mustShowThreads = $cascadeOptions['mustShowThreads'];
		
		$replies = $thread->subthreads();
		usort( $replies, array( 'Thread', 'createdSortCallback' ) );
		
		foreach ( $replies as $st ) {
			++$i;
			
			// Only show undeleted threads that are above our 'startAt' index.
			$shown = false;
			if ( $st->type() != Threads::TYPE_DELETED &&
					$i >= $startAt &&
					$showThreads ) {
				if ( $showCount > $maxCount && $maxCount > 0 ) {
					// We've shown too many threads.
					$link = $this->getShowMore( $thread, $st, $i );
					
					$this->output->addHTML( $link );
					$showThreads = false;
					continue;
				}
				
				++$showCount;
				if ( $showCount == 1 ) {
					// There's a post sep before each reply group to
					//  separate from the parent thread.
					$this->output->addHTML(
						Xml::tags( 'div',
							array( 'class' => 'lqt-post-sep' ),
							'&nbsp;' ) );
				}
				
				$this->showThread( $st, $i, $subthreadCount, $cascadeOptions );
				$shown = true;
			}
			
			// Handle must-show threads.
			// FIXME this thread will be duplicated if somebody clicks the
			//  "show more" link (probably needs fixing in the JS)
			if ( $st->type() != Threads::TYPE_DELETED && !$shown &&
					array_key_exists( $st->id(), $mustShowThreads ) ) {
					
				$this->showThread( $st, $i, $subthreadCount, $cascadeOptions );
			}
		}
		
		$finishDiv = Xml::tags( 'div', array( 'class' => 'lqt-replies-finish' ),
			Xml::tags( 'div', array( 'class' => 'lqt-replies-finish-corner' ), '&nbsp;' ) );
		
		$this->output->addHTML( $finishDiv . Xml::CloseElement( 'div' ) );
	}

	function showThread( $thread, $levelNum = 1, $totalInLevel = 1,
			$options = array() ) {
		global $wgLang;
		
		// Safeguard
		if ( $thread->type() & Threads::TYPE_DELETED ) {
			return;
		}
		
		$this->threadNestingLevel++;
		
		// Figure out which threads *need* to be shown because they're involved in an
		//  operation
		$mustShowOption = array();
		if ( isset( $options['mustShowThreads'] ) ) {
			$mustShowOption = $options['mustShowThreads' ];
		}
		$mustShowThreads = $this->getMustShowThreads( $mustShowOption );
		
		// For cascading.
		$options['mustShowThreads'] = $mustShowThreads;
		
		// Don't show blank posts unless we have to
		$content = '';
		if ( $thread->root() ) $content = $thread->root()->getContent();
		if ( trim($content) == '' &&
			$thread->type() != Threads::TYPE_MOVED &&
			! self::threadContainsRepliesWithContent( $thread ) &&
			! array_key_exists( $thread->id(), $mustShowThreads ) ) {
			
			$this->threadNestingLevel--;
			return;
		}
		
		$sk = $this->user->getSkin();
		$html = '';

		$html .= Xml::element( 'a', array( 'name' => $this->anchorName( $thread ) ), ' ' );
		$html .= $this->showThreadHeading( $thread );
		
		$class = $this->threadDivClass( $thread );
		if ( $levelNum == 1 ) {
			$class .= ' lqt-thread-first';
		} elseif ( $levelNum == $totalInLevel ) {
			$class .= ' lqt-thread-last';
		}
		
		$html .= Xml::openElement( 'div',
						array( 'class' => $class,
						'id' => 'lqt_thread_id_' . $thread->id() ) );

		// Metadata stuck in the top of the lqt_thread div.
		// Modified time for topmost threads...
		if ( $thread->isTopmostThread() ) {
			$html .= Xml::hidden( 'lqt-thread-modified-' . $thread->id(),
						wfTimestamp( TS_MW, $thread->modified() ),
						array( 'id' => 'lqt-thread-modified-' . $thread->id(),
							'class' => 'lqt-thread-modified' ) );
		}
		
		// Add the thread's title
		$html .= Xml::hidden( 'lqt-thread-title-' . $thread->id(),
					$thread->title()->getPrefixedText(),
					array( 'id' => 'lqt-thread-title-' . $thread->id(),
						'class' => 'lqt-thread-title-metadata' ) );

		// Flush output to display thread
		$this->output->addHTML( $html );
		$this->output->addHTML( Xml::openElement( 'div',
					array( 'class' => 'lqt-post-wrapper' ) ) );
		$this->showSingleThread( $thread );
		$this->output->addHTML( Xml::closeElement( 'div' ) );

		// Check depth and count
		if ( isset( $options['maxDepth'] ) ) {
			$maxDepth = $options['maxDepth'];
		} else {
			$maxDepth = $this->user->getOption( 'lqtdisplaydepth' );
		}
		
		if ( isset( $options['maxCount'] ) ) {
			$maxCount = $options['maxCount'];
		} else {
			$maxCount = $this->user->getOption( 'lqtdisplaycount' );
		}
		
		if ( isset( $options['startAt'] ) ) {
			$startAt = $options['startAt'];
		} else {
			$startAt = 0;
		}
		
		$cascadeOptions = $options;
		unset( $cascadeOptions['startAt'] );
		
		$showThreads = ( $maxDepth == - 1 ) ||
				( $this->threadNestingLevel <= $maxDepth );
		
		$mustShowThreadIds = array_keys( $mustShowThreads );
		$subthreadIds = array_keys( $thread->replies() );
		$mustShowSubthreadIds = array_intersect( $mustShowThreadIds, $subthreadIds );
		
		$hasSubthreads = self::threadContainsRepliesWithContent( $thread );
		$hasSubthreads = $hasSubthreads || count( $mustShowSubthreadIds );
		
		// Show subthreads if one of the subthreads is on the must-show list
		$showThreads = $showThreads ||
			count( array_intersect(
				array_keys( $mustShowThreads ), array_keys( $thread->replies() )
			) );
		if ( $hasSubthreads && $showThreads ) {
			$this->showThreadReplies( $thread, $startAt, $maxCount, $showThreads,
				$cascadeOptions );
		} elseif ( $hasSubthreads && !$showThreads ) {
			// Add a "show subthreads" link.
			$link = $this->getShowReplies( $thread );
			
			$this->output->addHTML( $link );
			
			if ( $levelNum < $totalInLevel ) {
				$this->output->addHTML(
					Xml::tags( 'div', array( 'class' => 'lqt-post-sep' ), '&nbsp;' ) );
			}
		} elseif ( $levelNum < $totalInLevel ) {
			$this->output->addHTML(
				Xml::tags( 'div', array( 'class' => 'lqt-post-sep' ), '&nbsp;' ) );
		}

		if ( $this->threadNestingLevel == 1 ) {
			$finishDiv = Xml::tags( 'div', array( 'class' => 'lqt-replies-finish' ),
				Xml::tags( 'div', array( 'class' => 'lqt-replies-finish-corner' ), '&nbsp;' ) );
				
			$this->output->addHTML( $finishDiv );
		}

		$this->output->addHTML( Xml::closeElement( 'div' ) );
		
		$this->threadNestingLevel--;
	}
	
	function threadDivClass( $thread ) {
		$levelClass = 'lqt-thread-nest-' . $this->threadNestingLevel;
		$alternatingType = ( $this->threadNestingLevel % 2 ) ? 'odd' : 'even';
		$alternatingClass = "lqt-thread-$alternatingType";
		$topmostClass = $thread->isTopmostThread() ? ' lqt-thread-topmost' : '';
		
		return "lqt_thread $levelClass $alternatingClass$topmostClass";
	}

	function getSummary( $t ) {
		if ( !$t->summary() ) return;
		if ( !$t->summary()->getContent() ) return; // Blank summary
		wfLoadExtensionMessages( 'LiquidThreads' );
		global $wgUser;
		$sk = $wgUser->getSkin();
		
		$label = wfMsgExt( 'lqt_summary_label', 'parseinline' );
		$edit_text = wfMsgExt( 'edit', 'parseinline' );
		$link_text = wfMsg( 'lqt_permalink', 'parseinline' );
		
		$html = '';
		
		$html .= Xml::tags( 'span',
					array( 'class' => 'lqt_thread_permalink_summary_title' ),
					$label );
		
		$link = $sk->link( $t->summary()->getTitle(), $link_text );
		$edit_link = self::permalink( $t, $edit_text, 'summarize', $t->id() );
		$links = "[$link]\n[$edit_link]";
		$html .= Xml::tags( 'span', array( 'class' => 'lqt_thread_permalink_summary_edit' ),
				$links );
							
		$summary_body = $this->showPostBody( $t->summary() );
		$html .= Xml::tags( 'div', array( 'class' => 'lqt_thread_permalink_summary_body' ),
				$summary_body );
		
		$html = Xml::tags( 'div', array( 'class' => 'lqt_thread_permalink_summary' ), $html );
		
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
	
	function getBoringSignature( $user, $uid, $name ) {
		if ( isset( self::$boringSignatureCache[$name] ) ) {
			return self::$boringSignatureCache[$name];
		}
		
		$msg = ( $uid > 0 ) ? 'signature' : 'signature-anon';
		
		$sig = wfMsgExt( $msg, 'parseinline', array( $name, $name ) );
		
		self::$boringSignatureCache[$name] = $sig;
		
		return $sig;
	}
	
	function getUserSignature( $user, $uid, $name ) {
		if ( isset( self::$userSignatureCache[$name] ) ) {
			return self::$userSignatureCache[$name];
		}
		
		if ( !$user ) {
			$user = User::newFromId( $uid );
		}
		
		global $wgParser, $wgOut, $wgTitle;
		
		// Parser gets antsy about parser options here if it hasn't parsed anything before.
		$wgParser->clearState();
		$wgParser->setTitle( $wgTitle );
		$wgParser->mOptions = new ParserOptions;
		
		$sig = $wgParser->getUserSig( $user );
		$sig = $wgParser->preSaveTransform( $sig, $this->title, $user,
							$wgParser->mOptions, false );
		$sig = $wgOut->parseInline( $sig );
		
		self::$userSignatureCache[$name] = $sig;
		
		return $sig;
	}
}
