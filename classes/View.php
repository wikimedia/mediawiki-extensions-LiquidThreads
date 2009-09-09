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
	
	static function diffQuery( $thread, $revision ) {
		$changed_thread = $revision->getChangeObject();
		$curr_rev_id = $revision->getThreadObj()->rootRevision();
		$curr_rev = Revision::newFromId( $curr_rev_id );
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
	
	static function diffPermalink( $thread, $text, $revision ) {
		$query = self::diffQuery( $thread, $revision );
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
		$this->showEditingFormInGeneral( $thread, 'summarize', $thread );
	}
	
	function doInlineEditForm() {
		$method = $this->request->getVal( 'lqt_method' );
		$operand = $this->request->getVal( 'lqt_operand' );
		
		$thread = Threads::withId( intval($operand) );
		
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

		if ( $edit_type == 'new' ) {
			wfLoadExtensionMessages( 'LiquidThreads' );
			// This is a top-level post; show the subject line.
			$db_subject = $thread ? $thread->subjectWithoutIncrement() : '';
			$subject = $this->request->getVal( 'lqt_subject_field', $db_subject );
			$subject_label = wfMsg( 'lqt_subject' );
			
			$attr = array( 'tabindex' => 1 );
			
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
			$redirectTitle = clone $edit_applies_to->article()->getTitle();
			$redirectTitle->setFragment( '#'.$this->anchorName( $edit_applies_to ) );
			$this->output->redirect( $redirectTitle->getFullURL() );
		}
	}
	
	static function postEditUpdates($edit_type, $edit_applies_to, $edit_page, $article,
									$subject, $edit_summary, $thread ) {
		// Update metadata - create and update thread and thread revision objects as
		//  appropriate.
		
		if ( $edit_type == 'reply' ) {
			$subject = $edit_applies_to->subject();
			
			$thread = Threads::newThread( $edit_page, $article, $edit_applies_to,
											Threads::TYPE_NORMAL, $subject );
		} elseif ( $edit_type == 'summarize' ) {
			$edit_applies_to->setSummary( $edit_page );
			$edit_applies_to->commitRevision( Threads::CHANGE_EDITED_SUMMARY,
												$edit_applies_to, $edit_summary );
		} elseif ( $edit_type == 'editExisting' ) {
			// Move the thread and replies if subject changed.
			if ( $subject && $subject != $thread->subjectWithoutIncrement() ) {
				$thread->setSubject( $subject );
				
				// Disabled page-moving for now.
				// $this->renameThread( $thread, $subject, $e->summary );
			}
			
			// Add the history entry.
			$thread->commitRevision( Threads::CHANGE_EDITED_ROOT, $thread, $edit_summary );
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

	function scratchTitle() {
		return Threads::scratchTitle();
	}
	
	function newScratchTitle( $subject ) {
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
			$delete_url = $thread->title()->getFullURL( 'action=delete');
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
			
			unset($mergeParams['title']);
			
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
		
		if ($thread->isHistorical() ) {
			// No links for historical threads.
			return array();
		}
		
		$commands = array();
		
		if ( $this->user->isAllowed( 'lqt-merge' ) &&
				$this->request->getCheck( 'lqt_merge_from' ) ) {
			$srcThread = Threads::withId( $this->request->getVal( 'lqt_merge_from' ) );
			$par = $srcThread->title()->getPrefixedText();
			$mergeTitle = SpecialPage::getTitleFor( 'MergeThread', $par );
			$mergeUrl = $mergeTitle->getFullURL( 'dest='.$thread->id() );
			$label = wfMsgExt( 'lqt-thread-merge-to', 'parseinline' );
			
			$commands['merge-to'] = array( 'label' => $label, 'href' => $mergeUrl,
											'enabled' => true );
		}
		
		$commands['reply'] = array( 'label' => wfMsgExt( 'lqt_reply', 'parseinline' ),
							 'href' => $this->talkpageUrl( $this->title, 'reply', $thread ),
							 'enabled' => true, 'icon' => 'reply.png', 'showlabel' => 1);
		
		$commands['link'] = array( 'label' => wfMsgExt( 'lqt_permalink', 'parseinline' ),
							'href' => $thread->title()->getFullURL(),
							'enabled' => true, 'icon' => 'link.png' );
		
		if ( $thread->root()->getTitle()->quickUserCan( 'edit' ) ) {
			$commands['edit'] = array( 'label' => wfMsgExt( 'edit', 'parseinline' ),
								'href' => $this->talkpageUrl( $this->title, 'edit', $thread ),
								'enabled' => true, 'icon' => 'edit.png' );
		}
		
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
		
		if ( self::$stylesAndScriptsDone ) {
			return;
		}
		
		global $wgOut;
		global $wgScriptPath, $wgStyleVersion;
		global $wgEnableJS2system;

		$wgOut->addInlineScript( 'var wgLqtMessages = ' . self::exportJSLocalisation() . ';' );
		
		if (!$wgEnableJS2system) {
			$wgOut->addScriptFile( "{$wgScriptPath}/extensions/LiquidThreads/js2.combined.js" );
		}
		
		$wgOut->addScriptFile( "{$wgScriptPath}/extensions/LiquidThreads/lqt.js" );
		$wgOut->addExtensionStyle( "{$wgScriptPath}/extensions/LiquidThreads/lqt.css?{$wgStyleVersion}" );
		
		self::$stylesAndScriptsDone = true;
	}
	
	static function exportJSLocalisation() {
		wfLoadExtensionMessages( 'LiquidThreads' );
		
		$messages = array( 'lqt-quote-intro', 'lqt-quote', 'lqt-ajax-updated',
							'lqt-ajax-update-link' );
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
		
		// Load compatibility layer for older versions
		if ( !($post instanceof Article_LQT_Compat) ) {
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
		
		foreach( $this->threadMajorCommands( $thread ) as $key => $cmd ) {
			$content = $this->contentForCommand( $cmd, false /* No icon divs */ );
			$headerParts[] = Xml::tags( 'li',
										array( 'class' => "lqt-command lqt-command-$key" ),
										$content );
		}
		
		// Drop-down menu
		$commands = $this->threadCommands( $thread );
		$menuHTML = Xml::tags( 'ul', array( 'class' => 'lqt-thread-toolbar-command-list'),
									$this->listItemsForCommands( $commands ) );
									
		$triggerText =	Xml::tags( 'span', array('class' => 'lqt-thread-actions-icon'),
										'&nbsp;');
		$dropDownTrigger = Xml::tags( 'div',
										array( 'class' => 'lqt-thread-actions-trigger '.
											'lqt-command-icon', 'style' => 'display: none;'),
										$triggerText );
		$headerParts[] = Xml::tags( 'li',
									array( 'class' => 'lqt-thread-toolbar-menu' ),
									$dropDownTrigger );
							
		$html .= implode( ' ', $headerParts );
		
		$html = Xml::tags( 'ul', array( 'class' => 'lqt-thread-toolbar-commands' ), $html );
		$html .= Xml::tags( 'div', array( 'style' => 'clear: both; height: 0;' ), '&nbsp;' );
		
		// Box stuff
		$boxElements = array( 'lqt-thread-toolbar-box-tl', 'lqt-thread-toolbar-box-tr',
								'lqt-thread-toolbar-box-br', 'lqt-thread-toolbar-box-bl' );
		foreach( $boxElements as $class ) {
			$html = Xml::openElement( 'div', array( 'class' => $class ) ) . $html .
				Xml::closeElement( 'div' );
		}
							
		$html = Xml::tags( 'div', array( 'class' => 'lqt-thread-toolbar' ), $html ) .
				$menuHTML;
		
		return $html;
	}

	function listItemsForCommands( $commands ) {
		$result = array();
		foreach ( $commands as $key => $command ) {
			$thisCommand = $this->contentForCommand( $command );
			
			$thisCommand = Xml::tags( 	'li',
										array( 'class' => 'lqt-command lqt-command-'.$key ),
										$thisCommand );
			
			$result[] = $thisCommand;
		}
		return join( ' ', $result );
	}
	
	function contentForCommand( $command, $icon_divs = true ) {
		$label = $command['label'];
		$href = $command['href'];
		$enabled = $command['enabled'];
		
		if ( isset( $command['icon'] ) ) {
			global $wgScriptPath;
			$icon = Xml::tags( 'div', array( 'title' => $label,
												'class' => 'lqt-command-icon' ), '&nbsp;' );
			if ($icon_divs) {						
				if ( !empty($command['showlabel']) ) {
					$label = $icon.'&nbsp;'.$label;
				} else {
					$label = $icon;
				}
			} else {
				if ( empty($command['showlabel']) ) {
					$label = '';
				}
			}
		}
		
		$thisCommand = '';
	
		if ( $enabled ) {
			$thisCommand = Xml::tags( 'a', array( 'href' => $href ), $label );
		} else {
			$thisCommand = Xml::tags( 'span', array( 'class' => 'lqt_command_disabled' ),
										$label );
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
			$html .= $this->showThreadToolbar( $thread );
			$html .= Xml::openElement( 'div', array( 'class' => $divClass ) );
			$html .= $this->showPostBody( $post, $oldid );
			$html .= Xml::closeElement( 'div' );
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
		global $wgUser;
		$sk = $wgUser->getSkin();
		
		$author = $thread->author();
		$signature = $sk->userLink( $author->getId(), $author->getName() );
		$signature = '&mdash; '. Xml::tags( 'span', array( 'class' => 'lqt-thread-author' ),
								$signature );
		$signature .= $sk->userToolLinks( $author->getId(), $author->getName() );
		
		$signature = Xml::tags( 'div', array( 'class' => 'lqt-thread-signature' ),
								$signature );
		
		return $signature;
	}
	
	function threadInfoPanel( $thread ) {
		global $wgUser, $wgLang;
		
		$sk = $wgUser->getSkin();
		
		$infoElements = array();
		
		$timestamp = $wgLang->timeanddate( $thread->created(), true );
		$infoElements[] = Xml::element( 'div', array( 'class' => 'lqt-thread-toolbar-timestamp' ),
									$timestamp );
									
		// Check for edited flag.
		$editedFlag = $thread->editedness();		
		$ebLookup = array( Threads::EDITED_BY_AUTHOR => 'author',
							Threads::EDITED_BY_OTHERS => 'others' );
		if ( isset( $ebLookup[$editedFlag] ) ) {

			$editedBy = $ebLookup[$editedFlag];
			$editedNotice = wfMsgExt( 'lqt-thread-edited-'.$editedBy, 'parseinline' );
			$infoElements[] = Xml::element( 'div', array( 'class' =>
											'lqt-thread-toolbar-edited-'.$editedBy ),
											$editedNotice );
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
		
		$this->output->addHTML( $this->threadInfoPanel( $thread ) );
		
		if ( $thread->summary() ) {
			$html .= $this->getSummary( $thread );
		}
		
		// Unfortunately, I can't rewrite showRootPost() to pass back HTML
		//  as it would involve rewriting EditPage, which I do NOT intend to do.

		$this->output->addHTML( $html );
		
		$this->showThreadBody( $thread );
	
	}

	function showThread( $thread, $levelNum = 1, $totalInLevel = 1 ) {
		global $wgLang;
		
		// Safeguard
		if ( $thread->type() == Threads::TYPE_DELETED
			&& ! ($this->request->getBool( 'lqt_show_deleted_threads' )
				&& $this->user->isAllowed( 'deletedhistory' ) ) ) {
			return;
		}
		
		$this->threadNestingLevel++;
		
		$sk = $this->user->getSkin();
		
		$html = '';

		$html .= Xml::element( 'a', array( 'name' => $this->anchorName($thread) ), ' ' );

		$html .= $this->showThreadHeading( $thread );
		
		$class = $this->threadDivClass( $thread );
		if ($levelNum == 1) {
			$class .= ' lqt-thread-first';
		} elseif ($levelNum == $totalInLevel) {
			$class .= ' lqt-thread-last';
		}
		
		$html .= Xml::openElement( 'div', array( 'class' => $class,
									'id' => 'lqt_thread_id_'. $thread->id() ) );

		// Modified time for topmost threads...
		if ( $thread->isTopmostThread() ) {
			$html .= Xml::hidden( 'lqt-thread-modified-'.$thread->id(),
									wfTimestamp( TS_MW, $thread->modified() ),
									array( 'id' => 'lqt-thread-modified-'.$thread->id(),
											'class' => 'lqt-thread-modified' ) );
		}								

		// Flush output to display thread
		$this->output->addHTML( $html );
		$this->output->addHTML( Xml::openElement( 'div',
									array( 'class' => 'lqt-post-wrapper' ) ) );
		$this->showSingleThread( $thread );
		$this->output->addHTML( Xml::closeElement( 'div' ) );

		if ( $thread->hasSubthreads() ) {
			$repliesClass = 'lqt-thread-replies lqt-thread-replies-'.$this->threadNestingLevel;
			$div = Xml::openElement( 'div', array( 'class' => $repliesClass ) );
			$this->output->addHTML( $div );
			
			$subthreadCount = count( $thread->subthreads() );
			$i = 0;
			
			foreach ( $thread->subthreads() as $st ) {
				++$i;
				if ($i == 1 || !$lastSubthread->hasSubthreads() ) {
					$this->output->addHTML(
						Xml::tags( 'div', array( 'class' => 'lqt-post-sep' ), '&nbsp;' ) );
				}
				
				if ($st->type() != Threads::TYPE_DELETED) {
					$this->showThread( $st, $i, $subthreadCount );
				}
				
				$lastSubthread = $st;
			}
			
			$finishDiv = Xml::tags( 'div', array( 'class' => 'lqt-replies-finish' ),
				Xml::tags( 'div', array( 'class' => 'lqt-replies-finish-corner' ), '&nbsp;' ) );
			
			$this->output->addHTML( $finishDiv . Xml::CloseElement( 'div' ) );
		}

		if ($this->threadNestingLevel == 1) {
			$finishDiv = Xml::tags( 'div', array( 'class' => 'lqt-replies-finish' ),
				Xml::tags( 'div', array( 'class' => 'lqt-replies-finish-corner' ), '&nbsp;' ) );
				
			$this->output->addHTML( $finishDiv );
		}

		$this->output->addHTML( Xml::closeElement( 'div' ) );
		
		$this->threadNestingLevel--;
	}
	
	function threadDivClass( $thread ) {
		$levelClass = 'lqt-thread-nest-'.$this->threadNestingLevel;
		$alternatingType = ($this->threadNestingLevel % 2) ? 'odd' : 'even';
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
}
