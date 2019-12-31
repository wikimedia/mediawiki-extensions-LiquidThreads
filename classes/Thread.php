<?php

class Thread {
	/* SCHEMA changes must be reflected here. */

	/* ID references to other objects that are loaded on demand: */
	protected $rootId;
	protected $articleId;
	protected $summaryId;
	protected $ancestorId;
	/** @var int|null */
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

	/** @var int */
	protected $id;
	protected $type;
	protected $subject;
	protected $authorId;
	protected $authorName;
	protected $signature;
	protected $replyCount;

	protected $allDataLoaded;

	protected $isHistorical = false;

	protected $rootRevision;

	/* Flag about who has edited or replied to this thread. */
	public $editedness;
	protected $editors = null;

	protected $replies;
	protected $reactions;

	public $dbVersion; // A copy of the thread as it exists in the database.
	public $threadRevision;

	public static $titleCacheById = [];
	public static $replyCacheById = [];
	public static $articleCacheById = [];
	public static $reactionCacheById = [];

	public static $VALID_TYPES = [
		Threads::TYPE_NORMAL, Threads::TYPE_MOVED, Threads::TYPE_DELETED ];

	public function isHistorical() {
		return $this->isHistorical;
	}

	public static function create( $root, $article, $superthread = null,
		$type = Threads::TYPE_NORMAL, $subject = '',
		$summary = '', $bump = null, $signature = null
	) {
		$thread = new Thread( null );

		if ( !in_array( $type, self::$VALID_TYPES ) ) {
			throw new Exception( __METHOD__ . ": invalid change type $type." );
		}

		if ( $superthread ) {
			$change_type = Threads::CHANGE_REPLY_CREATED;
		} else {
			$change_type = Threads::CHANGE_NEW_THREAD;
		}

		global $wgUser;

		$thread->setAuthor( $wgUser );

		if ( is_object( $root ) ) {
			$thread->setRoot( $root );
		} else {
			$thread->setRootId( $root );
		}

		$thread->setSuperthread( $superthread );
		$thread->setArticle( $article );
		$thread->setSubject( $subject );
		$thread->setType( $type );

		if ( !is_null( $signature ) ) {
			$thread->setSignature( $signature );
		}

		$thread->insert();

		if ( $superthread ) {
			$superthread->addReply( $thread );

			$superthread->commitRevision( $change_type, $thread, $summary, $bump );
		} else {
			ThreadRevision::create( $thread, $change_type );
		}

		// Create talk page
		Threads::createTalkpageIfNeeded( $article );

		// Notifications
		NewMessages::writeMessageStateForUpdatedThread( $thread, $change_type, $wgUser );

		if ( $wgUser->getOption( 'lqt-watch-threads', false ) ) {
			WatchAction::doWatch( $thread->topmostThread()->root()->getTitle(), $wgUser );
		}

		return $thread;
	}

	public function insert() {
		$this->dieIfHistorical();

		if ( $this->id() ) {
			throw new Exception( "Attempt to insert a thread that already exists." );
		}

		$dbw = wfGetDB( DB_MASTER );

		$row = $this->getRow();

		$dbw->insert( 'thread', $row, __METHOD__ );
		$this->id = $dbw->insertId();

		// Touch the root
		if ( $this->root() ) {
			$this->root()->getTitle()->invalidateCache();
		}

		// Touch the talk page, too.
		$this->getTitle()->invalidateCache();

		$this->dbVersion = clone $this;
		$this->dbVersion->dbVersion = null;
	}

	public function setRoot( $article ) {
		$this->rootId = $article->getId();
		$this->root = $article;

		if ( $article->getTitle()->getNamespace() != NS_LQT_THREAD ) {
			throw new Exception( "Attempt to set thread root to a non-Thread page" );
		}
	}

	public function setRootId( $article ) {
		$this->rootId = $article;
		$this->root = null;
	}

	public function commitRevision( $change_type, $change_object = null, $reason = "",
					$bump = null ) {
		$this->dieIfHistorical();
		global $wgUser;

		global $wgThreadActionsNoBump;
		if ( is_null( $bump ) ) {
			$bump = !in_array( $change_type, $wgThreadActionsNoBump );
		}
		if ( $bump ) {
			$this->sortkey = wfTimestamp( TS_MW );
		}

		$original = $this->dbVersion;
		if ( $original->signature() != $this->signature() ) {
			$this->logChange( Threads::CHANGE_EDITED_SIGNATURE, $original, null, $reason );
		}

		$this->modified = wfTimestampNow();
		$this->updateEditedness( $change_type );
		$this->save( __METHOD__ . "/" . wfGetCaller() );

		$topmost = $this->topmostThread();
		$topmost->modified = wfTimestampNow();
		if ( $bump ) {
			$topmost->setSortKey( wfTimestamp( TS_MW ) );
		}
		$topmost->save();

		ThreadRevision::create( $this, $change_type, $change_object, $reason );
		$this->logChange( $change_type, $original, $change_object, $reason );

		if ( $change_type == Threads::CHANGE_EDITED_ROOT ) {
			NewMessages::writeMessageStateForUpdatedThread( $this, $change_type, $wgUser );
		}
	}

	public function logChange( $change_type, $original, $change_object = null, $reason = '' ) {
		$log = new LogPage( 'liquidthreads' );

		if ( is_null( $reason ) ) {
			$reason = '';
		}

		switch ( $change_type ) {
			case Threads::CHANGE_MOVED_TALKPAGE:
				$log->addEntry( 'move', $this->title(), $reason,
					[ $original->getTitle(),
						$this->getTitle() ] );
				break;
			case Threads::CHANGE_SPLIT:
				$log->addEntry( 'split', $this->title(), $reason,
					[ $this->subject(),
						$original->superthread()->title()
					] );
				break;
			case Threads::CHANGE_EDITED_SUBJECT:
				$log->addEntry( 'subjectedit', $this->title(), $reason,
					[ $original->subject(), $this->subject() ] );
				break;
			case Threads::CHANGE_MERGED_TO:
				$oldParent = $change_object->dbVersion->isTopmostThread()
						? ''
						: $change_object->dbVersion->superthread()->title();

				$log->addEntry( 'merge', $this->title(), $reason,
					[ $oldParent, $change_object->superthread()->title() ] );
				break;
			case Threads::CHANGE_ADJUSTED_SORTKEY:
				$log->addEntry( 'resort', $this->title(), $reason,
					[ $original->sortkey(), $this->sortkey() ] );
			case Threads::CHANGE_EDITED_SIGNATURE:
				$log->addEntry( 'signatureedit', $this->title(), $reason,
					[ $original->signature(), $this->signature() ] );
				break;
		}
	}

	public function updateEditedness( $change_type ) {
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

	/**
	 * Unless you know what you're doing, you want commitRevision
	 * @param string|null $fname
	 */
	public function save( $fname = null ) {
		$this->dieIfHistorical();

		$dbr = wfGetDB( DB_MASTER );

		if ( !$fname ) {
			$fname = __METHOD__ . "/" . wfGetCaller();
		} else {
			$fname = __METHOD__ . "/" . $fname;
		}

		$dbr->update( 'thread',
			/* SET */ $this->getRow(),
			/* WHERE */ [ 'thread_id' => $this->id, ],
			$fname );

		// Touch the root
		if ( $this->root() ) {
			$this->root()->getTitle()->invalidateCache();
		}

		// Touch the talk page, too.
		$this->getTitle()->invalidateCache();

		$this->dbVersion = clone $this;
		$this->dbVersion->dbVersion = null;
	}

	public function getRow() {
		$id = $this->id();

		$dbw = wfGetDB( DB_MASTER );

		if ( !$id ) {
			$id = $dbw->nextSequenceValue( 'thread_thread_id' );
		}

		// If there's no root, bail out with an error message
		if ( !$this->rootId && !( $this->type & Threads::TYPE_DELETED ) ) {
			throw new Exception( "Non-deleted thread saved with empty root ID" );
		}

		if ( $this->replyCount < -1 ) {
			wfWarn(
				"Saving thread $id with negative reply count {$this->replyCount} " .
					wfGetAllCallers()
			);
			$this->replyCount = -1;
		}

		// Reflect schema changes here.

		return [
			'thread_id' => $id,
			'thread_root' => $this->rootId,
			'thread_parent' => $this->parentId,
			'thread_article_namespace' => $this->articleNamespace,
			'thread_article_title' => $this->articleTitle,
			'thread_article_id' => $this->articleId,
			'thread_modified' => $dbw->timestamp( $this->modified ),
			'thread_created' => $dbw->timestamp( $this->created ),
			'thread_ancestor' => $this->ancestorId,
			'thread_type' => $this->type,
			'thread_subject' => $this->subject,
			'thread_author_id' => $this->authorId,
			'thread_author_name' => $this->authorName,
			'thread_summary_page' => $this->summaryId,
			'thread_editedness' => $this->editedness,
			'thread_sortkey' => $this->sortkey,
			'thread_replies' => $this->replyCount,
			'thread_signature' => $this->signature,
		];
	}

	public function author() {
		if ( $this->authorId ) {
			return User::newFromId( $this->authorId );
		} else {
			// Do NOT validate username. If the user did it, they did it.
			return User::newFromName( $this->authorName, false /* no validation */ );
		}
	}

	public function delete( $reason, $commit = true ) {
		if ( $this->type == Threads::TYPE_DELETED ) {
			return;
		}

		$this->type = Threads::TYPE_DELETED;

		if ( $commit ) {
			$this->commitRevision( Threads::CHANGE_DELETED, $this, $reason );
		} else {
			$this->save( __METHOD__ );
		}
		/* Mark thread as read by all users, or we get blank thingies in New Messages. */

		$this->dieIfHistorical();

		$dbw = wfGetDB( DB_MASTER );

		$dbw->delete( 'user_message_state', [ 'ums_thread' => $this->id() ],
			__METHOD__ );

		// Fix reply count.
		$t = $this->superthread();

		if ( $t ) {
			$t->decrementReplyCount( 1 + $this->replyCount() );
			$t->save();
		}
	}

	public function undelete( $reason ) {
		$this->type = Threads::TYPE_NORMAL;
		$this->commitRevision( Threads::CHANGE_UNDELETED, $this, $reason );

		// Fix reply count.
		$t = $this->superthread();
		if ( $t ) {
			$t->incrementReplyCount( 1 );
			$t->save();
		}
	}

	public function moveToPage( $title, $reason, $leave_trace ) {
		global $wgUser;

		if ( !$this->isTopmostThread() ) {
			throw new Exception( "Attempt to move non-toplevel thread to another page" );
		}

		$this->dieIfHistorical();

		$dbr = wfGetDB( DB_MASTER );

		$oldTitle = $this->getTitle();
		$newTitle = $title;

		$new_articleNamespace = $title->getNamespace();
		$new_articleTitle = $title->getDBkey();
		$new_articleID = $title->getArticleID();

		if ( !$new_articleID ) {
			$article = new Article( $newTitle, 0 );
			Threads::createTalkpageIfNeeded( $article );
			$new_articleID = $article->getId();
		}

		// Update on *all* subthreads.
		$dbr->update(
			'thread',
			[
				'thread_article_namespace' => $new_articleNamespace,
				'thread_article_title' => $new_articleTitle,
				'thread_article_id' => $new_articleID,
			],
			[ 'thread_ancestor' => $this->id() ],
			__METHOD__
		);

		$this->articleNamespace = $new_articleNamespace;
		$this->articleTitle = $new_articleTitle;
		$this->articleId = $new_articleID;
		$this->article = null;

		$this->commitRevision( Threads::CHANGE_MOVED_TALKPAGE, null, $reason );

		// Notifications
		NewMessages::writeMessageStateForUpdatedThread( $this, $this->type, $wgUser );

		if ( $leave_trace ) {
			$this->leaveTrace( $reason, $oldTitle, $newTitle );
		}
	}

	// Drop a note at the source location of a move, noting that a thread was moved from
	// there.
	public function leaveTrace( $reason, $oldTitle, $newTitle ) {
		$this->dieIfHistorical();

		// Create redirect text
		$mwRedir = MagicWord::get( 'redirect' );
		$redirectText = $mwRedir->getSynonym( 0 ) .
			' [[' . $this->title()->getPrefixedText() . "]]\n";

		// Make the article edit.
		$traceTitle = Threads::newThreadTitle( $this->subject(), new Article( $oldTitle, 0 ) );
		$redirectArticle = new Article( $traceTitle, 0 );

		$redirectArticle->getPage()->doEditContent(
			ContentHandler::makeContent( $redirectText, $traceTitle ),
			$reason,
			EDIT_NEW | EDIT_SUPPRESS_RC
		);

		// Add the trace thread to the tracking table.
		$thread = self::create( $redirectArticle, new Article( $oldTitle, 0 ), null,
			Threads::TYPE_MOVED, $this->subject() );

		$thread->setSortKey( $this->sortkey() );
		$thread->save();
	}

	// Lists total reply count, including replies to replies and such
	public function replyCount() {
		// Populate reply count
		if ( $this->replyCount == - 1 ) {
			if ( $this->isTopmostThread() ) {
				$dbr = wfGetDB( DB_REPLICA );

				$count = $dbr->selectField( 'thread', 'count(*)',
					[ 'thread_ancestor' => $this->id() ], __METHOD__ );
			} else {
				$count = self::recursiveGetReplyCount( $this );
			}

			$this->replyCount = $count;
			$this->save();
		}

		return $this->replyCount;
	}

	public function incrementReplyCount( $val = 1 ) {
		$this->replyCount += $val;

		wfDebug( "Incremented reply count for thread " . $this->id() . " to " .
			$this->replyCount . "\n" );

		$thread = $this->superthread();

		if ( $thread ) {
			$thread->incrementReplyCount( $val );
			wfDebug( "Saving Incremented thread " . $thread->id() .
				" with reply count " . $thread->replyCount . "\n" );
			$thread->save();
		}
	}

	public function decrementReplyCount( $val = 1 ) {
		$this->incrementReplyCount( - $val );
	}

	/**
	 * @param stdClass $row
	 * @return Thread
	 */
	public static function newFromRow( $row ) {
		$id = $row->thread_id;

		if ( isset( Threads::$cache_by_id[$id] ) ) {
			return Threads::$cache_by_id[$id];
		}

		return new Thread( $row );
	}

	/**
	 * @param stdClass|null $line
	 * @param null $unused
	 */
	protected function __construct( $line, $unused = null ) {
		/* SCHEMA changes must be reflected here. */

		if ( is_null( $line ) ) { // For Thread::create().
			$dbr = wfGetDB( DB_REPLICA );
			$this->modified = $dbr->timestamp( wfTimestampNow() );
			$this->created = $dbr->timestamp( wfTimestampNow() );
			$this->sortkey = wfTimestamp( TS_MW );
			$this->editedness = Threads::EDITED_NEVER;
			$this->replyCount = 0;
			return;
		}

		$dataLoads = [
			'thread_id' => 'id',
			'thread_root' => 'rootId',
			'thread_article_namespace' => 'articleNamespace',
			'thread_article_title' => 'articleTitle',
			'thread_article_id' => 'articleId',
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
			'thread_signature' => 'signature',
		];

		foreach ( $dataLoads as $db_field => $member_field ) {
			if ( isset( $line->$db_field ) ) {
				$this->$member_field = $line->$db_field;
			}
		}

		if ( isset( $line->page_namespace ) && isset( $line->page_title ) ) {
			$root_title = Title::makeTitle( $line->page_namespace, $line->page_title );
			$this->root = new Article( $root_title, 0 );
			$this->root->getPage()->loadPageData( $line );
		} else {
			if ( isset( self::$titleCacheById[$this->rootId] ) ) {
				$root_title = self::$titleCacheById[$this->rootId];
			} else {
				$root_title = Title::newFromID( $this->rootId );
			}

			if ( $root_title ) {
				$this->root = new Article( $root_title, 0 );
			}
		}

		Threads::$cache_by_id[$line->thread_id] = $this;
		if ( $line->thread_parent ) {
			if ( !isset( self::$replyCacheById[$line->thread_parent] ) ) {
				self::$replyCacheById[$line->thread_parent] = [];
			}
			self::$replyCacheById[$line->thread_parent][$line->thread_id] = $this;
		}

		try {
			$this->doLazyUpdates();
		} catch ( Exception $excep ) {
			trigger_error( "Exception doing lazy updates: " . $excep->__toString() );
		}

		$this->dbVersion = clone $this;
		$this->dbVersion->dbVersion = null;
	}

	// Load a list of threads in bulk, including all subthreads.
	public static function bulkLoad( $rows ) {
		// Preload subthreads
		$top_thread_ids = [];
		$all_thread_rows = $rows;
		$pageIds = [];
		$linkBatch = new LinkBatch();
		$userIds = [];
		$loadEditorsFor = [];

		$dbr = wfGetDB( DB_REPLICA );

		if ( !is_array( self::$replyCacheById ) ) {
			self::$replyCacheById = [];
		}

		// Build a list of threads for which to pull replies, and page IDs to pull data for.
		// Also, pre-initialise the reply cache.
		foreach ( $rows as $row ) {
			if ( $row->thread_ancestor ) {
				$top_thread_ids[] = $row->thread_ancestor;
			} else {
				$top_thread_ids[] = $row->thread_id;
			}

			// Grab page data while we're here.
			if ( $row->thread_root ) {
				$pageIds[] = $row->thread_root;
			}
			if ( $row->thread_summary_page ) {
				$pageIds[] = $row->thread_summary_page;
			}
			if ( !isset( self::$replyCacheById[$row->thread_id] ) ) {
				self::$replyCacheById[$row->thread_id] = [];
			}
		}

		$all_thread_ids = $top_thread_ids;

		// Pull replies to the threads provided, and as above, pull page IDs to pull data for,
		// pre-initialise the reply cache, and stash the row object for later use.
		if ( count( $top_thread_ids ) ) {
			$res = $dbr->select( 'thread', '*',
				[ 'thread_ancestor' => $top_thread_ids,
					'thread_type != ' . $dbr->addQuotes( Threads::TYPE_DELETED ) ],
				__METHOD__ );

			foreach ( $res as $row ) {
				// Grab page data while we're here.
				if ( $row->thread_root ) {
					$pageIds[] = $row->thread_root;
				}
				if ( $row->thread_summary_page ) {
					$pageIds[] = $row->thread_summary_page;
				}
				$all_thread_rows[] = $row;
				$all_thread_ids[$row->thread_id] = $row->thread_id;
			}
		}

		// Pull thread reactions
		if ( count( $all_thread_ids ) ) {
			$res = $dbr->select( 'thread_reaction', '*',
						[ 'tr_thread' => $all_thread_ids ],
						__METHOD__ );

			foreach ( $res as $row ) {
				$thread_id = $row->tr_thread;
				$info = [
					'type' => $row->tr_type,
					'user-id' => $row->tr_user,
					'user-name' => $row->tr_user_text,
					'value' => $row->tr_value,
				];

				$type = $info['type'];
				$user = $info['user-name'];

				if ( !isset( self::$reactionCacheById[$thread_id] ) ) {
					self::$reactionCacheById[$thread_id] = [];
				}

				if ( !isset( self::$reactionCacheById[$thread_id][$type] ) ) {
					self::$reactionCacheById[$thread_id][$type] = [];
				}

				self::$reactionCacheById[$thread_id][$type][$user] = $info;
			}
		}

		// Preload page data (restrictions, and preload Article object with everything from
		// the page table. Also, precache the title and article objects for pulling later.
		$articlesById = [];
		if ( count( $pageIds ) ) {
			// Pull restriction info. Needs to come first because otherwise it's done per
			// page by loadPageData.
			$restrictionRows = array_fill_keys( $pageIds, [] );
			$res = $dbr->select( 'page_restrictions', '*', [ 'pr_page' => $pageIds ],
									__METHOD__ );
			foreach ( $res as $row ) {
				$restrictionRows[$row->pr_page][] = $row;
			}

			$res = $dbr->select( 'page', '*', [ 'page_id' => $pageIds ], __METHOD__ );

			foreach ( $res as $row ) {
				$t = Title::newFromRow( $row );

				if ( isset( $restrictionRows[$t->getArticleID()] ) ) {
					$t->loadRestrictionsFromRows( $restrictionRows[$t->getArticleID()],
									$row->page_restrictions );
				}

				$article = new Article( $t, 0 );
				$article->getPage()->loadPageData( $row );

				self::$titleCacheById[$t->getArticleID()] = $t;
				$articlesById[$article->getId()] = $article;

				if ( count( self::$titleCacheById ) > 10000 ) {
					self::$titleCacheById = [];
				}
			}
		}

		// For every thread we have a row object for, load a Thread object, add the user and
		// user talk pages to a link batch, cache the relevant user id/name pair, and
		// populate the reply cache.
		foreach ( $all_thread_rows as $row ) {
			$thread = self::newFromRow( $row );

			if ( isset( $articlesById[$thread->rootId] ) ) {
				$thread->root = $articlesById[$thread->rootId];
			}

			// User cache data
			$t = Title::makeTitleSafe( NS_USER, $row->thread_author_name );
			$linkBatch->addObj( $t );
			$t = Title::makeTitleSafe( NS_USER_TALK, $row->thread_author_name );
			$linkBatch->addObj( $t );

			User::$idCacheByName[$row->thread_author_name] = $row->thread_author_id;
			$userIds[$row->thread_author_id] = true;

			if ( $row->thread_editedness > Threads::EDITED_BY_AUTHOR ) {
				$loadEditorsFor[$row->thread_root] = $thread;
				$thread->setEditors( [] );
			}
		}

		// Pull list of users who have edited
		if ( count( $loadEditorsFor ) ) {
			$revQuery = self::getRevisionQueryInfo();
			$res = $dbr->select(
				$revQuery['tables'],
				[ 'rev_user_text' => $revQuery['fields']['rev_user_text'], 'rev_page' ],
				[
					'rev_page' => array_keys( $loadEditorsFor ),
					'rev_parent_id != ' . $dbr->addQuotes( 0 )
				],
				__METHOD__,
				[],
				$revQuery['joins']
			);
			foreach ( $res as $row ) {
				$pageid = $row->rev_page;
				$editor = $row->rev_user_text;
				$t = $loadEditorsFor[$pageid];

				$t->addEditor( $editor );
			}
		}

		// Pull link batch data.
		$linkBatch->execute();

		$threads = [];

		// Fill and return an array with the threads that were actually requested.
		foreach ( $rows as $row ) {
			$threads[$row->thread_id] = Threads::$cache_by_id[$row->thread_id];
		}

		return $threads;
	}

	/**
	 * @return User|null the User object representing the author of the first revision
	 * (or null, if the database is screwed up).
	 */
	public function loadOriginalAuthorFromRevision() {
		$this->dieIfHistorical();

		$dbr = wfGetDB( DB_REPLICA );

		$article = $this->root();

		$revQuery = self::getRevisionQueryInfo();
		$line = $dbr->selectRow(
			$revQuery['tables'],
			[ 'rev_user_text' => $revQuery['fields']['rev_user_text'] ],
			[ 'rev_page' => $article->getId() ],
			__METHOD__,
			[
				'ORDER BY' => 'rev_timestamp',
				'LIMIT'   => '1'
			],
			$revQuery['joins']
		);
		if ( $line ) {
			return User::newFromName( $line->rev_user_text, false );
		} else {
			return null;
		}
	}

	public static function recursiveGetReplyCount( $thread, $level = 1 ) {
		if ( $level > 80 ) {
			return 1;
		}

		$count = 0;

		foreach ( $thread->replies() as $reply ) {
			if ( $thread->type != Threads::TYPE_DELETED ) {
				$count++;
				$count += self::recursiveGetReplyCount( $reply, $level + 1 );
			}
		}

		return $count;
	}

	// Lazy updates done whenever a thread is loaded.
	// Much easier than running a long-running maintenance script.
	public function doLazyUpdates() {
		if ( $this->isHistorical() ) {
			return; // Don't do lazy updates on stored historical threads.
		}

		// This is an invocation guard to avoid infinite recursion when fixing a
		// missing ancestor.
		static $doingUpdates = false;
		if ( $doingUpdates ) {
			return;
		}
		$doingUpdates = true;

		// Fix missing ancestry information.
		// (there was a bug where this was not saved properly)
		if ( $this->parentId && !$this->ancestorId ) {
			$this->fixMissingAncestor();
		}

		$ancestor = $this->topmostThread();

		$set = [];

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
		if ( $this->subject() != $ancestor->subject() ) {
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

		// Check for article being in subject, not talk namespace.
		// If the page is non-LiquidThreads and it's in subject-space, we'll assume it's meant
		// to be on the corresponding talk page, but only if the talk-page is a LQT page.
		// (Previous versions stored the subject page, for some totally bizarre reason)
		// Old versions also sometimes store the thread page for trace threads as the
		// article, not as the root.
		// Trying not to exacerbate this by moving it to be the 'Thread talk' page.
		$articleTitle = $this->getTitle();
		global $wgLiquidThreadsMigrate;
		if ( !LqtDispatch::isLqtPage( $articleTitle ) && !$articleTitle->isTalkPage() &&
			LqtDispatch::isLqtPage( $articleTitle->getTalkPage() ) &&
			$articleTitle->getNamespace() != NS_LQT_THREAD &&
			$wgLiquidThreadsMigrate
		) {
			$newTitle = $articleTitle->getTalkPage();
			$newArticle = new Article( $newTitle, 0 );

			$set['thread_article_namespace'] = $newTitle->getNamespace();
			$set['thread_article_title'] = $newTitle->getDBkey();

			$this->articleNamespace = $newTitle->getNamespace();
			$this->articleTitle = $newTitle->getDBkey();
			$this->articleId = $newTitle->getArticleID();

			$this->article = $newArticle;
		}

		// Check for article corruption from incomplete thread moves.
		// (thread moves only updated this on immediate replies, not replies to replies etc)
		if ( !$ancestor->getTitle()->equals( $this->getTitle() ) ) {
			$title = $ancestor->getTitle();
			$set['thread_article_namespace'] = $title->getNamespace();
			$set['thread_article_title'] = $title->getDBkey();

			$this->articleNamespace = $title->getNamespace();
			$this->articleTitle = $title->getDBkey();
			$this->articleId = $title->getArticleID();

			$this->article = $ancestor->article();
		}

		// Check for invalid/missing articleId
		$articleTitle = null;
		$dbTitle = Title::makeTitleSafe( $this->articleNamespace, $this->articleTitle );
		if ( $this->articleId && isset( self::$titleCacheById[$this->articleId] ) ) {
			// If it corresponds to a title, the article obviously exists.
			$articleTitle = self::$titleCacheById[$this->articleId];
			$this->article = new Article( $articleTitle, 0 );
		} elseif ( $this->articleId ) {
			$articleTitle = Title::newFromID( $this->articleId );
		}

		// If still unfilled, the article ID referred to is no longer valid. Re-fill it
		// from the namespace/title pair if an article ID is provided
		if ( !$articleTitle && ( $this->articleId != 0 || $dbTitle->getArticleID() != 0 ) ) {
			$articleTitle = $dbTitle;
			$this->articleId = $articleTitle->getArticleID();
			$this->article = new Article( $dbTitle, 0 );

			$set['thread_article_id'] = $this->articleId;
			wfDebug(
				"Unfilled or non-existent thread_article_id, refilling to {$this->articleId}\n"
			);

			// There are probably problems on the rest of the article, trigger a small update
			Threads::synchroniseArticleData( $this->article, 100, 'cascade' );
		} elseif ( $articleTitle && !$articleTitle->equals( $dbTitle ) ) {
			// The page was probably moved and this was probably not updated.
			wfDebug(
				"Article ID/Title discrepancy, resetting NS/Title to article provided by ID\n"
			);
			$this->articleNamespace = $articleTitle->getNamespace();
			$this->articleTitle = $articleTitle->getDBkey();

			$set['thread_article_namespace'] = $articleTitle->getNamespace();
			$set['thread_article_title'] = $articleTitle->getDBkey();

			// There are probably problems on the rest of the article, trigger a small update
			Threads::synchroniseArticleData( $this->article, 100, 'cascade' );
		}

		// Check for unfilled signature field. This field hasn't existed until
		// recently.
		if ( is_null( $this->signature ) ) {
			// Grab our signature.
			$sig = LqtView::getUserSignature( $this->author() );

			$set['thread_signature'] = $sig;
			$this->setSignature( $sig );
		}

		if ( count( $set ) ) {
			$dbw = wfGetDB( DB_MASTER );

			$dbw->update( 'thread', $set, [ 'thread_id' => $this->id() ], __METHOD__ );
		}

		// Done
		$doingUpdates = false;
	}

	public function addReply( $thread ) {
		$thread->setSuperThread( $this );

		if ( is_array( $this->replies ) ) {
			$this->replies[$thread->id()] = $thread;
		} else {
			$this->replies();
			$this->replies[$thread->id()] = $thread;
		}

		// Increment reply count.
		$this->incrementReplyCount( $thread->replyCount() + 1 );
	}

	public function removeReply( $thread ) {
		if ( is_object( $thread ) ) {
			$thread = $thread->id();
		}

		$this->replies();

		unset( $this->replies[$thread] );

		// Also, decrement the reply count.
		$threadObj = Threads::withId( $thread );
		$this->decrementReplyCount( 1 + $threadObj->replyCount() );
	}

	public function checkReplies( $replies ) {
		// Fixes a bug where some history pages were not working, before
		// superthread was properly instance-cached.
		if ( $this->isHistorical() ) {
			return;
		}
		foreach ( $replies as $reply ) {
			if ( !$reply->hasSuperthread() ) {
				throw new Exception( "Post " . $this->id() .
				" has contaminated reply " . $reply->id() .
				". Found no superthread." );
			}

			if ( $reply->superthread()->id() != $this->id() ) {
				throw new Exception( "Post " . $this->id() .
				" has contaminated reply " . $reply->id() .
				". Expected " . $this->id() . ", got " .
				$reply->superthread()->id() );
			}
		}
	}

	public function replies() {
		if ( !$this->id() ) {
			return [];
		}

		if ( !is_null( $this->replies ) ) {
			$this->checkReplies( $this->replies );
			return $this->replies;
		}

		$this->dieIfHistorical();

		// Check cache
		if ( isset( self::$replyCacheById[$this->id()] ) ) {
			$this->replies = self::$replyCacheById[$this->id()];
			return $this->replies;
		}

		$this->replies = [];

		$dbr = wfGetDB( DB_REPLICA );

		$res = $dbr->select( 'thread', '*',
					[ 'thread_parent' => $this->id(),
					'thread_type != ' . $dbr->addQuotes( Threads::TYPE_DELETED ) ],
					__METHOD__ );

		$rows = [];
		foreach ( $res as $row ) {
			$rows[] = $row;
		}

		$this->replies = self::bulkLoad( $rows );

		$this->checkReplies( $this->replies );

		return $this->replies;
	}

	public function setSuperthread( $thread ) {
		if ( $thread == null ) {
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

	public function superthread() {
		if ( !$this->hasSuperthread() ) {
			return null;
		} elseif ( $this->superthread ) {
			return $this->superthread;
		} else {
			$this->dieIfHistorical();
			$this->superthread = Threads::withId( $this->parentId );
			return $this->superthread;
		}
	}

	public function hasSuperthread() {
		return !$this->isTopmostThread();
	}

	public function topmostThread() {
		if ( $this->isTopmostThread() ) {
			$this->ancestor = $this;
			return $this->ancestor;
		} elseif ( $this->ancestor ) {
			return $this->ancestor;
		} else {
			$this->dieIfHistorical();

			$thread = Threads::withId( $this->ancestorId );

			if ( !$thread ) {
				$thread = $this->fixMissingAncestor();
			}

			$this->ancestor = $thread;

			return $thread;
		}
	}

	public function setAncestor( $newAncestor ) {
		if ( is_object( $newAncestor ) ) {
			$this->ancestorId = $newAncestor->id();
		} else {
			$this->ancestorId = $newAncestor;
		}
	}

	// Due to a bug in earlier versions, the topmost thread sometimes isn't there.
	// Fix the corruption by repeatedly grabbing the parent until we hit the topmost thread.
	public function fixMissingAncestor() {
		$thread = $this;

		$this->dieIfHistorical();

		while ( !$thread->isTopmostThread() ) {
			$thread = $thread->superthread();
		}

		$this->ancestorId = $thread->id();

		$dbw = wfGetDB( DB_MASTER );
		$dbw->update( 'thread', [ 'thread_ancestor' => $thread->id() ],
				[ 'thread_id' => $this->id() ], __METHOD__ );

		return $thread;
	}

	public function isTopmostThread() {
		return $this->ancestorId == $this->id ||
				$this->parentId == 0;
	}

	public function setArticle( $a ) {
		$this->articleId = $a->getID();
		$this->articleNamespace = $a->getTitle()->getNamespace();
		$this->articleTitle = $a->getTitle()->getDBkey();
		$this->touch();
	}

	public function touch() {
		// Nothing here yet
	}

	public function article() {
		if ( $this->article ) {
			return $this->article;
		}

		if ( !is_null( $this->articleId ) ) {
			if ( isset( self::$articleCacheById[$this->articleId] ) ) {
				return self::$articleCacheById[$this->articleId];
			}

			if ( isset( self::$titleCacheById[$this->articleId] ) ) {
				$title = self::$titleCacheById[$this->articleId];
			} else {
				$title = Title::newFromID( $this->articleId );
			}

			if ( $title ) {
				$article = new Article( $title, 0 );
				self::$articleCacheById[$this->articleId] = $article;
			}
		}

		if ( isset( $article ) && $article->exists() ) {
			$this->article = $article;
			return $article;
		} else {
			$title = Title::makeTitle( $this->articleNamespace, $this->articleTitle );
			return new Article( $title, 0 );
		}
	}

	/**
	 * @return int
	 */
	public function id() {
		return $this->id;
	}

	public function ancestorId() {
		return $this->ancestorId;
	}

	// The 'root' is the page in the Thread namespace corresponding to this thread.
	public function root() {
		if ( !$this->rootId ) {
			return null;
		}
		if ( !$this->root ) {
			if ( isset( self::$articleCacheById[$this->rootId] ) ) {
				$this->root = self::$articleCacheById[$this->rootId];
				return $this->root;
			}

			if ( isset( self::$titleCacheById[$this->rootId] ) ) {
				$title = self::$titleCacheById[$this->rootId];
			} else {
				$title = Title::newFromID( $this->rootId );
			}

			if ( !$title && $this->type() != Threads::TYPE_DELETED ) {
				if ( !$this->isHistorical() ) {
					$this->delete( '', false /* !commit */ );
				} else {
					$this->type = Threads::TYPE_DELETED;
				}
			}

			if ( !$title ) {
				return null;
			}

			$this->root = new Article( $title, 0 );
		}
		return $this->root;
	}

	public function editedness() {
		return $this->editedness;
	}

	public function summary() {
		if ( !$this->summaryId ) {
			return null;
		}

		if ( !$this->summary ) {
			$title = Title::newFromID( $this->summaryId );

			if ( !$title ) {
				wfDebug( __METHOD__ . ": supposed summary doesn't exist" );
				$this->summaryId = null;
				return null;
			}

			$this->summary = new Article( $title, 0 );

		}

		return $this->summary;
	}

	public function hasSummary() {
		return $this->summaryId != null;
	}

	public function setSummary( $post ) {
		// Weird -- this was setting $this->summary to NULL before I changed it.
		// If there was some reason why, please tell me! -- Andrew
		$this->summary = $post;
		$this->summaryId = $post->getID();
	}

	public function title() {
		if ( is_object( $this->root() ) ) {
			return $this->root()->getTitle();
		} else {
			// wfWarn( "Thread ".$this->id()." has no title." );
			return null;
		}
	}

	public static function splitIncrementFromSubject( $subject_string ) {
		preg_match( '/^(.*) \((\d+)\)$/', $subject_string, $matches );
		if ( count( $matches ) != 3 ) {
			throw new Exception(
				__METHOD__ . ": thread subject has no increment: " . $subject_string
			);
		} else {
			return $matches;
		}
	}

	public function subject() {
		return $this->subject;
	}

	public function formattedSubject() {
		return LqtView::formatSubject( $this->subject() );
	}

	public function setSubject( $subject ) {
		$this->subject = $subject;

		foreach ( $this->replies() as $reply ) {
			$reply->setSubject( $subject );
		}
	}

	// Deprecated, use subject().
	public function subjectWithoutIncrement() {
		return $this->subject();
	}

	// Currently equivalent to isTopmostThread.
	public function hasDistinctSubject() {
		return $this->isTopmostThread();
	}

	public function hasSubthreads() {
		return count( $this->replies() ) != 0;
	}

	// Synonym for replies()
	public function subthreads() {
		return $this->replies();
	}

	public function modified() {
		return $this->modified;
	}

	public function created() {
		return $this->created;
	}

	public function type() {
		return $this->type;
	}

	public function setType( $t ) {
		$this->type = $t;
	}

	public function redirectThread() {
		$rev = Revision::newFromId( $this->root()->getLatest() );
		$rtitle = ContentHandler::makeContent(
			$rev->getContent( Revision::RAW )->getNativeData(),
			null,
			CONTENT_MODEL_WIKITEXT
		)->getRedirectTarget();
		if ( !$rtitle ) {
			return null;
		}

		$this->dieIfHistorical();
		$rthread = Threads::withRoot( new Article( $rtitle, 0 ) );
		return $rthread;
	}

	// This only makes sense when called from the hook, because it uses the hook's
	// default behavior to check whether this thread itself is protected, so you'll
	// get false negatives if you use it from some other context.
	public function getRestrictions( $action, &$result ) {
		if ( $this->hasSuperthread() ) {
			$parent_restrictions = $this->superthread()->root()->getTitle()
				->getRestrictions( $action );
		} else {
			$parent_restrictions = $this->getTitle()->getRestrictions( $action );
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

	public function getAnchorName() {
		$wantedId = $this->subject() . "_{$this->id()}";
		return Sanitizer::escapeId( $wantedId );
	}

	public function updateHistory() {
	}

	public function setAuthor( $user ) {
		$this->authorId = $user->getId();
		$this->authorName = $user->getName();
	}

	// Load all lazy-loaded data in prep for (e.g.) serialization.
	public function loadAllData() {
		// Make sure superthread and topmost thread are loaded.
		$this->superthread();
		$this->topmostThread();

		// Make sure replies, and all the data therein, is loaded.
		foreach ( $this->replies() as $reply ) {
			$reply->loadAllData();
		}
	}

	// On serialization, load all data because it will be different in the DB when we wake up.
	public function __sleep() {
		$this->loadAllData();

		$fields = array_keys( get_object_vars( $this ) );

		// Filter out article objects, there be dragons (or unserialization problems)
		$fields = array_diff( $fields, [ 'root', 'article', 'summary', 'sleeping',
			'dbVersion' ] );

		return $fields;
	}

	public function __wakeup() {
		// Mark as historical.
		$this->isHistorical = true;
	}

	// This is a safety valve that makes sure that the DB is NEVER touched by a historical
	// thread (even for reading, because the data will be out of date).
	public function dieIfHistorical() {
		if ( $this->isHistorical() ) {
			throw new Exception( "Attempted write or DB operation on historical thread" );
		}
	}

	/**
	 * @return int|null
	 */
	public function rootRevision() {
		if ( !$this->isHistorical() ||
			!isset( $this->topmostThread()->threadRevision ) ||
			!$this->root()
		) {
			return null;
		}

		$dbr = wfGetDB( DB_REPLICA );

		$revision = $this->topmostThread()->threadRevision;
		$timestamp = $dbr->timestamp( $revision->getTimestamp() );

		$conds = [
			'rev_timestamp<=' . $dbr->addQuotes( $timestamp ),
			'page_namespace' => $this->root()->getTitle()->getNamespace(),
			'page_title' => $this->root()->getTitle()->getDBkey(),
		];

		$join_conds = [ 'page' => [ 'JOIN', 'rev_page=page_id' ] ];

		$row = $dbr->selectRow( [ 'revision', 'page' ], '*', $conds, __METHOD__,
			[ 'ORDER BY' => 'rev_timestamp DESC' ], $join_conds );

		return $row->rev_id;
	}

	public function sortkey() {
		return $this->sortkey;
	}

	public function setSortKey( $k = null ) {
		if ( is_null( $k ) ) {
			$k = wfTimestamp( TS_MW );
		}

		$this->sortkey = $k;
	}

	public function replyWithId( $id ) {
		if ( $this->id() == $id ) {
			return $this;
		}

		foreach ( $this->replies() as $reply ) {
			$obj = $reply->replyWithId( $id );
			if ( $obj ) {
				return $obj;
			}
		}

		return null;
	}

	public static function createdSortCallback( $a, $b ) {
		$a = $a->created();
		$b = $b->created();

		if ( $a == $b ) {
			return 0;
		} elseif ( $a > $b ) {
			return 1;
		} else {
			return - 1;
		}
	}

	public function split( $newSubject, $reason = '', $newSortkey = null ) {
		$oldTopThread = $this->topmostThread();
		$oldParent = $this->superthread();

		$original = $this->dbVersion;

		self::recursiveSet( $this, $newSubject, $this, null );

		$oldParent->removeReply( $this );

		$bump = null;
		if ( !is_null( $newSortkey ) ) {
			$this->setSortKey( $newSortkey );
			$bump = false;
		}

		// For logging purposes, will be reset by the time this call returns.
		$this->dbVersion = $original;

		$this->commitRevision( Threads::CHANGE_SPLIT, null, $reason, $bump );
		$oldTopThread->commitRevision( Threads::CHANGE_SPLIT_FROM, $this, $reason );
	}

	public function moveToParent( $newParent, $reason = '' ) {
		$newSubject = $newParent->subject();

		$original = $this->dbVersion;

		$oldTopThread = $newParent->topmostThread();
		$oldParent = $this->superthread();
		$newTopThread = $newParent->topmostThread();

		self::recursiveSet( $this, $newSubject, $newTopThread, $newParent );

		$newParent->addReply( $this );

		if ( $oldParent ) {
			$oldParent->removeReply( $this );
		}

		$this->dbVersion = $original;

		$oldTopThread->commitRevision( Threads::CHANGE_MERGED_FROM, $this, $reason );
		$newParent->commitRevision( Threads::CHANGE_MERGED_TO, $this, $reason );
	}

	public static function recursiveSet( $thread, $subject, $ancestor, $superthread = false ) {
		$thread->setSubject( $subject );
		$thread->setAncestor( $ancestor->id() );

		if ( $superthread !== false ) {
			$thread->setSuperThread( $superthread );
		}

		$thread->save();

		foreach ( $thread->replies() as $subThread ) {
			self::recursiveSet( $subThread, $subject, $ancestor );
		}
	}

	public static function validateSubject( $subject, &$title, $replyTo, $article ) {
		$t = null;
		$ok = true;

		while ( !$t ) {
			try {
				global $wgUser;

				if ( !$replyTo && $subject ) {
					$t = Threads::newThreadTitle( $subject, $article );
				} elseif ( $replyTo ) {
					$t = Threads::newReplyTitle( $replyTo, $wgUser );
				}

				if ( $t ) {
					break;
				}
			} catch ( Exception $e ) {
			}

			$subject = md5( (string)mt_rand() ); // Just a random title
			$ok = false;
		}

		$title = $t;

		return $ok;
	}

	/* N.B. Returns true, or a string with either thread or talkpage, noting which is
	   protected */
	public function canUserReply( User $user, $rigor = 'secure' ) {
		$threadRestrictions = $this->topmostThread()->title()->getRestrictions( 'reply' );
		$talkpageRestrictions = $this->getTitle()->getRestrictions( 'reply' );

		$threadRestrictions = array_fill_keys( $threadRestrictions, 'thread' );
		$talkpageRestrictions = array_fill_keys( $talkpageRestrictions, 'talkpage' );

		$restrictions = array_merge( $threadRestrictions, $talkpageRestrictions );

		foreach ( $restrictions as $right => $source ) {
			if ( $right == 'sysop' ) {
				$right = 'protect';
			}
			if ( !$user->isAllowed( $right ) ) {
				return $source;
			}
		}

		return self::canUserCreateThreads( $user, $rigor );
	}

	public static function canUserPost( $user, $talkpage, $rigor = 'secure' ) {
		$restrictions = $talkpage->getTitle()->getRestrictions( 'newthread' );

		foreach ( $restrictions as $right ) {
			if ( !$user->isAllowed( $right ) ) {
				return false;
			}
		}

		return self::canUserCreateThreads( $user, $rigor );
	}

	// Generally, not some specific page
	public static function canUserCreateThreads( $user, $rigor = 'secure' ) {
		$userText = $user->getName();

		static $canCreateNew = [];
		if ( !isset( $canCreateNew[$userText] ) ) {
			$title = Title::makeTitleSafe(
				NS_LQT_THREAD, 'Test title for LQT thread creation check' );
			$canCreateNew[$userText] = $title->userCan( 'create', $user, $rigor )
				&& $title->userCan( 'edit', $user, $rigor );
		}

		return $canCreateNew[$userText];
	}

	public function signature() {
		return $this->signature;
	}

	public function setSignature( $sig ) {
		$sig = LqtView::signaturePST( $sig, $this->author() );
		$this->signature = $sig;
	}

	public function editors() {
		if ( is_null( $this->editors ) ) {
			if ( $this->editedness() < Threads::EDITED_BY_AUTHOR ) {
				return [];
			} elseif ( $this->editedness == Threads::EDITED_BY_AUTHOR ) {
				return [ $this->author()->getName() ];
			}

			// Load editors
			$this->editors = [];

			$dbr = wfGetDB( DB_REPLICA );
			$revQuery = self::getRevisionQueryInfo();
			$res = $dbr->select(
				$revQuery['tables'],
				[ 'rev_user_text' => $revQuery['fields']['rev_user_text'] ],
				[
					'rev_page' => $this->root()->getId(),
					'rev_parent_id != ' . $dbr->addQuotes( 0 )
				],
				__METHOD__,
				[],
				$revQuery['joins']
			);

			foreach ( $res as $row ) {
				$this->editors[$row->rev_user_text] = 1;
			}

			$this->editors = array_keys( $this->editors );
		}

		return $this->editors;
	}

	public function setEditors( $e ) {
		$this->editors = $e;
	}

	public function addEditor( $e ) {
		$this->editors[] = $e;
		$this->editors = array_unique( $this->editors );
	}

	/**
	 * @return Title the Title object for the article this thread is attached to.
	 */
	public function getTitle() {
		return $this->article()->getTitle();
	}

	public function getReactions( $requestedType = null ) {
		if ( is_null( $this->reactions ) ) {
			if ( isset( self::$reactionCacheById[$this->id()] ) ) {
				$this->reactions = self::$reactionCacheById[$this->id()];
			} else {
				$reactions = [];

				$dbr = wfGetDB( DB_REPLICA );

				$res = $dbr->select( 'thread_reaction',
						[ 'tr_thread' => $this->id() ],
						__METHOD__ );

				foreach ( $res as $row ) {
					$user = $row->tr_user_text;
					$type = $row->tr_type;
					$info = [
						'type' => $type,
						'user-id' => $row->tr_user,
						'user-name' => $row->tr_user_text,
						'value' => $row->tr_value,
					];

					if ( !isset( $reactions[$type] ) ) {
						$reactions[$type] = [];
					}

					$reactions[$type][$user] = $info;
				}

				$this->reactions = $reactions;
			}
		}

		if ( is_null( $requestedType ) ) {
			return $this->reactions;
		} else {
			return $this->reactions[$requestedType];
		}
	}

	public function addReaction( $user, $type, $value ) {
		$info = [
			'type' => $type,
			'user-id' => $user->getId(),
			'user-name' => $user->getName(),
			'value' => $value,
		];

		if ( !isset( $this->reactions[$type] ) ) {
			$this->reactions[$type] = [];
		}

		$this->reactions[$type][$user->getName()] = $info;

		$row = [
			'tr_type' => $type,
			'tr_thread' => $this->id(),
			'tr_user' => $user->getId(),
			'tr_user_text' => $user->getName(),
			'tr_value' => $value,
		];

		$dbw = wfGetDB( DB_MASTER );

		$dbw->insert( 'thread_reaction', $row, __METHOD__ );
	}

	public function deleteReaction( $user, $type ) {
		$dbw = wfGetDB( DB_MASTER );

		if ( isset( $this->reactions[$type][$user->getName()] ) ) {
			unset( $this->reactions[$type][$user->getName()] );
		}

		$dbw->delete( 'thread_reaction',
				[ 'tr_thread' => $this->id(),
					'tr_user' => $user->getId(),
					'tr_type' => $type ],
				__METHOD__ );
	}

	/**
	 * @return array
	 */
	private static function getRevisionQueryInfo() {
		$info = Revision::getQueryInfo();
		if ( !isset( $info['fields']['rev_user_text'] ) ) {
			$info['fields']['rev_user_text'] = 'rev_user_text';
		}

		return $info;
	}
}
