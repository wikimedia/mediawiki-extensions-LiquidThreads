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

	/* Subject page of the talkpage we're attached to: */
	protected $articleNamespace;
	protected $articleTitle;

	/* Timestamps: */
	protected $modified;
	protected $created;

	protected $id;
	protected $revisionNumber;
	protected $type;
	protected $subject;
	protected $authorId;
	protected $authorName;

	/* Flag about who has edited or replied to this thread. */
	protected $editedness;

	/* Information about what changed in this revision. */
	protected $changeType;
	protected $changeObject;
	protected $changeComment;
	protected $changeUser;
	protected $changeUserText;

	/* Only used by $double to be saved into a historical thread. */
	protected $rootRevision;

	/* Copy of $this made when first loaded from database, to store the data
	   we will write to the history if a new revision is commited. */
	protected $double;

	protected $replies;
	
	static $titleCacheById = array();
	static $replyCacheById = array();

	function isHistorical() {
		return false;
	}

	function revisionNumber() {
		return $this->revisionNumber;
	}

	function atRevision( $r ) {
		if ( $r == $this->revisionNumber() )
			return $this;
		else
			return HistoricalThread::withIdAtRevision( $this->id(), $r );
	}

	function historicalRevisions() {
		$dbr =& wfGetDB( DB_SLAVE );
		$res = $dbr->select(
			'historical_thread',
			'hthread_contents',
			array( 'hthread_id' => $this->id() ),
			__METHOD__ );
		$results = array();
		while ( $l = $dbr->fetchObject( $res ) ) {
			$results[] = HistoricalThread::fromTextRepresentation( $l->hthread_contents );
		}
		return $results;
	}

	private function bumpRevisionsOnAncestors( $change_type, $change_object, $change_reason, $timestamp ) {
		global $wgUser; // TODO global.

		$this->revisionNumber += 1;
		$this->setChangeType( $change_type );
		$this->setChangeObject( $change_object );
		$this->changeComment = $change_reason;
		$this->changeUser = $wgUser->getID();
		$this->changeUserText = $wgUser->getName();

		if ( $this->hasSuperthread() )
			$this->superthread()->bumpRevisionsOnAncestors( $change_type, $change_object, $change_reason, $timestamp );
		$dbr =& wfGetDB( DB_MASTER );
		$res = $dbr->update( 'thread',
		     /* SET */ array( 'thread_revision' => $this->revisionNumber,
		                     'thread_change_type' => $this->changeType,
		                     'thread_change_object' => $this->changeObject,
							 'thread_change_comment' => $this->changeComment,
							 'thread_change_user' => $this->changeUser,
							 'thread_change_user_text' => $this->changeUserText,
							 'thread_modified' => $timestamp ),
		     /* WHERE */ array( 'thread_id' => $this->id ),
		     __METHOD__ );
	}

	private static function setChangeOnDescendents( $thread, $change_type, $change_object ) {
		// TODO this is ludicrously inefficient.
		$thread->setChangeType( $change_type );
		$thread->setChangeObject( $change_object );
		$dbr =& wfGetDB( DB_MASTER );
		$res = $dbr->update( 'thread',
		     /* SET */ array( 'thread_revision' => $thread->revisionNumber,
		                     'thread_change_type' => $thread->changeType,
		                     'thread_change_object' => $thread->changeObject ),
		     /* WHERE */ array( 'thread_id' => $thread->id ),
		     __METHOD__ );
		foreach ( $thread->replies() as $r )
			self::setChangeOnDescendents( $r, $change_type, $change_object );
		return $thread;
	}
	
	function getDouble() {
		if (!$this->double) {
			$this->double = clone $this;
		}
		
		return $this->double;
	}

	function commitRevision( $change_type, $change_object = null, $reason = "" ) {
		global $wgUser;

		// TODO open a transaction.
		HistoricalThread::create( $this->getDouble(), $change_type, $change_object );

		$this->bumpRevisionsOnAncestors( $change_type, $change_object, $reason, wfTimestampNow() );
		self::setChangeOnDescendents( $this->topmostThread(), $change_type, $change_object );

		if ( $change_type == Threads::CHANGE_REPLY_CREATED
				&& $this->editedness == Threads::EDITED_NEVER ) {
			$this->editedness = Threads::EDITED_HAS_REPLY;
		}
		else if ( $change_type == Threads::CHANGE_EDITED_ROOT ) {
			$originalAuthor = $this->author();
			if ( ( $wgUser->getId() == 0 && $originalAuthor->getName() != $wgUser->getName() )
					|| $wgUser->getId() != $originalAuthor->getId() ) {
				$this->editedness = Threads::EDITED_BY_OTHERS;
			} else if ( $this->editedness == Threads::EDITED_HAS_REPLY ) {
				$this->editedness = Threads::EDITED_BY_AUTHOR;
			}
		}

		/* SCHEMA changes must be reflected here. */

		$dbr =& wfGetDB( DB_MASTER );
		$res = $dbr->update( 'thread',
		     /* SET */array( 'thread_root' => $this->rootId,
					'thread_ancestor' => $this->ancestorId,
					'thread_parent' => $this->parentId,
					'thread_type' => $this->type,
					'thread_subject' => $this->subject,
					'thread_author_id' => $this->authorId,
					'thread_author_name' => $this->authorName,
					'thread_summary_page' => $this->summaryId,
					'thread_article_namespace' => $this->articleNamespace,
				    'thread_article_title' => $this->articleTitle,
					'thread_editedness' => $this->editedness,
					),
		     /* WHERE */ array( 'thread_id' => $this->id, ),
		     __METHOD__ );
		     
		// Touch the root
		$this->root()->getTitle()->invalidateCache();
		// Touch the talk page, too.
		$this->article()->getTitle()->invalidateCache();

		if ( $change_type == Threads::CHANGE_EDITED_ROOT ) {
			NewMessages::writeMessageStateForUpdatedThread( $this, $change_type, $wgUser );
		}
	}
	
	function author() {
		$this->doLazyUpdates();
		
		if ($this->authorId) {
			return User::newFromId( $this->authorId );
		} else {
			// Do NOT validate username. If the user did it, they did it.
			return User::newFromName( $this->authorName, false );
		}
	}

	function delete( $reason ) {
		$this->type = Threads::TYPE_DELETED;
		$this->revisionNumber += 1;
		$this->commitRevision( Threads::CHANGE_DELETED, $this, $reason );
		/* Mark thread as read by all users, or we get blank thingies in New Messages. */
		
		$dbw = wfGetDB( DB_MASTER );
		
		$dbw->delete( 'user_message_state', array( 'ums_thread' => $this->id() ),
						__METHOD__ );
	}
	
	function undelete( $reason ) {
		$this->type = Threads::TYPE_NORMAL;
		$this->revisionNumber += 1;
		$this->commitRevision( Threads::CHANGE_UNDELETED, $this, $reason );
	}

	function moveToPage( $title, $reason, $leave_trace ) {
		$dbr =& wfGetDB( DB_MASTER );

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
		$this->revisionNumber += 1;
		$this->commitRevision( Threads::CHANGE_MOVED_TALKPAGE, null, $reason );
		
		# Log the move
		$log = new LogPage( 'liquidthreads' );
		$log->addEntry( 'move', $this->double->title(), $reason, array( $oldTitle, $newTitle ) );

		if ( $leave_trace ) {
			$this->leaveTrace( $reason );
		}
	}

	function leaveTrace( $reason ) {
		/* Adapted from Title::moveToNewTitle. But now the new title exists on the old talkpage. */
		$dbw =& wfGetDB( DB_MASTER );

		$mwRedir = MagicWord::get( 'redirect' );
		$redirectText = $mwRedir->getSynonym( 0 ) . ' [[' . $this->title()->getPrefixedText() . "]]\n";
		$redirectArticle = new Article( LqtView::incrementedTitle( $this->subjectWithoutIncrement(),
			NS_LQT_THREAD ) ); # # TODO move to model.
		$newid = $redirectArticle->insertOn( $dbw );
		$redirectRevision = new Revision( array(
			'page'    => $newid,
			'comment' => $reason,
			'text'    => $redirectText ) );
		$redirectRevision->insertOn( $dbw );
		$redirectArticle->updateRevisionOn( $dbw, $redirectRevision, 0 );

		# Purge caches as per article creation
		Article::onArticleCreate( $redirectArticle->getTitle() );

		# Record the just-created redirect's linking to the page
		$dbw->insert( 'pagelinks',
			array(
				'pl_from'      => $newid,
				'pl_namespace' => $redirectArticle->getTitle()->getNamespace(),
				'pl_title'     => $redirectArticle->getTitle()->getDBkey() ),
			__METHOD__ );

		$thread = Threads::newThread( $redirectArticle, $this->double->article(), null,
		 	Threads::TYPE_MOVED, $this->subject() );

		# Purge old title from squid
		# The new title, and links to the new title, are purged in Article::onArticleCreate()
		# $this-->purgeSquid();
	}



	function __construct( $line, $children ) {
		/* SCHEMA changes must be reflected here. */
		
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
							'thread_revision' => 'revisionNumber',
							'thread_type' => 'type',
							'thread_change_type' => 'changeType',
							'thread_change_object' => 'changeObject',
							'thread_change_comment' => 'changeComment',
							'thread_change_user' => 'changeUser',
							'thread_change_user_text' => 'changeUserText',
							'thread_editedness' => 'editedness',
							'thread_subject' => 'subject',
							'thread_author_id' => 'authorId',
							'thread_author_name' => 'authorName',
						);
						
		foreach( $dataLoads as $db_field => $member_field ) {
			if ( isset($line->$db_field) ) {
				$this->$member_field = $line->$db_field;
			}
		}
		
		if ( isset($line->page_namespace) && isset($line->page_title) ) {
			$root_title = Title::makeTitle( $line->page_namespace, $line->page_title );
			$this->root = new Article( $root_title );
			$this->root->loadPageData( $line );
		} else {
			if ( isset( self::$titleCacheById[$this->rootId] ) ) {
				$root_title = self::$titleCacheById[$this->rootId];
			} else {
				$root_title = Title::newFromID( $this->rootId );
			}
			$this->root = new Article( $root_title );
		}

		$this->rootRevision = $this->root->mLatest;
		
		$this->doLazyUpdates( $line );
	}
	
	// Load a list of threads in bulk, including all subthreads.
	static function bulkLoad( $rows ) {		
		// Preload subthreads
		$thread_ids = array();
		$pageIds = array();
		
		if (!is_array(self::$replyCacheById)) {
			self::$replyCacheById = array();
		}
		
		foreach( $rows as $row ) {
			$thread_ids[] = $row->thread_id;
			
			// Grab page data while we're here.
			if ($row->thread_root)
				$pageIds[] = $row->thread_root;
			if ($row->thread_summary_page)
				$pageIds[] = $row->thread_summary_page;
		}
		
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
				
				if ( !is_null($row->thread_parent) ) {
					if ( !isset( self::$replyCacheById[$row->thread_parent] ) ) {
						self::$replyCacheById[$row->thread_parent] = array();
					}
					
					self::$replyCacheById[$row->thread_parent][$row->thread_id] =
										new Thread( $row, null );
				}
			}
		}
		
		// Preload page data in one swoop.
		if ( count($pageIds) ) {
			$res = $dbr->select( 'page', '*', array( 'page_id' => $pageIds ), __METHOD__ );
			while( $row = $dbr->fetchObject( $res ) ) {
				$t = Title::newFromRow( $row );
				
				self::$titleCacheById[$t->getArticleId()] = $t;
				
				if ( count(self::$titleCacheById) > 1000 ) {
					self::$titleCacheById = array();
				}
			}
		}
		
		$threads = array();
		
		foreach( $rows as $row ) {
			$threads[] = $thread = new Thread( $row, null );
			Threads::$cache_by_id[$row->thread_id] = $thread;
		}
		
		return $threads;
	}
	
	/**
	* Return the User object representing the author of the first revision
	* (or null, if the database is screwed up).
	*/
	function loadOriginalAuthorFromRevision( ) {
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
		if (!$this->subject) {
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
				$articleTitle->getNamespace() != NS_THREAD ) {
			$newTitle = $articleTitle->getTalkPage();
			$newArticle = new Article( $newTitle );
			
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
		
		if ( count($set) ) {
			$dbw = wfGetDB( DB_MASTER );
			
			$dbw->update( 'thread', $set, array( 'thread_id' => $this->id() ), __METHOD__ );
		}
		
		// Done
		$doingUpdates = false;
	}

	function initWithReplies( $children ) {

		$this->replies = $children;

		$this->double = clone $this;
	}

	function __clone() {
		// Cloning does not normally create a new array (but the clone keyword doesn't
		// work on arrays -- go figure).

		// Update: this doesn't work for some reason, but why do we update the replies array
		// in the first place after creating a new reply?
		$new_array = array();

		if( $this->replies ) {
			foreach ( $this->replies as $r )
				$new_array[] = $r;
			$this->replies = $new_array;
		}
	}

	/*
	More evidence that the way I'm doing history is totally screwed.
	These methods do not alter the childrens' superthread field. All they do
	is make sure the latest info gets into any historicalthreads we commit.
	 */
	function addReply( $thread ) {
		// TODO: question for myself to ponder: We don't want the latest info in the
		// historical thread, duh. Why were we doing this?
//		$this->replies[] = $thread;
	}
	
	function removeReplyWithId( $id ) {
		// Force load
		$this->replies();
		
		$target = null;
		foreach ( $this->replies as $k => $r ) {
			if ( $r->id() == $id ) {
				$target = $k; break;
			}
		}
		if ( $target ) {
			unset( $this->replies[$target] );
			return true;
		} else {
			return false;
		}
	}
	
	function replies() {
		if ( !is_null($this->replies) ) {
			return $this->replies;
		}
		
		// Check cache
		if ( isset( self::$replyCacheById[$this->id()] ) ) {
			return self::$replyCacheById[$this->id()];
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
			$this->ancestorId = $this->id();
			return;
		}
	
		$this->parentId = $thread->id();
		
		if ( $thread->isTopmostThread() ) {
			$this->ancestorId = $thread->id();
		} else {
			$this->ancestorId = $thread->ancestorId();
		}
	}

	function superthread() {
		if ( !$this->hasSuperthread() ) {
			return null;
		} else {
			return Threads::withId( $this->parentId );
		}
	}

	function hasSuperthread() {
		return $this->parentId != null;
	}

	function topmostThread() {
		// In further evidence that the history mechanism is fragile,
		// if we always use Threads::withId instead of returning $this,
		// the historical revision is not incremented and we get a
		// duplicate key.
		if ( $this->isTopmostThread() ) {
			return $this;
		} else {
			$thread = Threads::withId( $this->ancestorId );

			if (!$thread) {
				$thread = $this->fixMissingAncestor();
			}
			
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

	function article() {
		if ( $this->article ) return $this->article;
		
		if ( !is_null( $this->articleId ) ) {
			$title = Title::newFromID( $this->articleId );
			if ( $title ) {
				$article = new Article( $title );
			}
		}
		if ( isset( $article ) && $article->exists() ) {
			$this->article = $article;
			return $article;
		} else {
			$title = Title::makeTitle( $this->articleNamespace, $this->articleTitle );
			return new Article( $title );
		}
	}

	function id() {
		return $this->id;
	}

	function ancestorId() {
		return $this->ancestorId;
	}

	// The 'root' is the page in the Thread namespace corresponding to this thread.
	function root() {
		if ( !$this->rootId ) return null;
		if ( !$this->root ) $this->root = new Article( Title::newFromID( $this->rootId ),
		                                            $this->rootRevision() );
		return $this->root;
	}

	function setRootRevision( $rr ) {
		if ( ( is_object( $rr ) ) ) {
			$this->rootRevision = $rr->getId();
		} else if ( is_int( $rr ) ) {
			$this->rootRevision = $rr;
		}
	}

	function rootRevision() {
		return $this->rootRevision;
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
				return null;
			}
			
			$this->summary = new Article( $title );

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
		return $this->root()->getTitle();
	}

	static function splitIncrementFromSubject( $subject_string ) {
		preg_match( '/^(.*) \((\d+)\)$/', $subject_string, $matches );
		if ( count( $matches ) != 3 )
			throw new MWException( __METHOD__ . ": thread subject has no increment: " . $subject_string );
		else
			return $matches;
	}

	function wikilink() {
		return $this->root()->getTitle()->getPrefixedText();
	}

	function subject() {
		return $this->subject;
	}
	
	function setSubject( $subject ) {
		$this->subject = $subject;
		
		foreach( $this->replies() as $reply ) {
			$reply->setSubject( $subject );
			$reply->commitRevision( CHANGE_EDITED_SUBJECT );
		}
	}

	function wikilinkWithoutIncrement() {
		return $this->wikilink();
	}

	function subjectWithoutIncrement() {
		return $this->subject();
	}

	function hasDistinctSubject() {
		return $this->isTopmostThread();
	}

	function hasSubthreads() {
		return count( $this->replies() ) != 0;
	}

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

	function changeType() {
		return $this->changeType;
	}

	private function replyWithId( $id ) {
		if ( $this->id == $id ) return $this;
		foreach ( $this->replies() as $r ) {
			if ( $r->id() == $id ) return $r;
			else {
				$s = $r->replyWithId( $id );
				if ( $s ) return $s;
			}
		}
		return null;
	}
	function changeObject() {
		return $this->replyWithId( $this->changeObject );
	}

	function setChangeType( $t ) {
		if ( in_array( $t, Threads::$VALID_CHANGE_TYPES ) ) {
			$this->changeType = $t;
		} else {
			throw new MWException( __METHOD__ . ": invalid changeType $t." );
		}
	}

	function setChangeObject( $o ) {
		# we assume $o to be a Thread.
		if ( $o === null ) {
			$this->changeObject = null;
		} else {
			$this->changeObject = $o->id();
		}
	}

	function changeUser() {
		if ( $this->changeUser == 0 ) {
			return User::newFromName( $this->changeUserText, false );
		} else {
			return User::newFromId( $this->changeUser );
		}
	}

	function changeComment() {
		return $this->changeComment;
	}

	function redirectThread() {
		$rev = Revision::newFromId( $this->root()->getLatest() );
		$rtitle = Title::newFromRedirect( $rev->getRawText() );
		if ( !$rtitle ) return null;
		$rthread = Threads::withRoot( new Article( $rtitle ) );
		return $rthread;
	}

	// Called from hook in Title::isProtected.
	static function getRestrictionsForTitle( $title, $action, &$result ) {
		$thread = Threads::withRoot( new Article( $title ) );
		if ( $thread )
			return $thread->getRestrictions( $action, $result );
		else
			return true; // not a thread; do normal protection check.
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

	function getArchiveStartDays() {
		return Threads::getArticleArchiveStartDays( $this->article() );
	}
	
	function getAnchorName() {
		return "lqt_thread_{$this->id()}";
	}
}
