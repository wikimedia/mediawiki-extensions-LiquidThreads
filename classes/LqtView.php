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
	protected $article;
	protected $output;
	protected $user;
	protected $title;
	protected $request;

	protected $headerLevel = 2; 	/* h1, h2, h3, etc. */
	protected $maxIndentationLevel = 4;
	protected $lastUnindentedSuperthread;

	protected $threadNestingLevel = 0;

	protected $sort_order = LQT_NEWEST_CHANGES;

	function __construct( &$output, &$article, &$title, &$user, &$request ) {
		$this->article = $article;
		$this->output = $output;
		$this->user = $user;
		$this->title = $title;
		$this->request = $request;
		$this->user_colors = array();
		$this->user_color_index = 1;
	}

	function setHeaderLevel( $int ) {
		$this->headerLevel = $int;
	}

	static protected $occupied_titles = array();

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
		list ($title, $query) = self::permalinkData( $thread, $method, $operand );
		
		$query = array_merge( $query, $uquery );
		
		$queryString = wfArrayToCGI( $query );
		
		return $title->getFullUrl( $queryString );
	}
	
	/** Gets an array of (title, query-parameters) for a permalink **/
	static function permalinkData( $thread, $method = null, $operand = null ) {
		$query = array();
		
		if ($method) {
			$query['lqt_method'] = $method;
		}
		if ($operand) {
			$query['lqt_operand'] = $operand;
		}
		
		return array( $thread->root()->getTitle(), $query );
	}

	/* This is used for action=history so that the history tab works, which is
	   why we break the lqt_method paradigm. */
	static function permalinkUrlWithQuery( $thread, $query ) {
		if ( !is_array($query) ) {
			$query = wfCGIToArray( $query );
		}
		
		return self::permalinkUrl( $thread, null, null, $query );
	}
	
	static function permalink( $thread, $text = null, $method = null, $operand = null,
								$sk = null, $attribs = array(), $uquery = array() ) {
		if ( is_null($sk) ) {
			global $wgUser;
			$sk = $wgUser->getSkin();
		}
		
		list( $title, $query ) = self::permalinkData( $thread, $method, $operand );
		
		$query = array_merge( $query, $uquery );
		
		return $sk->link( $title, $text, $attribs, $query );
	}
	
	static function diffQuery( $thread ) {
		$changed_thread = $thread->changeObject();
		$curr_rev_id = $changed_thread->rootRevision();
		$curr_rev = Revision::newFromTitle( $changed_thread->root()->getTitle(), $curr_rev_id );
		$prev_rev = $curr_rev->getPrevious();
		$oldid = $prev_rev ? $prev_rev->getId() : "";
		
		$query = array( 'lqt_method' => 'diff',
						'diff' => $curr_rev_id,
						'oldid' => $oldid );
		
		return $query;
	}

	static function permalinkUrlWithDiff( $thread ) {
		$query = self::diffQuery( $thread );
		return self::permalinkUrl( $thread->changeObject(), null, null, $query );
	}
	
	static function diffPermalink( $thread, $text ) {
		$query = self::diffQuery( $thread );
		return self::permalink( $thread, $text, null, null, null, array(), $query );
	}
	
	static function talkpageLink( $title, $text = null , $method=null, $operand=null,
									$includeFragment=true, $attribs = array(),
									$options = array() ) {
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
		
		if ($method) {
			$query['lqt_method'] = $method;
		}
		
		if ($operand) {
			$query['lqt_operand'] = $operand->id();
		}
		
		$oldid = $wgRequest->getVal( 'oldid', null );
		
		if ( $oldid !== null ) {
			// this is an immensely ugly hack to make editing old revisions work.
			$query['oldid'] = $oldid;
		}
		
		// Add fragment if appropriate.
		if ($operand && $includeFragment) {
			$title->mFragment = 'lqt_thread_'.$operand->id();
		}
		
		return array( $title, $query );
	}

	static function talkpageUrl( $title, $method = null, $operand = null,
									$includeFragment = true ) {
		global $wgUser;
		$sk = $wgUser->getSkin();
		
		list( $title, $query ) =
			self::talkpageLinkData( $title, $method, $operand, $includeFragment );
		
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
		$this->showEditingFormInGeneral( null, 'summarize', $thread );
	}
	
	function doInlineEditForm() {
		$method = $this->request->getVal( 'lqt_method' );
		$operand = $this->request->getVal( 'lqt_operand' );
		
		$thread = Threads::withId( $operand );
		
		if ($method == 'reply') {
			$this->showReplyForm( $thread );
		} elseif ($method == 'talkpage_new_thread') {
			$this->showNewThreadForm();
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

		// Stuff that might break the save		
		$valid_subject = true;
		$failed_rename = false;
		
		$subject = $this->request->getVal( 'lqt_subject_field', '' );
		
		if ( $edit_type == 'summarize' && $edit_applies_to->summary() ) {
			$article = $edit_applies_to->summary();
		} elseif ( $edit_type == 'summarize' ) {
			$t = $this->newSummaryTitle( $edit_applies_to );
			$article = new Article( $t );
		} elseif ( $thread == null ) {
			if ( $subject && is_null( Title::makeTitleSafe( NS_LQT_THREAD, $subject ) ) ) {
				// Dodgy title
				$valid_subject = false;
				$t = $this->scratchTitle();
			} else {			
				if ( $edit_type == 'new' ) {
					$t = $this->newScratchTitle( $subject );
				} elseif ( $edit_type == 'reply' ) {
					$t = $this->newReplyTitle( $subject, $edit_applies_to );
				}
			}
			$article = new Article( $t );
		} else {
			$article = $thread->root();
		}

		$e = new EditPage( $article );
		
		
		// Find errors.
		if (!$valid_subject && $subject) {
			$e->editFormPageTop .= 
				Xml::tags( 'div', array( 'class' => 'error' ),
					wfMsgExt( 'lqt_invalid_subject', 'parse' ) );
		}
		
		if ( $subject && $thread && $subject != $thread->subjectWithoutIncrement() &&
				!$this->user->isAllowed( 'move' ) ) {
			$e->editFormPageTop .= 
				Xml::tags( 'div', array( 'class' => 'error' ),
					wfMsgExt( 'lqt_subject_change_forbidden', 'parse' ) );
			$failed_rename = true;
			
			// Reset the subject
			global $wgRequest;
			$wgRequest->setVal( 'lqt_subject_field', $thread->subjectWithoutIncrement() ); 
		}
		
		if ( (!$valid_subject && $subject) || $failed_rename ) {
			// Dirty hack to prevent saving from going ahead
			global $wgRequest;
			$wgRequest->setVal( 'wpPreview', true );
		}

		$e->suppressIntro = true;
		$e->editFormTextBeforeContent .=
			$this->perpetuate( 'lqt_method', 'hidden' ) .
			$this->perpetuate( 'lqt_operand', 'hidden' ) .
			Xml::hidden( 'lqt_nonce', wfGenerateToken() );
			
		// Add a one-time random string to a hidden field. Store the random string
		//  in memcached on submit and don't allow the edit to go ahead if it's already
		//  been added.
		$submitted_nonce = $this->request->getVal( 'lqt_nonce' );
		if ($submitted_nonce) {
			global $wgMemc;
			
			$key = wfMemcKey( 'lqt-nonce', $submitted_nonce, $this->user->getName() );
			if ( $wgMemc->get($key) ) {
				$this->output->redirect( $this->article->getTitle()->getFullURL() );
				return;
			}
			
			$wgMemc->set( $key, 1, 3600 );
		}

		if ( $edit_type == 'new' || ( $thread && !$thread->hasSuperthread() ) ) {
			wfLoadExtensionMessages( 'LiquidThreads' );
			// This is a top-level post; show the subject line.
			$db_subject = $thread ? $thread->subjectWithoutIncrement() : '';
			$subject = $this->request->getVal( 'lqt_subject_field', $db_subject );
			$subject_label = wfMsg( 'lqt_subject' );
			
			$attr = array( 'tabindex' => 1 );
			if ( $thread && !$this->user->isAllowed( 'move' ) ) {
				$attr['readonly'] = 'readonly';
			}
			
			$e->editFormTextBeforeContent .=
				Xml::inputLabel( $subject_label, 'lqt_subject_field', 'lqt_subject_field',
					60, $subject, $attr ) . Xml::element( 'br' );
		}

		$e->edit();

		// Override what happens in EditPage::showEditForm, called from $e->edit():

		$this->output->setArticleFlag( false );
		
		if ( $e->didSave ) {
			$thread = self::postEditUpdates( $edit_type, $edit_applies_to, $article, $this->article,
									$subject, $e->summary, $thread );
		}

		// A redirect without $e->didSave will happen if the new text is blank (EditPage::attemptSave).
		// This results in a new Thread object not being created for replies and new discussions,
		// so $thread is null. In that case, just allow editpage to redirect back to the talk page.
		if ( $this->output->getRedirect() != '' && $thread ) {
			$redirectTitle = clone $thread->article()->getTitle();
			$redirectTitle->setFragment( '#'.$this->anchorName( $thread ) );
			$this->output->redirect( $this->title->getFullURL() );
		} else if ( $this->output->getRedirect() != '' && $edit_applies_to ) {
			// For summaries:
			$redirectTitle = clone $edit_applies_to->article()->title();
			$redirectTitle->setFragment( '#'.$this->anchorName( $edit_applies_to ) );
			$this->output->redirect( $redirectTitle->getFullURL() );
		}
	}
	
	static function postEditUpdates($edit_type, $edit_applies_to, $edit_page, $article,
									$subject, $edit_summary, $thread ) {
		// For replies and new posts, insert the associated thread object into the DB.
		if ( $edit_type == 'reply' ) {
			$subject = $edit_applies_to->subject();
			
			$thread = Threads::newThread( $edit_page, $article, $edit_applies_to,
											Threads::TYPE_NORMAL, $subject );
			
			$edit_applies_to->commitRevision( Threads::CHANGE_REPLY_CREATED, $thread,
												$edit_summary );
		} elseif ( $edit_type == 'summarize' ) {
			$edit_applies_to->setSummary( $article );
			$edit_applies_to->commitRevision( Threads::CHANGE_EDITED_SUMMARY,
												$edit_applies_to, $edit_summary );
		} elseif ( $edit_type == 'editExisting' ) {
			// Move the thread and replies if subject changed.
			if ( $subject && $subject != $thread->subjectWithoutIncrement() ) {
				$thread->setSubject( $subject );
				
				// Disabled page-moving for now.
				// $this->renameThread( $thread, $subject, $e->summary );
			}
			
			// For all edits, bump the version number.
			$thread->setRootRevision( Revision::newFromTitle( $thread->root()->getTitle() ) );
			$thread->commitRevision( Threads::CHANGE_EDITED_ROOT, $thread, $edit_summary );
		} else {
			$thread = Threads::newThread( $edit_page, $article, null,
											Threads::TYPE_NORMAL, $subject );
			// Commented-out for now. History needs fixing.
// 			// Commit the first revision
// 			$thread->commitRevision( Threads::CHANGE_NEW_THREAD, $thread,
// 										$edit_summary );
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

	function scratchTitle() {
		$token = md5( uniqid( rand(), true ) );
		return Title::newFromText( "Thread:$token" );
	}
	
	function newScratchTitle( $subject ) {
		wfLoadExtensionMessages( 'LiquidThreads' );
		$subject = $subject ? $subject : wfMsg( 'lqt_nosubject' );
		
		$base = $this->article->getTitle()->getPrefixedText() . "/$subject";
		
		return $this->incrementedTitle( $base, NS_LQT_THREAD );
	}
	
	function newSummaryTitle( $t ) {
		return $this->incrementedTitle( $t->title()->getText(), NS_LQT_SUMMARY );
	}
	
	function newReplyTitle( $s, $t ) {
		$topThread = $t->topMostThread();
		
		$base = $t->title()->getText() . '/' . $this->user->getName();
		
		return $this->incrementedTitle( $base, NS_LQT_THREAD );
	}
	
	/** Keep trying titles starting with $basename until one is unoccupied. */
	public static function incrementedTitle( $basename, $namespace ) {
		$i = 2;
		
		$t = Title::makeTitleSafe( $namespace, $basename );
		while ( $t->exists() ||
				in_array( $t->getPrefixedDBkey(), self::$occupied_titles ) ) {
			$t = Title::makeTitleSafe( $namespace, $basename . ' (' . $i . ')' );
			$i++;
		}
		return $t;
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

		$user_can_edit = $thread->root()->getTitle()->quickUserCan( 'edit' );
		$editMsg = $user_can_edit ? 'edit' : 'viewsource';

		$commands['edit'] = array( 'label' => wfMsgExt( $editMsg, 'parseinline' ),
		                     'href' => $this->talkpageUrl( $this->title, 'edit', $thread ),
		                     'enabled' => true );

		$history_url = self::permalinkUrlWithQuery( $thread, array( 'action' => 'history' ) );
		$commands['history'] = array( 'label' => wfMsgExt( 'history_short', 'parseinline' ),
							 'href' => $history_url,
							 'enabled' => true );

		if ( $this->user->isAllowed( 'delete' ) ) {
			$threadText = $thread->title()->getPrefixedText();
			$deleteTitle = SpecialPage::getTitleFor( 'DeleteThread', $threadText );
			$delete_url = $deleteTitle->getFullURL();
			$deleteMsg = $thread->type() == Threads::TYPE_DELETED ? 'lqt_undelete' : 'delete';
				
			$commands['delete'] = array( 'label' => wfMsgExt( $deleteMsg, 'parseinline' ),
								 'href' => $delete_url,
								 'enabled' => true );
		}
		
		if ( !$thread->isTopmostThread() ) {
			$splitUrl = SpecialPage::getTitleFor( 'SplitThread',
							$thread->title()->getPrefixedText() )->getFullURL();
			$commands['split'] = array( 'label' => wfMsgExt( 'lqt-thread-split', 'parseinline' ),
										'href' => $splitUrl, 'enabled' => true );
		}

		return $commands;
	}
	
	// Commands for the bottom.
	function threadFooterCommands( $thread ) {
		wfLoadExtensionMessages( 'LiquidThreads' );
		
		$commands = array();
		
		$commands['reply'] = array( 'label' => wfMsgExt( 'lqt_reply', 'parseinline' ),
							 'href' =>  $this->talkpageUrl( $this->title, 'reply', $thread ),
							 'enabled' => true,
							 'icon' => 'reply.png');
		
		return $commands;
	}

	function topLevelThreadCommands( $thread ) {
		wfLoadExtensionMessages( 'LiquidThreads' );
		$commands = array();

		$commands['history'] = array( 'label' => wfMsg( 'history_short' ),
		                     'href' => self::permalinkUrl( $thread, 'thread_history' ),
		                     'enabled' => true );

		if ( in_array( 'move', $this->user->getRights() ) ) {
			$move_href = SpecialPage::getTitleFor( 'MoveThread' )->getFullURL()
				. '/' . $thread->title()->getPrefixedURL();
			$commands['move'] = array( 'label' => wfMsg( 'move' ),
			                     'href' => $move_href,
			                     'enabled' => true );
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
		
		if ( $this->user->isAllowed( 'delete' ) ) {
			$delete_title = SpecialPage::getTitleFor( 'DeleteThread',
								$thread->title()->getPrefixedText() );
			$delete_href = $delete_title->getFullURL();
			
			$commands['delete'] = array( 'label' => wfMsg( 'delete' ),
									'href' => $delete_href,
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
		// Changed this to be static so that we can call it from
		// wfLqtBeforeWatchlistHook.
		global $wgOut;
		global $wgScriptPath, $wgStyleVersion;

		$wgOut->addInlineScript( 'var wgLqtMessages = ' . self::exportJSLocalisation() . ';' );
		$wgOut->addScriptFile( "{$wgScriptPath}/extensions/LiquidThreads/lqt.js" );
		$wgOut->addExtensionStyle( "{$wgScriptPath}/extensions/LiquidThreads/lqt.css?{$wgStyleVersion}" );
	}
	
	static function exportJSLocalisation() {
		wfLoadExtensionMessages( 'LiquidThreads' );
		
		$messages = array( 'lqt-quote-intro', 'lqt-quote' );
		$data = array();
		
		foreach( $messages as $msg ) {
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
		
		$parserOutput = $post->getParserOutput( $oldid );
		$wgOut->addParserOutputNoText( $parserOutput );
		
		return $parserOutput->getText();
	}
	
	function showThreadHeader( $thread ) {
		global $wgLang;
		
		$sk = $this->user->getSkin();
		$html = '';
		
		/// RHS, actions. Show as a drop-down, goes first in the HTML so it floats correctly.
		$commands = $this->threadCommands( $thread );
		$commandHTML = Xml::tags( 'ul', array( 'class' => 'lqt-thread-header-command-list' ),
									$this->listItemsForCommands( $commands ) );

		$headerParts = array();
		
		$permalink = $this->permalink( $thread, wfMsgExt( 'lqt_permalink', 'parseinline' ) );
		$permalink = Xml::tags( 'span', array( 'class' => 'lqt-thread-permalink' ), $permalink );
		$headerParts[] = $permalink;
		
		// Drop-down menu
		$triggerText =	wfMsgExt( 'lqt-header-actions', 'parseinline' ) .
						Xml::tags( 'span', array('class' => 'lqt-thread-actions-icon'),
										'&nbsp;');
		$dropDownTrigger = Xml::tags( 	'span',
										array( 'class' => 'lqt-thread-actions-trigger' ),
										$triggerText );
		$headerParts[] = Xml::tags( 'div',
									array( 'class' => 'lqt-thread-header-commands' ),
									$dropDownTrigger . $commandHTML );
		
		$dropDown = Xml::tags( 'div',
								array( 'class' => 'lqt-thread-header-rhs' ),
								$wgLang->pipeList( $headerParts ) );
		$html .= $dropDown;
		
		$infoElements = array();
		
		// Author name.
		$author = $thread->author();
		$signature = $sk->userLink( $author->getId(), $author->getName() );
		$signature = Xml::tags( 'span', array( 'class' => 'lqt-thread-header-author' ),
								$signature );
		$signature .= $sk->userToolLinks( $author->getId(), $author->getName() );
		$infoElements[] = Xml::tags( 'span', array( 'class' => 'lqt-thread-header-signature' ),
								$signature );
		
		$timestamp = $wgLang->timeanddate( $thread->created(), true );
		$infoElements[] = Xml::element( 'span', array( 'class' => 'lqt-thread-header-timestamp' ),
									$timestamp );
									
		// Check for edited flag.
		$editedFlag = $thread->editedness();		
		$ebLookup = array( Threads::EDITED_BY_AUTHOR => 'author',
							Threads::EDITED_BY_OTHERS => 'others' );
		if ( isset( $ebLookup[$editedFlag] ) ) {

			$editedBy = $ebLookup[$editedFlag];
			$editedNotice = wfMsgExt( 'lqt-thread-edited-'.$editedBy, 'parseinline' );
			$infoElements[] = Xml::element( 'span', array( 'class' =>
											'lqt-thread-header-edited-'.$editedBy ),
											$editedNotice );
		}
		
		$html .= Xml::tags( 'span', array( 'class' => 'lqt-thread-header-info' ),
							$wgLang->pipeList( $infoElements ) );
							
		// Fix the floating elements by adding a clear.
		$html .= Xml::tags( 'span', array( 'style' => 'clear: both;' ), '&nbsp;' );
							
		$html = Xml::tags( 'div', array( 'class' => 'lqt-thread-header' ), $html );
		
		return $html;
	}

	function showThreadFooter( $thread ) {
		global $wgLang, $wgUser;
		
		$sk = $wgUser->getSkin();
		$html = '';

		// Footer commands
		$footerCommands =
			$this->listItemsForCommands( $this->threadFooterCommands( $thread ) );
		$html .=
			Xml::tags( 'span', array( 'class' => "lqt_footer_commands" ), $footerCommands );

		$html = Xml::tags( 'ul', array( 'class' => 'lqt_footer' ), $html );
		
		return $html;
	}

	function listItemsForCommands( $commands ) {
		$result = array();
		foreach ( $commands as $key => $command ) {
			$label = $command['label'];
			$href = $command['href'];
			$enabled = $command['enabled'];
			
			if ( isset( $command['icon'] ) ) {
				global $wgScriptPath;
				$src = $wgScriptPath . '/extensions/LiquidThreads/icons/'.$command['icon'];
				$icon = Xml::element( 'img', array( 'src' => $src,
													'alt' => $label,
													'class' => 'lqt-command-icon' ) );
				$label = $icon.'&nbsp;'.$label;
			}
			
			$thisCommand = '';

			if ( $enabled ) {
				$thisCommand = Xml::tags( 'a', array( 'href' => $href ), $label );
			} else {
				$thisCommand = Xml::tags( 'span', array( 'class' => 'lqt_command_disabled' ),
											$label );
			}
			
			$thisCommand = Xml::tags( 	'li',
										array( 'class' => 'lqt-command lqt-command-'.$key ),
										$thisCommand );
			
			$result[] = $thisCommand;
		}
		return join( ' ', $result );
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
		// thread being requested, not any replies.  TODO: eliminate the need
		// for article-level histories.
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
			$html .= $this->showThreadHeader( $thread );
			$html .= $this->getReplyContext( $thread );
			$html .= Xml::openElement( 'div', array( 'class' => $divClass ) );
			$html .= $this->showPostBody( $post, $oldid );
			$html .= Xml::closeElement( 'div' );
			$html .= $this->showThreadFooter( $thread );
		}

		// wish I didn't have to use this open/closeElement cruft.
		
		
		
		// If we're replying to this thread, show the reply form after it.
		if ( $this->methodAppliesToThread( 'reply', $thread ) ) {
			// As with above, flush HTML to avoid refactoring EditPage.
			$html .= $this->indent( $thread );
			$this->output->addHTML( $html );
			$this->showReplyForm( $thread );
			$html = $this->unindent( $thread );
		}
		
		$this->output->addHTML( $html );

		$popts->setEditSection( $previous_editsection );
		$this->output->parserOptions( $popts );
	}

	/** Shows the headING for a thread (as opposed to the headeER for a post within
		a thread). */
	function showThreadHeading( $thread ) {
		if ( $thread->hasDistinctSubject() ) {
			if ( $thread->hasSuperthread() ) {
				$commands_html = "";
			} else {
				$lis = $this->listItemsForCommands( $this->topLevelThreadCommands( $thread ) );
				$commands_html = Xml::tags( 'ul',
											array( 'class' => 'lqt_threadlevel_commands' ),
											$lis );
			}

			$html = $this->output->parseInline( $thread->subjectWithoutIncrement() );
			$html = Xml::tags( 'span', array( 'class' => 'mw-headline' ), $html );
			$html = Xml::tags( 'h'.$this->headerLevel, array( 'class' => 'lqt_header' ),
								$html ) . $commands_html;
			
			return $html;
		}
		
		return '';
	}

	function postDivClass( $thread ) {
		$levelClass = 'lqt-thread-nest-'.$this->threadNestingLevel;
		$alternatingType = ($this->threadNestingLevel % 2) ? 'odd' : 'even';
		$alternatingClass = "lqt-thread-$alternatingType";
		
		return "lqt_post $levelClass $alternatingClass";
	}

	static function anchorName( $thread ) {
		return $thread->getAnchorName();
	}
	
	// Gets HTML for the 'in reply to' thing if warranted.
	function getReplyContext( $thread ) {
		if ( $this->lastUnindentedSuperthread ) {
			wfLoadExtensionMessages( 'LiquidThreads' );
			$tmp = $this->lastUnindentedSuperthread;
			$replyLink = Xml::tags( 'a', array( 'href' => '#'.$this->anchorName( $tmp ) ),
									$tmp->subject() );
			$msg = wfMsgExt( 'lqt_in_response_to', array( 'parseinline', 'replaceafter' ),
				array( $replyLink, $tmp->author()->getName() ) );
				
			return Xml::tags( 'span', array( 'class' => 'lqt_nonindent_message' ),
								"&larr; $msg" );
		}
		
		return '';
	}
	
	// Display a moved thread
	function showMovedThread( $thread ) {
		global $wgLang;
		
		$sk = $this->user->getSkin();
	
		// Grab target thread
		$article = new Article( $thread->title() );
		$target = Title::newFromRedirect( $article->getContent() );
		$t_thread = Threads::withRoot( new Article( $target ) );
		
		// Grab data about the new post.
		$author = $thread->author();
		$sig = $sk->userLink( $author->getID(), $author->getName() ) .
			   $sk->userToolLinks( $author->getID(), $author->getName() );
			   
		$html =
			wfMsgExt( 'lqt_move_placeholder', array( 'parseinline', 'replaceafter' ),
				$sk->link( $target ),
				$sig,
				$wgLang->date( $thread->modified() ),
				$wgLang->time( $thread->modified() )
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
			
			if (!$res) return;
		}
		
		if ( $thread->summary() ) {
			$html .= $this->getSummary( $thread );
		} elseif ( $thread->isArchiveEligible() ) {
			wfLoadExtensionMessages( 'LiquidThreads' );
			
			$permalink_text = wfMsgNoTrans( 'lqt_summary_notice_link' );
			$permalink = self::permalink( $thread, $permalink_text, 'summarize',
											$thread->id() );
			$msg = wfMsgExt( 'lqt_summary_notice', array('parseinline', 'replaceafter'),
								array( $permalink, $thread->getArchiveStartDays() ) );
			$msg = Xml::tags( 'p', array( 'class' => 'lqt_summary_notice' ), $msg );
			
			$html .= $msg;
		}
		
		// Unfortunately, I can't rewrite showRootPost() to pass back HTML
		//  as it would involve rewriting EditPage, which I do NOT intend to do.

		$this->output->addHTML( $html );
		
		$this->showThreadBody( $thread );
	
	}

	function showThread( $thread ) {
		global $wgLang;
		
		$this->threadNestingLevel++;
		
		// Safeguard
		if ( $thread->type() == Threads::TYPE_DELETED
			&& ! ($this->request->getBool( 'lqt_show_deleted_threads' )
				&& $this->user->isAllowed( 'deletedhistory' ) ) ) {
			return;
		}
		
		$sk = $this->user->getSkin();
		
		$html = '';

		$html .= Xml::element( 'a', array( 'name' => $this->anchorName($thread) ), ' ' );
		$html .= $this->showThreadHeading( $thread );
		
		// Sigh.
		$html .= Xml::openElement( 'div', array( 'class' => $this->threadDivClass( $thread ),
									'id' => 'lqt_thread_id_'. $thread->id() ) );

		// Flush output to display thread
		$this->output->addHTML( $html );
		
		$this->showSingleThread( $thread );

		if ( $thread->hasSubthreads() ) {
			foreach ( $thread->subthreads() as $st ) {
				$this->showThread( $st );
			}
		}

		$this->output->addHTML( Xml::closeElement( 'div' ) );
		
		$this->threadNestingLevel--;
	}
	
	function threadDivClass( $thread ) {
		$levelClass = 'lqt-thread-nest-'.$this->threadNestingLevel;
		$alternatingType = ($this->threadNestingLevel % 2) ? 'odd' : 'even';
		$alternatingClass = "lqt-thread-$alternatingType";
		
		return "lqt_thread $levelClass $alternatingClass";
	}

	// FIXME does indentation need rethinking?
	function indent( $thread ) {
		$result = '';
		if ( $this->headerLevel <= $this->maxIndentationLevel ) {
			$result = '<dl class="lqt_replies"><dd>';
		} else {
			$result = '<div class="lqt_replies_without_indent">';
		}
		$this->lastUnindentedSuperthread = null;
		$this->headerLevel += 1;
		
		return $result;
	}
	
	function unindent( $thread ) {
		$result = '';
		if ( $this->headerLevel <= $this->maxIndentationLevel + 1 ) {
			$result = '</dd></dl>';
		} else {
			$result = '</div>';
		}
		// See the beginning of showThread().
		$this->lastUnindentedSuperthread = $thread->superthread();
		$this->headerLevel -= 1;
		
		return $result;
	}

	function getSummary( $t ) {
		if ( !$t->summary() ) return;
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
}
