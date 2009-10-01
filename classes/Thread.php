<?php
if ( !defined( 'MEDIAWIKI' ) ) die;

class Thread {
	/* SCHEMA changes must be reflected here. */

	/* ID references to other objects that are loaded on demand: */
	protected $rootId;
	protected $articleId;
	protected $summaryId;
	protected $ancestorId;
	protected $parentId;

	/* Actual objects loaded on demand from the above when accessors are called: */
	protected $root;
	protected $article;
	protected $summary;
	protected $superthread;
	protected $ancestor;

	/* Subject page of the talkpage we're attached to: */
	protected $articleNamespace;
	protected $articleTitle;

	/* Timestamps: */
	protected $modified;
	protected $created;
	protected $sortkey;

	protected $id;
	protected $type;
	protected $subject;
	protected $authorId;
	protected $authorName;
	
	protected $allDataLoaded;
	
	protected $isHistorical = false;
	
	protected $rootRevision;

	/* Flag about who has edited or replied to this thread. */
	protected $editedness;

	protected $replies;
	
	static $titleCacheById = array();
	static $replyCacheById = array();
	static $articleCacheById = array();
	
	static $VALID_TYPES = array( Threads::TYPE_NORMAL, Threads::TYPE_MOVED, Threads::TYPE_DELETED );

	function isHistorical() {
		return $this->isHistorical;
	}
	
	static function create( $root, $article, $superthread = null,
    							$type = Threads::TYPE_NORMAL, $subject = '' ) {

        $dbw = wfGetDB( DB_MASTER );
        
        $thread = new Thread(null);

		if ( !in_array( $type, self::$VALID_TYPES ) ) {
			throw new MWException( __METHOD__ . ": invalid change type $type." );
		}

		if ( $superthread ) {
			$change_type = Threads::CHANGE_REPLY_CREATED;
		} else {
			$change_type = Threads::CHANGE_NEW_THREAD;
		}

		global $wgUser;

		$timestamp = wfTimestampNow();

		$thread->setAuthor( $wgUser );
		$thread->setRoot( $root );
		$thread->setSuperthread( $superthread );
		$thread->setArticle( $article );
		$thread->setSubject( $subject );
		$thread->setType( $type );
		
		$thread->insert();
		
		if ( $superthread ) {
			$superthread->addReply( $thread );
			
			$superthread->commitRevision( $change_type, $thread );
		} else {
			$hthread = ThreadRevision::create( $thread, $change_type );
		}
		
		// Increment appropriate reply counts.
		$t = $thread->superthread();
		while ($t) {
			$t->incrementReplyCount();
			$t->save();
			$t = $t->superthread();
		}
		
		// Create talk page
		Threads::createTalkpageIfNeeded( $article );

		// Notifications
		NewMessages::writeMessageStateForUpdatedThread( $thread, $change_type, $wgUser );
		
		if ($wgUser->getOption( 'lqt-watch-threads', false ) ) {
			$thread->topmostThread()->root()->doWatch();
		}

		return $thread;
	}
	
	function insert() {
		$this->dieIfHistorical();
		
		$dbw = wfGetDB( DB_MASTER );
		
		$row = $this->getRow();
		$row['thread_id'] = $dbw->nextSequenceValue( 'thread_thread_id' );
		
		$dbw->insert( 'thread', $row, __METHOD__ );
		$this->id = $dbw->insertId();
		
		// Touch the root
		if ($this->root()) {
			$this->root()->getTitle()->invalidateCache();
		}
		
		// Touch the talk page, too.
		$this->article()->getTitle()->invalidateCache();
	}
	
	function setRoot( $article ) {
		$this->rootId = $article->getId();
		$this->root = $article;
	}

	function commitRevision( $change_type, $change_object = null, $reason = "" ) {
		$this->dieIfHistorical();
		global $wgUser;
		
		global $wgThreadActionsNoBump;
		if ( !in_array( $change_type, $wgThreadActionsNoBump ) ) {
			$this->sortkey = wfTimestampNow();
		}

		$this->modified = wfTimestampNow();
		$this->updateEditedness( $change_type );
		$this->save();
		
		$topmost = $this->topmostThread();
		$topmost->modified = wfTimestampNow();
		$topmost->save();
		
		ThreadRevision::create( $this, $change_type, $change_object, $reason );

		if ( $change_type == Threads::CHANGE_EDITED_ROOT ) {
			NewMessages::writeMessageStateForUpdatedThread( $this, $change_type, $wgUser );
		}
	}
	
	function updateEditedness( $change_type ) {
		global $wgUser;
		
		if ( $change_type == Threads::CHANGE_REPLY_CREATED
				&& $this->editedness == Threads::EDITED_NEVER ) {
			$this->editedness = Threads::EDITED_HAS_REPLY;
		} elseif ( $change_type == Threads::CHANGE_EDITED_ROOT ) {
			$originalAuthor = $this->author();
			
			if ( ( $wgUser->getId() == 0 && $originalAuthor->getName() != $wgUser->getName() )
					|| $wgUser->getId() != $originalAuthor->getId() ) {
				$this->editedness = Threads::EDITED_BY_OTHERS;
			} elseif ( $this->editedness == Threads::EDITED_HAS_REPLY ) {
				$this->editedness = Threads::EDITED_BY_AUTHOR;
			}
		}
	}
	
	/** Unless you know what you're doing, you want commitRevision */
	function save() {		
		$this->dieIfHistorical();

		$dbr = wfGetDB( DB_MASTER );
		
		$res = $dbr->update( 'thread',
		     /* SET */ $this->getRow(),
		     /* WHERE */ array( 'thread_id' => $this->id, ),
		     __METHOD__ );
		     
		// Touch the root
		if ($this->root()) {
			$this->root()->getTitle()->invalidateCache();
		}
		
		// Touch the talk page, too.
		$this->article()->getTitle()->invalidateCache();
	}
	
	function getRow() {
		$id = $this->id();
		
		$dbw = wfGetDB( DB_MASTER );

		if (!$id) {
			$id = $dbw->nextSequenceValue( 'thread_thread_id' );
		}
		
		// Reflect schema changes here.
	
		return array(
					'thread_id' => $id,
					'thread_root' => $this->rootId,
					'thread_parent' => $this->parentId,
					'thread_article_namespace' => $this->articleNamespace,
				    'thread_article_title' => $this->articleTitle,
				    'thread_modified' => $dbw->timestamp($this->modified),
				    'thread_created' => $dbw->timestamp($this->created),
					'thread_ancestor' => $this->ancestorId,
					'thread_type' => $this->type,
					'thread_subject' => $this->subject,
					'thread_author_id' => $this->authorId,
					'thread_author_name' => $this->authorName,
					'thread_summary_page' => $this->summaryId,
					'thread_editedness' => $this->editedness,
					'thread_sortkey' => $this->sortkey,
					'thread_replies' => $this->replyCount,
				);
	}
	
	function author() {
		$this->doLazyUpdates();
		
		if ($this->authorId) {
			return User::newFromId( $this->authorId );
		} else {
			// Do NOT validate username. If the user did it, they did it.
			return User::newFromName( $this->authorName, false /* no validation */ );
		}
	}

	function delete( $reason ) {
		$this->type = Threads::TYPE_DELETED;
		$this->commitRevision( Threads::CHANGE_DELETED, $this, $reason );
		/* Mark thread as read by all users, or we get blank thingies in New Messages. */
		
		$this->dieIfHistorical();
		
		$dbw = wfGetDB( DB_MASTER );
		
		$dbw->delete( 'user_message_state', array( 'ums_thread' => $this->id() ),
						__METHOD__ );
		
		// Fix reply count.
		$t = $this->superthread();
		while( $t ) {
			$t->decrementReplyCount();
			$t->save();
		}
	}
	
	function undelete( $reason ) {
		$this->type = Threads::TYPE_NORMAL;
		$this->commitRevision( Threads::CHANGE_UNDELETED, $this, $reason );
		
		// Fix reply count.
		$t = $this->superthread();
		while( $t ) {
			$t->incrementReplyCount();
		}
	}

	function moveToPage( $title, $reason, $leave_trace ) {
		if (!$this->isTopmostThread() )
			throw new MWException( "Attempt to move non-toplevel thread to another page" );
			
		$this->dieIfHistorical();
		
		$dbr = wfGetDB( DB_MASTER );

		$oldTitle = $this->article()->getTitle();
		$newTitle = $title;
		
		$new_articleNamespace = $title->getNamespace();
		$new_articleTitle = $title->getDBkey();
		
		// Update on *all* subthreads.
		$dbr->update( 'thread',
						array(
							'thread_revision=thread_revision+1',
							'thread_article_namespace' => $new_articleNamespace,
							'thread_article_title' => $new_articleTitle,
							'thread_modified' => $dbr->timestamp( wfTimestampNow() ),
						),
						array( 'thread_ancestor' => $this->id() ),
						__METHOD__ );

		$this->articleNamespace = $new_articleNamespace;
		$this->articleTitle = $new_articleTitle;
		$this->commitRevision( Threads::CHANGE_MOVED_TALKPAGE, null, $reason );
		
		# Log the move
		$log = new LogPage( 'liquidthreads' );
		$log->addEntry( 'move', $this->title(), $reason, array( $oldTitle, $newTitle ) );

		if ( $leave_trace ) {
			$this->leaveTrace( $reason, $oldTitle, $newTitle );
		}
	}

	// Drop a note at the source location of a move, noting that a thread was moved from
	//  there.
	function leaveTrace( $reason, $oldTitle, $newTitle ) {
		$this->dieIfHistorical();
		
		$dbw = wfGetDB( DB_MASTER );

		// Create redirect text
		$mwRedir = MagicWord::get( 'redirect' );
		$redirectText = $mwRedir->getSynonym( 0 ) . ' [[' . $this->title()->getPrefixedText() . "]]\n";
		
		// Make the article edit.
		$traceTitle = Threads::newThreadTitle( $this->subject(), new Article_LQT_Compat($oldTitle) );
		$redirectArticle = new Article_LQT_Compat( $traceTitle );
		$redirectArticle->doEdit( $redirectText, $reason, EDIT_NEW );

		// Add the trace thread to the tracking table.
		$thread = Threads::newThread( $redirectArticle, new Article_LQT_Compat($oldTitle), null,
		 	Threads::TYPE_MOVED, $this->subject() );
	}

	// Lists total reply count, including replies to replies and such
	function replyCount() {
		return $this->replyCount;
	}
	
	function incrementReplyCount() {
		$this->replyCount++;
	}
	
	function decrementReplyCount() {
		$this->replyCount--;
	}

	function __construct( $line, $unused = null ) {
		/* SCHEMA changes must be reflected here. */
		
		if ( is_null($line) ) { // For Thread::create().
			$this->modified = wfTimestampNow();
			$this->created = wfTimestampNow();
			$this->sortkey = wfTimestampNow();
			$this->editedness = Threads::EDITED_NEVER;
			$this->replyCount = 0;
			return;
		}
		
		$dataLoads = array(
							'thread_id' => 'id',
							'thread_root' => 'rootId',
							'thread_article_namespace' => 'articleNamespace',
							'thread_article_title' => 'articleTitle',
							'thread_summary_page' => 'summaryId',
							'thread_ancestor' => 'ancestorId',
							'thread_parent' => 'parentId',
							'thread_modified' => 'modified',
							'thread_created' => 'created',
							'thread_type' => 'type',
							'thread_editedness' => 'editedness',
							'thread_subject' => 'subject',
							'thread_author_id' => 'authorId',
							'thread_author_name' => 'authorName',
							'thread_sortkey' => 'sortkey',
							'thread_replies' => 'replyCount',
						);
						
		foreach( $dataLoads as $db_field => $member_field ) {
			if ( isset($line->$db_field) ) {
				$this->$member_field = $line->$db_field;
			}
		}
		
		if ( isset($line->page_namespace) && isset($line->page_title) ) {
			$root_title = Title::makeTitle( $line->page_namespace, $line->page_title );
			$this->root = new Article_LQT_Compat( $root_title );
			$this->root->loadPageData( $line );
		} else {
			if ( isset( self::$titleCacheById[$this->rootId] ) ) {
				$root_title = self::$titleCacheById[$this->rootId];
			} else {
				$root_title = Title::newFromID( $this->rootId );
			}
			
			if ($root_title) {
				$this->root = new Article_LQT_Compat( $root_title );
			}
		}
		
		$this->doLazyUpdates( $line );
	}
	
	// Load a list of threads in bulk, including all subthreads.
	static function bulkLoad( $rows ) {		
		// Preload subthreads
		$thread_ids = array();
		$all_thread_rows = $rows;
		$pageIds = array();
		$linkBatch = new LinkBatch();
		$userIds = array();
		
		if (!is_array(self::$replyCacheById)) {
			self::$replyCacheById = array();
		}
		
		// Build a list of threads for which to pull replies, and page IDs to pull data for.
		//  Also, pre-initialise the reply cache.
		foreach( $rows as $row ) {
			$thread_ids[] = $row->thread_id;
			
			// Grab page data while we're here.
			if ($row->thread_root)
				$pageIds[] = $row->thread_root;
			if ($row->thread_summary_page)
				$pageIds[] = $row->thread_summary_page;
				
			if ( !isset( self::$replyCacheById[$row->thread_id] ) ) {
				self::$replyCacheById[$row->thread_id] = array();
			}
		}
		
		// Pull replies to the threads provided, and as above, pull page IDs to pull data for,
		//  pre-initialise the reply cache, and stash the row object for later use.
		if ( count($thread_ids) ) {
			$dbr = wfGetDB( DB_SLAVE );
			$res = $dbr->select( 'thread', '*', array( 'thread_ancestor' => $thread_ids ),
									__METHOD__ );
									
			while( $row = $dbr->fetchObject($res) ) {				
				// Grab page data while we're here.
				if ($row->thread_root)
					$pageIds[] = $row->thread_root;
				if ($row->thread_summary_page)
					$pageIds[] = $row->thread_summary_page;
					
				$all_thread_rows[] = $row;
				
				if ( !isset( self::$replyCacheById[$row->thread_id] ) ) {
					self::$replyCacheById[$row->thread_id] = array();
				}
			}
		}
		
		// Preload page data (restrictions, and preload Article object with everything from 
		//  the page table. Also, precache the title and article objects for pulling later.
		$articlesById = array();
		if ( count($pageIds) ) {
			// Pull restriction info. Needs to come first because otherwise it's done per
			//  page by loadPageData.
			$restrictionRows = array_fill_keys( $pageIds, array() );
			$res = $dbr->select( 'page_restrictions', '*', array( 'pr_page' => $pageIds ),
									__METHOD__ );
			while( $row = $dbr->fetchObject( $res ) ) {
				$restrictionRows[$row->pr_page][] = $row;
			}
			
			$res = $dbr->select( 'page', '*', array( 'page_id' => $pageIds ), __METHOD__ );
			
			while( $row = $dbr->fetchObject( $res ) ) {
				$t = Title::newFromRow( $row );
				
				if ( isset( $restrictionRows[$t->getArticleId()] ) ) {
					$t->loadRestrictionsFromRows( $restrictionRows[$t->getArticleId()],
													$row->page_restrictions );
				}
				
				$article = new Article_LQT_Compat( $t );
				$article->loadPageData( $row );
				
				self::$titleCacheById[$t->getArticleId()] = $t;
				$articlesById[$article->getId()] = $article;
				
				if ( count(self::$titleCacheById) > 10000 ) {
					self::$titleCacheById = array();
				}
			}
		}
		
		// For every thread we have a row object for, load a Thread object, add the user and
		//  user talk pages to a link batch, cache the relevant user id/name pair, and
		//  populate the reply cache.
		foreach( $all_thread_rows as $row ) {		
			$thread = new Thread( $row, null );
			
			if ( isset($articlesById[$thread->rootId]) )
				$thread->root = $articlesById[$thread->rootId];
			
			Threads::$cache_by_id[$row->thread_id] = $thread;
			
			// User cache data
			$t = Title::makeTitleSafe( NS_USER, $row->thread_author_name );
			$linkBatch->addObj( $t );
			$t = Title::makeTitleSafe( NS_USER_TALK, $row->thread_author_name );
			$linkBatch->addObj( $t );
			
			User::$idCacheByName[$row->thread_author_name] = $row->thread_author_id;
			$userIds[$row->thread_author_id] = true;
			
			if ( $row->thread_parent ) {
				self::$replyCacheById[$row->thread_parent][$row->thread_id] = $thread;
			}
		}
		
		$userIds = array_keys($userIds);
		
		// Pull signature data and pre-cache in View object.		
		if ( count($userIds) ) {
			$signatureDataCache = array_fill_keys( $userIds, array() );
			$res = $dbr->select( 'user_properties',
									array( 'up_user', 'up_property', 'up_value' ),
									array( 'up_property' => array('nickname', 'fancysig'),
											'up_user' => $userIds ),
									__METHOD__ );
			
			foreach( $res as $row ) {
				$signatureDataCache[$row->up_user][$row->up_property] = $row->up_value;
			}
			
			global $wgParser, $wgOut;
			
			foreach( $userIds as $uid ) {
				$user = User::newFromId($uid); // Should pull from UID cache.
				$name = $user->getName();
				
				// Grab sig data
				$nickname = null;
				$fancysig = (bool)User::getDefaultOption( 'fancysig' );
				
				if ( isset($signatureDataCache[$uid]['nickname']) )
					$nickname = $signatureDataCache[$uid]['nickname'];
				if( isset($signatureDataCache[$uid]['fancysig']) )
					$fancysig = $signatureDataCache[$uid]['fancysig'];
					
				// Generate signature from Parser
				
				$sig = $wgParser->getUserSig( $user, $nickname, $fancysig );
				$sig = $wgOut->parseInline( $sig );
				
				// Save into LqtView for later use.
				LqtView::$userSignatureCache[$name] = $sig;
			}
		}
		
		// Pull link batch data.
		$linkBatch->execute();
		
		$threads = array();
		
		// Fill and return an array with the threads that were actually requested.
		foreach( $rows as $row ) {
			$threads[$row->thread_id] = Threads::$cache_by_id[$row->thread_id];
		}
		
		return $threads;
	}
	
	/**
	* Return the User object representing the author of the first revision
	* (or null, if the database is screwed up).
	*/
	function loadOriginalAuthorFromRevision( ) {
		$this->dieIfHistorical();
		
		$dbr = wfGetDB( DB_SLAVE );
		
		$article = $this->root();

		$line = $dbr->selectRow( 'revision',
								'rev_user_text',
								array( 'rev_page' => $article->getID() ),
								__METHOD__,
								array(
									'ORDER BY' => 'rev_timestamp',
									'LIMIT'   => '1'
								) );
		if ( $line )
			return User::newFromName( $line->rev_user_text, false );
		else
			return null;
	}
	
	// Lazy updates done whenever a thread is loaded.
	//  Much easier than running a long-running maintenance script.
	function doLazyUpdates( ) {
		if ( $this->isHistorical() )
			return; // Don't do lazy updates on stored historical threads.
		
		// This is an invocation guard to avoid infinite recursion when fixing a
		//  missing ancestor.
		static $doingUpdates = false;
		if ($doingUpdates) return;
		$doingUpdates = true;
		
		// Fix missing ancestry information.
		// (there was a bug where this was not saved properly)
		if ($this->parentId &&!$this->ancestorId) {
			$this->fixMissingAncestor();
		}
		
		$ancestor = $this->topmostThread();
		
		$set = array();
		
		// Fix missing subject information
		// (this information only started to be added later)
		if ( !$this->subject && $this->root() ) {
			$detectedSubject = $this->root()->getTitle()->getText();
			$parts = self::splitIncrementFromSubject( $detectedSubject );
			
			$this->subject = $detectedSubject = $parts[1];
			
			// Update in the DB
			$set['thread_subject'] = $detectedSubject;
		}
		
		// Fix inconsistent subject information
		// (in some intermediate versions this was not updated when the subject was changed)
		if ($this->subject() != $ancestor->subject()) {
			$set['thread_subject'] = $ancestor->subject();
			
			$this->subject = $ancestor->subject();
		}
		
		// Fix missing authorship information
		// (this information only started to be added later)
		if ( !$this->authorName ) {
			$author = $this->loadOriginalAuthorFromRevision();
			
			$this->authorId = $author->getId();
			$this->authorName = $author->getName();
			
			$set['thread_author_name'] = $this->authorName;
			$set['thread_author_id'] = $this->authorId;
		}
		
		//Check for article being in subject, not talk namespace.
		//If the page is non-LiquidThreads and it's in subject-space, we'll assume it's meant
		// to be on the corresponding talk page, but only if the talk-page is a LQT page.
		//(Previous versions stored the subject page, for some totally bizarre reason)
		// Old versions also sometimes store the thread page for trace threads as the
		//  article, not as the root.
		//  Trying not to exacerbate this by moving it to be the 'Thread talk' page.
		$articleTitle = $this->article()->getTitle();
		if ( !LqtDispatch::isLqtPage( $articleTitle ) && !$articleTitle->isTalkPage() &&
				LqtDispatch::isLqtPage( $articleTitle->getTalkPage() ) &&
				$articleTitle->getNamespace() != NS_LQT_THREAD ) {
			$newTitle = $articleTitle->getTalkPage();
			$newArticle = new Article_LQT_Compat( $newTitle );
			
			$set['thread_article_namespace'] = $newTitle->getNamespace();
			$set['thread_article_title'] = $newTitle->getDbKey();
			
			$this->articleNamespace = $newTitle->getNamespace();
			$this->articleTitle = $newTitle->getDbKey();
			
			$this->article = $newArticle;
		}
		
		// Check for article corruption from incomplete thread moves.
		// (thread moves only updated this on immediate replies, not replies to replies etc)
		if (! $ancestor->article()->getTitle()->equals( $this->article()->getTitle() ) ) {
			$title = $ancestor->article()->getTitle();
			$set['thread_article_namespace'] = $title->getNamespace();
			$set['thread_article_title'] = $title->getDbKey();
			
			$this->articleNamespace = $title->getNamespace();
			$this->articleTitle = $title->getDbKey();
			
			$this->article = $ancestor->article();
		}
		
		// Populate reply count
		if ( $this->replyCount == -1 ) {
			$dbr = wfGetDB( DB_SLAVE );
			
			$count = $dbr->selectField( 'thread', 'count(*)',
				array( 'thread_ancestor' => $this->id() ), __METHOD__ );
				
			$this->replyCount = $count;
			$set['thread_replies'] = $count;
		}
		
		if ( count($set) ) {
			$dbw = wfGetDB( DB_MASTER );
			
			$dbw->update( 'thread', $set, array( 'thread_id' => $this->id() ), __METHOD__ );
		}
		
		// Done
		$doingUpdates = false;
	}

	function addReply( $thread ) {
		$thread->setSuperThread( $this );
		
		if ( is_array($this->replies) ) {
			$this->replies[$thread->id()] = $thread;
		} else {
			$this->replies();
			$this->replies[$thread->id()] = $thread;
		}
		
		// Increment reply count.
		$this->replyCount += $thread->replyCount() + 1;
	}
	
	function removeReply( $thread ) {
		if ( is_object($thread) ) {
			$thread = $thread->id();
		}
		
		$this->replies();
		
		unset( $thread->replies[$thread] );
		
		// Also, decrement the reply count.
		$threadObj = Threads::withId($thread);
		$this->replyCount -= ( 1 + $threadObj->replyCount() );
	}
	
	function replies() {
		if ( !is_null($this->replies) ) {
			return $this->replies;
		}
		
		$this->dieIfHistorical();
		
		// Check cache
		if ( isset( self::$replyCacheById[$this->id()] ) ) {
			return $this->replies = self::$replyCacheById[$this->id()];
		}
		
		$this->replies = array();
		
		$dbr = wfGetDB( DB_SLAVE );
		
		$res = $dbr->select( 'thread', '*',
								array( 'thread_parent' => $this->id() ), __METHOD__ );
		
		$rows = array();
		while ( $row = $dbr->fetchObject($res) ) {
			$rows[] = $row;
		}
		
		$this->replies = Thread::bulkLoad( $rows );
		
		return $this->replies;
	}

	function setSuperthread( $thread ) {
		if ($thread == null) {
			$this->parentId = null;
			$this->ancestorId = 0;
			return;
		}
	
		$this->parentId = $thread->id();
		$this->superthread = $thread;
		
		if ( $thread->isTopmostThread() ) {
			$this->ancestorId = $thread->id();
			$this->ancestor = $thread;
		} else {
			$this->ancestorId = $thread->ancestorId();
			$this->ancestor = $thread->topmostThread();
		}
	}

	function superthread() {
		if ( !$this->hasSuperthread() ) {
			return null;
		} else {
			$this->dieIfHistorical();
			return Threads::withId( $this->parentId );
		}
	}

	function hasSuperthread() {
		return !$this->isTopmostThread();
	}

	function topmostThread() {
		if ( $this->isTopmostThread() ) {
			return $this->ancestor = $this;
		} elseif ($this->ancestor) {
			return $this->ancestor;
		} else {
			$this->dieIfHistorical();
			
			$thread = Threads::withId( $this->ancestorId );

			if (!$thread) {
				$thread = $this->fixMissingAncestor();
			}
			
			$this->ancestor = $thread;
			
			return $thread;
		}
	}
	
	function setAncestor( $newAncestor ) {
		if ( is_object( $newAncestor ) ) {
			$this->ancestorId = $newAncestor->id();
		} else {
			$this->ancestorId = $newAncestor;
		}
	}

	// Due to a bug in earlier versions, the topmost thread sometimes isn't there.
	// Fix the corruption by repeatedly grabbing the parent until we hit the topmost thread.
	function fixMissingAncestor() {
		$thread = $this;
		
		$this->dieIfHistorical();
		
		while ( !$thread->isTopmostThread() ) {
			$thread = $thread->superthread();
		}
		
		$this->ancestorId = $thread->id();
		
		$dbw = wfGetDB( DB_MASTER );
		$dbw->update( 'thread', array( 'thread_ancestor' => $thread->id() ),
						array( 'thread_id' => $this->id() ), __METHOD__ );
		
		return $thread;
	}

	function isTopmostThread() {
		return $this->ancestorId == $this->id ||
				$this->parentId == 0;
	}

	function setArticle( $a ) {
		$this->articleId = $a->getID();
		$this->articleNamespace = $a->getTitle()->getNamespace();
		$this->articleTitle = $a->getTitle()->getDBkey();
		$this->touch();
	}
	
	function touch() {
		// Nothing here yet
	}

	function article() {
		if ( $this->article ) return $this->article;
		
		if ( !is_null( $this->articleId ) ) {
			$title = Title::newFromID( $this->articleId );
			if ( $title ) {
				$article = new Article_LQT_Compat( $title );
			}
		}
		if ( isset( $article ) && $article->exists() ) {
			$this->article = $article;
			return $article;
		} else {
			$title = Title::makeTitle( $this->articleNamespace, $this->articleTitle );
			return new Article_LQT_Compat( $title );
		}
	}

	function id() {
		return $this->id;
	}

	function ancestorId() {
		return $this->ancestorId;
	}

	// The 'root' is the page in the Thread namespace corresponding to this thread.
	function root( ) {
		if ( !$this->rootId ) return null;
		if ( !$this->root ) {
			if ( isset(self::$articleCacheById[$this->rootId]) ) {
				$this->root = self::$articleCacheById[$this->rootId];
				return $this->root;
			}
		
			if ( isset( self::$titleCacheById[$this->rootId] ) ) {
				$title = self::$titleCacheById[$this->rootId];
			} else {
				$title = Title::newFromID( $this->rootId );
			}
			
			if (!$title) return null;
			
			$this->root = new Article_LQT_Compat( $title );
		}
		return $this->root;
	}

	function editedness() {
		return $this->editedness;
	}

	function summary() {
		if ( !$this->summaryId )
			return null;
			
		if ( !$this->summary ) {
			$title = Title::newFromID( $this->summaryId );
			
			if (!$title) {
				wfDebug( __METHOD__.": supposed summary doesn't exist" );
				$this->summaryId = null;
				return null;
			}
			
			$this->summary = new Article_LQT_Compat( $title );

		}
			
		return $this->summary;
	}

	function hasSummary() {
		return $this->summaryId != null;
	}

	function setSummary( $post ) {
		// Weird -- this was setting $this->summary to NULL before I changed it.
		// If there was some reason why, please tell me! -- Andrew
		$this->summary = $post;
		$this->summaryId = $post->getID();
	}

	function title() {
		if ( is_object( $this->root() ) ) {
			return $this->root()->getTitle();
		} else {
			return null;
		}
	}

	static function splitIncrementFromSubject( $subject_string ) {
		preg_match( '/^(.*) \((\d+)\)$/', $subject_string, $matches );
		if ( count( $matches ) != 3 )
			throw new MWException( __METHOD__ . ": thread subject has no increment: " . $subject_string );
		else
			return $matches;
	}

	function subject() {
		return $this->subject;
	}
	
	function setSubject( $subject ) {
		$this->subject = $subject;
	}

	// Deprecated, use subject().
	function subjectWithoutIncrement() {
		return $this->subject();
	}

	// Currently equivalent to isTopmostThread.
	function hasDistinctSubject() {
		return $this->isTopmostThread();
	}

	function hasSubthreads() {
		return count( $this->replies() ) != 0;
	}

	// Synonym for replies()
	function subthreads() {
		return $this->replies();
	}

	function modified() {
		return $this->modified;
	}

	function created() {
		return $this->created;
	}

	function type() {
		return $this->type;
	}
	
	function setType($t) {
		$this->type = $t;
	}

	function redirectThread() {
		$rev = Revision::newFromId( $this->root()->getLatest() );
		$rtitle = Title::newFromRedirect( $rev->getRawText() );
		if ( !$rtitle ) return null;
		
		$this->dieIfHistorical();
		$rthread = Threads::withRoot( new Article_LQT_Compat( $rtitle ) );
		return $rthread;
	}

	// This only makes sense when called from the hook, because it uses the hook's
	// default behavior to check whether this thread itself is protected, so you'll
	// get false negatives if you use it from some other context.
	function getRestrictions( $action, &$result ) {
		if ( $this->hasSuperthread() ) {
			$parent_restrictions = $this->superthread()->root()->getTitle()->getRestrictions( $action );
		} else {
			$parent_restrictions = $this->article()->getTitle()->getRestrictions( $action );
		}

		// TODO this may not be the same as asking "are the parent restrictions more restrictive than
		// our own restrictions?", which is what we really want.
		if ( count( $parent_restrictions ) == 0 ) {
			return true; // go to normal protection check.
		} else {
			$result = $parent_restrictions;
			return false;
		}

	}
	
	function getAnchorName() {
		return "lqt_thread_{$this->id()}";
	}
	
	function updateHistory() {
// 		$dbr = wfGetDB( DB_SLAVE );
// 		
// 		$res = $dbr->select( 'historical_thread', '*',
// 										array( 'hthread_id' => $this->id() ),
// 										__METHOD__,
// 										array( 'ORDER BY' => 'hthread_revision ASC' ) );
// 
// 		foreach( $row as $res ) {
// 			$historical_thread = HistoricalThread::fromTextRepresentation( $row->hthread_content );
// 
// 			// Insert a revision into the database.			
// 			$rev = ThreadRevision::create( $historical_thread,
// 											$historical_thread->changeType(),
// 											$historical_thread->changeObject(),
// 											$historical_thread->changeComment(),
// 											$historical_thread->changeUser(),
// 											$historical_thread->modified() );
// 		}
	}
	
	function setAuthor( $user ) {
		$this->authorId = $user->getId();
		$this->authorName = $user->getName();
	}
	
	// Load all lazy-loaded data in prep for (e.g.) serialization.
	function loadAllData() {
		// Make sure superthread and topmost thread are loaded.
		$this->superthread();
		$this->topmostThread();
		
		// Make sure replies, and all the data therein, is loaded.
		foreach( $this->replies() as $reply ) {
			$reply->loadAllData();
		}
	}
	
	// On serialization, load all data because it will be different in the DB when we wake up.
	function __sleep() {
		
		$this->loadAllData();
		
		$fields = array_keys( get_object_vars( $this ) );
		
		// Filter out article objects, there be dragons (or unserialization problems)
		$fields = array_diff( $fields, array( 'root', 'article', 'summary', 'sleeping' ) );
		
		return $fields;
	}
	
	function __wakeup() {
		// Mark as historical.
		$this->isHistorical = true;
	}
	
	// This is a safety valve that makes sure that the DB is NEVER touched by a historical
	//  thread (even for reading, because the data will be out of date).
	function dieIfHistorical() {
		if ($this->isHistorical()) {
			throw new MWException( "Attempted write or DB operation on historical thread" );
		}
	}
	
	function rootRevision() {
		if ( !$this->isHistorical() || !isset($this->topmostThread()->threadRevision) ) {
			return null;
		}
		
		$dbr = wfGetDB( DB_SLAVE );
		
		$revision = $this->topmostThread()->threadRevision;
		$timestamp = $dbr->timestamp( $revision->getTimestamp() );
		
		$conds = array(
				'rev_timestamp<='.$dbr->addQuotes( $timestamp ),
				'page_namespace' => $this->root()->getTitle()->getNamespace(),
				'page_title' => $this->root()->getTitle()->getDBKey(),
			);
			
		$join_conds = array( 'page' => array( 'JOIN', 'rev_page=page_id' ) );
		
		$row = $dbr->selectRow( array( 'revision', 'page' ), '*', $conds, __METHOD__,
							array( 'ORDER BY' => 'rev_timestamp DESC' ), $join_conds );
							
		return $row->rev_id;
	}
}
