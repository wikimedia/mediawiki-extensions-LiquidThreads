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

	protected $user_colors;
	protected $user_color_index;
	const number_of_user_colors = 6;

	protected $queries;

	protected $sort_order = LQT_NEWEST_CHANGES;

	function __construct( &$output, &$article, &$title, &$user, &$request ) {
		$this->article = $article;
		$this->output = $output;
		$this->user = $user;
		$this->title = $title;
		$this->request = $request;
		$this->user_colors = array();
		$this->user_color_index = 1;
		$this->queries = $this->initializeQueries();
	}

	function setHeaderLevel( $int ) {
		$this->headerLevel = $int;
	}

	function initializeQueries() {

		// Determine sort order
		if ( $this->methodApplies( 'talkpage_sort_order' ) ) {
			// Sort order is explicitly specified through UI
			global $wgRequest;
			$lqt_order = $wgRequest->getVal( 'lqt_order' );
			switch( $lqt_order ) {
				case 'nc':
					$this->sort_order = LQT_NEWEST_CHANGES;
					break;
				case 'nt':
					$this->sort_order = LQT_NEWEST_THREADS;
					break;
				case 'ot':
					$this->sort_order = LQT_OLDEST_THREADS;
					break;
			}
		} else {
			// Sort order set in user preferences overrides default
			global $wgUser;
			$user_order = $wgUser->getOption( 'lqt_sort_order' ) ;
			if ( $user_order ) {
				$this->sort_order = $user_order;
			}
		}
		
		// Create query group
		global $wgOut, $wgLqtThreadArchiveStartDays, $wgLqtThreadArchiveInactiveDays;
		$dbr = wfGetDB( DB_SLAVE );
		$g = new QueryGroup();
		
		$startdate = Date::now()->nDaysAgo( $wgLqtThreadArchiveStartDays )->midnight();
		$recentstartdate = $startdate->nDaysAgo( $wgLqtThreadArchiveInactiveDays );
		$article_clause = Threads::articleClause( $this->article );
		if ( $this->sort_order == LQT_NEWEST_CHANGES ) {
			$sort_clause = 'ORDER BY thread.thread_modified DESC';
		} elseif ( $this->sort_order == LQT_NEWEST_THREADS ) {
			$sort_clause = 'ORDER BY thread.thread_created DESC';
		} elseif ( $this->sort_order == LQT_OLDEST_THREADS ) {
			$sort_clause = 'ORDER BY thread.thread_created ASC';
		}
		
		// Add standard queries
		$g->addQuery( 'fresh',
		              array( $article_clause,
							'thread.thread_parent is null',
		                    '(thread.thread_modified >= ' . $startdate->text() .
		 					'  OR (thread.thread_summary_page is NULL' .
								 ' AND thread.thread_type=' . Threads::TYPE_NORMAL . '))' ),
		              array( $sort_clause ) );
		
		$g->extendQuery( 'fresh', 'fresh-undeleted',
						array( 'thread_type != '. $dbr->addQuotes( Threads::TYPE_DELETED ) ) );
						
						
		$g->addQuery( 'archived',
		             array( $article_clause,
							'thread.thread_parent is null',
		                   '(thread.thread_summary_page is not null' .
			                  ' OR thread.thread_type=' . Threads::TYPE_NORMAL . ')',
		                   'thread.thread_modified < ' . $startdate->text() ),
		             array( $sort_clause ) );
		             
		$g->extendQuery( 'archived', 'recently-archived',
		                array( '( thread.thread_modified >=' . $recentstartdate->text() .
				      '  OR  rev_timestamp >= ' . $recentstartdate->text() . ')',
				      'summary_page.page_id = thread.thread_summary_page', 'summary_page.page_latest = rev_id' ),
				array(),
				array( 'page summary_page', 'revision' ) );
				
		$g->addQuery( 'archived', 'archived-undeleted',
						array( 'thread_type != '. $dbr->addQuotes( Threads::TYPE_DELETED ) ) );
						
						
		return $g;
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
									$includeFragment=true, $attribs = array() ) {
		list( $title, $query ) = self::talkpageLinkData( $title, $method, $operand,
									$includeFragment );
		
		global $wgUser;
		$sk = $wgUser->getSkin();
		
		return $sk->link( $title, $text, $attribs, $query );
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
			return <<<HTML
			<input type="hidden" name="$name" id="$name" value="$value">
HTML;
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
		$this->showEditingFormInGeneral( null, 'summarize', $thread );
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
			$this->perpetuate( 'lqt_operand', 'hidden' );

		if ( $edit_type == 'new' || ( $thread && !$thread->hasSuperthread() ) ) {
			wfLoadExtensionMessages( 'LiquidThreads' );
			// This is a top-level post; show the subject line.
			$db_subject = $thread ? $thread->subjectWithoutIncrement() : '';
			$subject = $this->request->getVal( 'lqt_subject_field', $db_subject );
			$subject_label = wfMsg( 'lqt_subject' );
			
			$disableattr = array();
			if ( $thread && !$this->user->isAllowed( 'move' ) ) {
				$disableattr = array( 'readonly' => 'readonly' );
			}
			
			$e->editFormTextBeforeContent .=
				Xml::inputLabel( $subject_label, 'lqt_subject_field', 'lqt_subject_field',
					60, $subject, $disableattr ) . Xml::element( 'br' );
		}
		
		// Quote the original message if we're replying
		if ( $edit_type == 'reply' ) {
			global $wgContLang;
			
			$thread_article = new Post( $edit_applies_to->title() );
			
			$quote_text = $thread_article->getContent();
			$timestamp = $edit_applies_to->created();
			$fTime = $wgContLang->time($timestamp);
			$fDate = $wgContLang->date($timestamp);
			$user = $thread_article->originalAuthor()->getName();
			
			$quoteIntro = wfMsgForContent( 'lqt-quote-intro', $user, $fTime, $fDate );
			$quote_text = "$quoteIntro\n<blockquote>\n$quote_text\n</blockquote>\n";
			
			$e->setPreloadedText( $quote_text );
		}

		$e->edit();

		// Override what happens in EditPage::showEditForm, called from $e->edit():

		$this->output->setArticleFlag( false );

		// For replies and new posts, insert the associated thread object into the DB.
		if ( $edit_type != 'editExisting' && $edit_type != 'summarize' && $e->didSave ) {
			if ( $edit_type == 'reply' ) {
				$thread = Threads::newThread( $article, $this->article, $edit_applies_to, $e->summary );
				$edit_applies_to->commitRevision( Threads::CHANGE_REPLY_CREATED, $thread, $e->summary );
			} else {
				$thread = Threads::newThread( $article, $this->article, null, $e->summary );
			}
		}

		if ( $edit_type == 'summarize' && $e->didSave ) {
			$edit_applies_to->setSummary( $article );
			$edit_applies_to->commitRevision( Threads::CHANGE_EDITED_SUMMARY, $edit_applies_to, $e->summary );
		}

		// Move the thread and replies if subject changed.
		if ( $edit_type == 'editExisting' && $e->didSave ) {
			$subject = $this->request->getVal( 'lqt_subject_field', '' );
			if ( $subject && $subject != $thread->subjectWithoutIncrement() ) {

				$this->renameThread( $thread, $subject, $e->summary );
			}
			// this is unrelated to the subject change and is for all edits:
			$thread->setRootRevision( Revision::newFromTitle( $thread->root()->getTitle() ) );
			$thread->commitRevision( Threads::CHANGE_EDITED_ROOT, $thread, $e->summary );
		}

		// A redirect without $e->didSave will happen if the new text is blank (EditPage::attemptSave).
		// This results in a new Thread object not being created for replies and new discussions,
		// so $thread is null. In that case, just allow editpage to redirect back to the talk page.
		if ( $this->output->getRedirect() != '' && $thread ) {
			$this->output->redirect( $this->title->getFullURL() . '#' . 'lqt_thread_' . $thread->id() );
		} else if ( $this->output->getRedirect() != '' && $edit_applies_to ) {
			// For summaries:
			$this->output->redirect( $edit_applies_to->title()->getFullURL() . '#' . 'lqt_thread_' . $edit_applies_to->id() );
		}
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
		return $this->incrementedTitle( $subject ? $subject:wfMsg( 'lqt_nosubject' ), NS_LQT_THREAD );
	}
	function newSummaryTitle( $t ) {
		return $this->incrementedTitle( $t->subject(), NS_LQT_SUMMARY );
	}
	function newReplyTitle( $s, $t ) {
		return $this->incrementedTitle( $t->subjectWithoutIncrement(), NS_LQT_THREAD );
	}
	/** Keep trying titles starting with $basename until one is unoccupied. */
	public static function incrementedTitle( $basename, $namespace ) {
		$i = 1; do {
			$t = Title::newFromText( $basename . '_(' . $i . ')', $namespace );
			$i++;
		} while ( $t->exists() || in_array( $t->getPrefixedDBkey(), self::$occupied_titles ) );
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

		# don't allow moving to pages with # in
		if ( !$nt || $nt->getFragment() != '' ) {
			echo "malformed title"; // TODO real error reporting.
			return false;
		}

		$error = $ot->moveTo( $nt, true, "Changed thread subject: $reason" );
		if ( $error !== true ) {
			var_dump( $error );
			echo "something bad happened trying to rename the thread."; // TODO
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
	*       0 => array( 'label'   => 'Edit',
	*                   'href'    => 'http...',
	*                   'enabled' => false ),
	*       1 => array( 'label'   => 'Reply',
	*                   'href'    => 'http...',
	*                   'enabled' => true )
	*   )
	*/
	function threadFooterCommands( $thread ) {
		wfLoadExtensionMessages( 'LiquidThreads' );
		$commands = array();

		$user_can_edit = $thread->root()->getTitle()->quickUserCan( 'edit' );

		$commands[] = array( 'label' => $user_can_edit
											? wfMsg( 'edit' ) : wfMsg( 'viewsource' ),
		                     'href' => $this->talkpageUrl( $this->title, 'edit', $thread ),
		                     'enabled' => true );

		$commands[] = array( 'label' => wfMsg( 'history_short' ),
							 'href' =>  self::permalinkUrlWithQuery( $thread, 'action=history' ),
							 'enabled' => true );

		$commands[] = array( 'label' => wfMsg( 'lqt_permalink' ),
							 'href' =>  self::permalinkUrl( $thread ),
							 'enabled' => true );

		if ( in_array( 'delete',  $this->user->getRights() ) ) {
			$delete_url = SpecialPage::getTitleFor( 'DeleteThread' )->getFullURL()
				. '/' . $thread->title()->getPrefixedURL();
			$commands[] = array( 'label' => $thread->type() == Threads::TYPE_DELETED ? wfMsg( 'lqt_undelete' ) : wfMsg( 'delete' ),
								 'href' =>  $delete_url,
								 'enabled' => true );
		}

		$commands[] = array( 'label' => '<b class="lqt_reply_link">' . wfMsg( 'lqt_reply' ) . '</b>',
							 'href' =>  $this->talkpageUrl( $this->title, 'reply', $thread ),
							 'enabled' => $user_can_edit );

		return $commands;
	}

	function topLevelThreadCommands( $thread ) {
		wfLoadExtensionMessages( 'LiquidThreads' );
		$commands = array();

		$commands[] = array( 'label' => wfMsg( 'history_short' ),
		                     'href' => self::permalinkUrl( $thread, 'thread_history' ),
		                     'enabled' => true );

		if ( in_array( 'move', $this->user->getRights() ) ) {
			$move_href = SpecialPage::getTitleFor( 'MoveThread' )->getFullURL()
				. '/' . $thread->title()->getPrefixedURL();
			$commands[] = array( 'label' => wfMsg( 'move' ),
			                     'href' => $move_href,
			                     'enabled' => true );
		}
		if ( !$this->user->isAnon() && !$thread->title()->userIsWatching() ) {
			$commands[] = array( 'label' => wfMsg( 'watch' ),
			                     'href' => self::permalinkUrlWithQuery( $thread, 'action=watch' ),
			                     'enabled' => true );
		} else if ( !$this->user->isAnon() ) {
			$commands[] = array( 'label' => wfMsg( 'unwatch' ),
                                 'href' => self::permalinkUrlWithQuery( $thread, 'action=unwatch' ),
			                     'enabled' => true );
		}
		
		if ( $this->user->isAllowed( 'delete' ) ) {
			$delete_title = SpecialPage::getTitleFor( 'DeleteThread',
								$thread->title()->getPrefixedText() );
			$delete_href = $delete_title->getFullURL();
			
			$commands[] = array( 'label' => wfMsg( 'delete' ),
									'href' => $delete_href,
									'enabled' => true );
		}

		return $commands;
	}

	/*************************
	* Output methods         *
	*************************/

	static function addJSandCSS() {
		// Changed this to be static so that we can call it from
		// wfLqtBeforeWatchlistHook.
		global $wgJsMimeType, $wgScriptPath, $wgStyleVersion; // TODO globals.
		global $wgOut;
		$s = <<< HTML
		<script type="{$wgJsMimeType}" src="{$wgScriptPath}/extensions/LiquidThreads/lqt.js"><!-- lqt js --></script>
		<style type="text/css" media="screen, projection">/*<![CDATA[*/
			@import "{$wgScriptPath}/extensions/LiquidThreads/lqt.css?{$wgStyleVersion}";
		/*]]>*/</style>

HTML;
		$wgOut->addScript( $s );
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

	function colorTest() {
		$this->output->addHTML( '<div class="lqt_footer"><li class="lqt_footer_sig">' );
		for ( $i = 1; $i <= self::number_of_user_colors; $i++ ) {
			$this->output->addHTML( "<span class=\"lqt_post_color_{$i}\"><a href=\"foo\">DavidMcCabe</a></span>" );
		}
		$this->output->addHTML( '</li></div>' );
	}

	function showThreadFooter( $thread ) {
		global $wgLang, $wgUser;
		
		$sk = $wgUser->getSkin();
		$html = '';

		// Author signature.
		$author = $thread->root()->originalAuthor();
		$sig = $this->user->getSkin()->userLink( $author->getID(), $author->getName() ) .
			   $this->user->getSkin()->userToolLinks( $author->getID(), $author->getName() );
		$html .= Xml::tags( 'li', array( 'class' => 'lqt_author_sig' ), $sig );

		// Edited flag
		if ( $thread->editedness() == Threads::EDITED_BY_AUTHOR || $thread->editedness() == Threads::EDITED_BY_OTHERS ) {
			wfLoadExtensionMessages( 'LiquidThreads' );
			list($edited_title) = self::permalinkData( $thread );
			$edited_text = wfMsgExt( 'lqt_edited_notice', 'parseinline' );
			$edited_link = $sk->link( $edited_title, $edited_text,
										array( 'class' => 'lqt_edited_notice'),
										array( 'action' => 'history' ) );
										
			$html .= Xml::tags( 'li', array( 'class' => 'lqt_edited_notice' ), $edited_link );
		}
		
		// Timestamp
		$timestamp = $wgLang->timeanddate( $thread->created(), true );
		$html .= Xml::element( 'li', null, $timestamp );		

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
		foreach ( $commands as $command ) {
			$label = $command['label'];
			$href = $command['href'];
			$enabled = $command['enabled'];

			if ( $enabled ) {
				$result[] = "<li><a href=\"$href\">$label</a></li>";
			} else {
				$result[] = "<li><span class=\"lqt_command_disabled\">$label</span></li>";
			}
		}
		return join( "", $result );
	}

	function showRootPost( $thread ) {
		$popts = $this->output->parserOptions();
		$previous_editsection = $popts->getEditSection();
		$popts->setEditSection( false );
		$this->output->parserOptions( $popts );

		$post = $thread->root();

		// This is a bit of a hack to have individual histories work.
		// We can grab oldid either from lqt_oldid (which is a thread rev),
		// or from oldid (which is a page rev). But oldid only applies to the
		// thread being requested, not any replies.  TODO: eliminate the need
		// for article-level histories.
		$divClass = $this->postDivClass( $thread );
		$html = Xml::openElement( 'div', array( 'class' => $divClass ) );
		$page_rev = $this->request->getVal( 'oldid', null );
		if ( $page_rev !== null && $this->title->equals( $thread->root()->getTitle() ) ) {
			$oldid = $page_rev;
		} else {
			$oldid = $thread->isHistorical() ? $thread->rootRevision() : null;
		}

		if ( $this->methodAppliesToThread( 'edit', $thread ) ) {
			$this->output->addHTML( $html );
			$html = '';
			
			// No way am I refactoring EditForm to send its output as HTML.
			//  so I'm just flushing the HTML and displaying it as-is.
			$this->showPostEditingForm( $thread );
		} else {
			$html .= $this->showPostBody( $post, $oldid );
			$html .= $this->showThreadFooter( $thread );
		}

		// wish I didn't have to use this open/closeElement cruft.
		$html .= Xml::closeElement( 'div' );

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
		return 'lqt_post';
	}

	static function anchorName( $thread ) {
		return "lqt_thread_{$thread->id()}";
	}

	function showThread( $thread ) {
		global $wgLang;
		
		$sk = $this->user->getSkin();
		
		$html = '';

		// Safeguard
		if ( $thread->type() == Threads::TYPE_DELETED
			&& ! ($this->request->getBool( 'lqt_show_deleted_threads' )
				&& $this->user->isAllowed( 'deletedhistory' ) ) ) {
			return;
		}

		if ( $this->lastUnindentedSuperthread ) {
			wfLoadExtensionMessages( 'LiquidThreads' );
			$tmp = $this->lastUnindentedSuperthread;
			$replyLink = Xml::tags( 'a', array( 'href' => '#'.$this->anchorName( $tmp ) ),
									$tmp->subject() );
			$msg = wfMsgExt( 'lqt_in_response_to', array( 'parseinline', 'replaceafter' ),
				array( $replyLink, $tmp->root()->originalAuthor()->getName() ) );
				
			$html .= Xml::tags( 'span', array( 'class' => 'lqt_nonindent_message' ),
								"&larr; $msg" );
		}


		$html .= $this->showThreadHeading( $thread );

		$html .= Xml::element( 'a', array( 'name' => $this->anchorName($thread) ), ' ' );

		if ( $thread->type() == Threads::TYPE_MOVED ) {
			wfLoadExtensionMessages( 'LiquidThreads' );
			
			$revision = Revision::newFromTitle( $thread->title() );
			$target = Title::newFromRedirect( $revision->getText() );
			$t_thread = Threads::withRoot( new Article( $target ) );
			$author = $thread->root()->originalAuthor();
			$sig = $sk->userLink( $author->getID(), $author->getName() ) .
				   $sk->userToolLinks( $author->getID(), $author->getName() );
				   
			$html .=
				wfMsgExt( 'lqt_move_placeholder', array( 'parseinline', 'replaceafter' ),
					$sk->link( $target ),
					$sig,
					$wgLang->date( $thread->modified() ),
					$wgLang->time( $thread->modified() )
				);
			return $html;
		}

		if ( $thread->type() == Threads::TYPE_DELETED ) {
			wfLoadExtensionMessages( 'LiquidThreads' );
			if ( in_array( 'deletedhistory',  $this->user->getRights() ) ) {
				$html .= wfMsgExt( 'lqt_thread_deleted_for_sysops', 'parse' );
			}
			else {
				$msg = wfMsgExt( 'lqt_thread_deleted', 'parseinline' );
				$msg = Xml::tags( 'em', null, $msg );
				$msg = Xml::tags( 'p', null, $msg );
				$html .= $msg;
				return $html;
			}
		}
		if ( $thread->summary() ) {
			$html .= $this->showPostBody( $thread->summary() );
		} elseif( $thread->isArchiveEligible() )
		{
			wfLoadExtensionMessages( 'LiquidThreads' );
			
			$permalink_text = wfMsgNoTrans( 'lqt_summary_notice_link' );
			$permalink = self::permalink( $thread, $permalink_text );
			$msg = wfMsgExt( 'lqt_summary_notice', array('parseinline', 'replaceafter'),
								array( $permalink, $thread->getArchiveStartDays() ) );
			$msg = Xml::tags( 'p', array( 'class' => 'lqt_summary_notice' ), $msg );
			
			$html .= $msg;
		}

		// Sigh.
		$html .= Xml::openElement( 'div', array( 'class' => 'lqt_thread',
									'id' => 'lqt_thread_id_'. $thread->id() ) );
									
		// Unfortunately, I can't rewrite showRootPost() to pass back HTML
		//  as it would involve rewriting EditPage, which I do NOT intend to do.

		$this->output->addHTML( $html );
		
		$this->showRootPost( $thread );

		if ( $thread->hasSubthreads() ) {
			$this->output->addHTML( $this->indent( $thread ) );
		
			foreach ( $thread->subthreads() as $st ) {
				$this->showThread( $st );
			}
		
			$this->output->addHTML( $this->unindent( $thread ) );
		}

		$this->output->addHTML( Xml::closeElement( 'div' ) );
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
		$edit_link = self::permalink( $t, $edit_text, 'summarize' );
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
