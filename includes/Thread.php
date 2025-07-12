<?php

use MediaWiki\Content\ContentHandler;
use MediaWiki\Context\RequestContext;
use MediaWiki\Logging\LogPage;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\Article;
use MediaWiki\Parser\Sanitizer;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;
use Wikimedia\Rdbms\SelectQueryBuilder;

class Thread {
	/* SCHEMA changes must be reflected here. */

	/* ID references to other objects that are loaded on demand: */
	/** @var int|null */
	protected $rootId;
	/** @var int|null */
	protected $articleId;
	/** @var int|null */
	protected $summaryId;
	/** @var int|null */
	protected $ancestorId;
	/** @var int|null */
	protected $parentId;

	/* Actual objects loaded on demand from the above when accessors are called: */
	/** @var Article|null */
	protected $root;
	/** @var Article|null */
	protected $article;
	/** @var Article|null */
	protected $summary;
	/** @var self|null */
	protected $superthread;
	/** @var self|null */
	protected $ancestor;

	/** @var int|null namespace of Subject page of the talkpage we're attached to */
	protected $articleNamespace;
	/** @var string|null Subject page of the talkpage we're attached to: */
	protected $articleTitle;

	/** @var string Timestamp */
	protected $modified;
	/** @var string Timestamp */
	protected $created;
	/** @var string Timestamp */
	protected $sortkey;

	/** @var int */
	protected $id;
	/** @var int|null */
	protected $type;
	/** @var string|null */
	protected $subject;
	/** @var int */
	protected $authorId;
	/** @var string|null */
	protected $authorName;
	/** @var string|null */
	protected $signature;
	/** @var int */
	protected $replyCount;

	/** @var bool|null */
	protected $allDataLoaded;

	/** @var bool */
	protected $isHistorical = false;

	/** @var int|null */
	protected $rootRevision;

	/** @var int Flag about who has edited or replied to this thread. */
	public $editedness;
	/** @var string[]|null */
	protected $editors = null;

	/** @var Thread[]|null */
	protected $replies;
	/** @var array[]|null */
	protected $reactions;

	/** @var self|null */
	public $dbVersion; // A copy of the thread as it exists in the database.
	/** @var ThreadRevision */
	public $threadRevision;

	/** @var Title[] */
	public static $titleCacheById = [];
	/** @var self[][] */
	public static $replyCacheById = [];
	/** @var Article[] */
	public static $articleCacheById = [];
	/** @var array[][] */
	public static $reactionCacheById = [];

	/** @var int[] */
	public static $VALID_TYPES = [
		Threads::TYPE_NORMAL, Threads::TYPE_MOVED, Threads::TYPE_DELETED ];

	public function isHistorical() {
		return $this->isHistorical;
	}

	public static function create(
		$root,
		Article $article,
		User $user,
		?self $superthread,
		$type = Threads::TYPE_NORMAL,
		$subject = '',
		$summary = '',
		$bump = null,
		$signature = null
	) {
		$thread = new self( null );

		if ( !in_array( $type, self::$VALID_TYPES ) ) {
			throw new UnexpectedValueException( __METHOD__ . ": invalid change type $type." );
		}

		if ( $superthread ) {
			$change_type = Threads::CHANGE_REPLY_CREATED;
		} else {
			$change_type = Threads::CHANGE_NEW_THREAD;
		}

		$thread->setAuthor( $user );

		if ( is_object( $root ) ) {
			$thread->setRoot( $root );
		} else {
			$thread->setRootId( $root );
		}

		$thread->setSuperthread( $superthread );
		$thread->setArticle( $article );
		$thread->setSubject( $subject );
		$thread->setType( $type );

		if ( $signature !== null ) {
			$thread->setSignature( $signature );
		}

		$thread->insert();

		if ( $superthread ) {
			$superthread->addReply( $thread );

			$superthread->commitRevision( $change_type, $user, $thread, $summary, $bump );
		} else {
			ThreadRevision::create( $thread, $change_type, $user );
		}

		// Create talk page
		Threads::createTalkpageIfNeeded( $article->getPage() );

		// Notifications
		NewMessages::writeMessageStateForUpdatedThread( $thread, $change_type, $user );

		$services = MediaWikiServices::getInstance();
		$userOptionsLookup = $services->getUserOptionsLookup();
		$watchlistManager = $services->getWatchlistManager();
		if ( $userOptionsLookup->getOption( $user, 'lqt-watch-threads', false ) ) {
			$watchlistManager->addWatch( $user, $thread->topmostThread()->root()->getTitle() );
		}

		return $thread;
	}

	public function insert() {
		$this->dieIfHistorical();

		if ( $this->id() ) {
			throw new LogicException( "Attempt to insert a thread that already exists." );
		}

		$dbw = MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();

		$row = $this->getRow();

		$dbw->newInsertQueryBuilder()
			->insertInto( 'thread' )
			->row( $row )
			->caller( __METHOD__ )
			->execute();
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

	/**
	 * @param Article $article
	 */
	public function setRoot( $article ) {
		$this->rootId = $article->getPage()->getId();
		$this->root = $article;

		if ( $article->getTitle()->getNamespace() != NS_LQT_THREAD ) {
			throw new LogicException( "Attempt to set thread root to a non-Thread page" );
		}
	}

	public function setRootId( $article ) {
		$this->rootId = $article;
		$this->root = null;
	}

	/**
	 * @param int $change_type
	 * @param User $user
	 * @param self|null $change_object
	 * @param string $reason
	 * @param bool|null $bump
	 *
	 * @throws Exception
	 */
	public function commitRevision(
		$change_type,
		User $user,
		$change_object = null,
		$reason = "",
		$bump = null
	) {
		$this->dieIfHistorical();

		global $wgThreadActionsNoBump;
		$bump ??= !in_array( $change_type, $wgThreadActionsNoBump );
		if ( $bump ) {
			$this->sortkey = wfTimestamp( TS_MW );
		}

		$original = $this->dbVersion;
		if ( $original->signature() != $this->signature() ) {
			$this->logChange(
				Threads::CHANGE_EDITED_SIGNATURE,
				$original,
				null,
				$reason
			);
		}

		$this->modified = wfTimestampNow();
		$this->updateEditedness( $change_type, $user );
		$this->save( __METHOD__ . "/" . wfGetCaller() );

		$topmost = $this->topmostThread();
		$topmost->modified = wfTimestampNow();
		if ( $bump ) {
			$topmost->setSortKey( wfTimestamp( TS_MW ) );
		}
		$topmost->save();

		ThreadRevision::create( $this, $change_type, $user, $change_object, $reason );
		$this->logChange( $change_type, $original, $change_object, $reason );

		if ( $change_type == Threads::CHANGE_EDITED_ROOT ) {
			NewMessages::writeMessageStateForUpdatedThread( $this, $change_type, $user );
		}
	}

	/**
	 * @param int $change_type
	 * @param self $original
	 * @param self|null $change_object
	 * @param string|null $reason
	 */
	public function logChange(
		$change_type,
		$original,
		$change_object = null,
		$reason = ''
	) {
		$log = new LogPage( 'liquidthreads' );
		$user = $this->author();

		$reason ??= '';

		switch ( $change_type ) {
			case Threads::CHANGE_MOVED_TALKPAGE:
				$log->addEntry(
					'move',
					$this->title(),
					$reason,
					[ $original->getTitle(), $this->getTitle() ],
					$user
				);
				break;
			case Threads::CHANGE_SPLIT:
				$log->addEntry(
					'split',
					$this->title(),
					$reason,
					[ $this->subject(), $original->superthread()->title() ],
					$user
				);
				break;
			case Threads::CHANGE_EDITED_SUBJECT:
				$log->addEntry(
					'subjectedit',
					$this->title(),
					$reason,
					[ $original->subject(), $this->subject() ],
					$user
				);
				break;
			case Threads::CHANGE_MERGED_TO:
				$oldParent = $change_object->dbVersion->isTopmostThread()
						? ''
						: $change_object->dbVersion->superthread()->title();

				$log->addEntry(
					'merge',
					$this->title(),
					$reason,
					[ $oldParent, $change_object->superthread()->title() ],
					$user
				);
				break;
			case Threads::CHANGE_ADJUSTED_SORTKEY:
				$log->addEntry(
					'resort',
					$this->title(),
					$reason,
					[ $original->sortkey(), $this->sortkey() ],
					$user
				);
				break;
			case Threads::CHANGE_EDITED_SIGNATURE:
				$log->addEntry(
					'signatureedit',
					$this->title(),
					$reason,
					[ $original->signature(), $this->signature() ],
					$user
				);
				break;
		}
	}

	private function updateEditedness( $change_type, User $user ) {
		if ( $change_type == Threads::CHANGE_REPLY_CREATED
				&& $this->editedness == Threads::EDITED_NEVER ) {
			$this->editedness = Threads::EDITED_HAS_REPLY;
		} elseif ( $change_type == Threads::CHANGE_EDITED_ROOT ) {
			$originalAuthor = $this->author();

			if ( ( $user->getId() == 0 && $originalAuthor->getName() != $user->getName() )
					|| $user->getId() != $originalAuthor->getId() ) {
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

		$dbw = MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();

		if ( !$fname ) {
			$fname = __METHOD__ . "/" . wfGetCaller();
		} else {
			$fname = __METHOD__ . "/" . $fname;
		}

		$dbw->newUpdateQueryBuilder()
			->update( 'thread' )
			->set( $this->getRow() )
			->where( [ 'thread_id' => $this->id, ] )
			->caller( $fname )
			->execute();

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

		$dbw = MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();

		// If there's no root, bail out with an error message
		if ( !$this->rootId && !( $this->type & Threads::TYPE_DELETED ) ) {
			throw new LogicException( "Non-deleted thread saved with empty root ID" );
		}

		if ( $this->replyCount < -1 ) {
			wfWarn(
				"Saving thread $id with negative reply count {$this->replyCount} " .
					wfGetAllCallers()
			);
			$this->replyCount = -1;
		}

		$contLang = MediaWikiServices::getInstance()->getContentLanguage();
		// Reflect schema changes here.
		$row = [
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
			'thread_signature' => $contLang->truncateForDatabase( $this->signature, 255, '' ),
		];
		if ( $id ) {
			$row['thread_id'] = $id;
		}

		return $row;
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
		$user = RequestContext::getMain()->getUser(); // Need to inject

		if ( $commit ) {
			$this->commitRevision( Threads::CHANGE_DELETED, $user, $this, $reason );
		} else {
			$this->save( __METHOD__ );
		}
		/* Mark thread as read by all users, or we get blank thingies in New Messages. */

		$this->dieIfHistorical();

		$dbw = MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();

		$dbw->newDeleteQueryBuilder()
			->deleteFrom( 'user_message_state' )
			->where( [ 'ums_thread' => $this->id() ] )
			->caller( __METHOD__ )
			->execute();

		// Fix reply count.
		$t = $this->superthread();

		if ( $t ) {
			$t->decrementReplyCount( 1 + $this->replyCount() );
			$t->save();
		}
	}

	public function undelete( $reason ) {
		$this->type = Threads::TYPE_NORMAL;
		$user = RequestContext::getMain()->getUser(); // Need to inject
		$this->commitRevision( Threads::CHANGE_UNDELETED, $user, $this, $reason );

		// Fix reply count.
		$t = $this->superthread();
		if ( $t ) {
			$t->incrementReplyCount( 1 );
			$t->save();
		}
	}

	public function moveToPage( $title, $reason, $leave_trace, User $user ) {
		if ( !$this->isTopmostThread() ) {
			throw new LogicException( "Attempt to move non-toplevel thread to another page" );
		}

		$this->dieIfHistorical();

		$dbw = MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();

		$oldTitle = $this->getTitle();
		$newTitle = $title;

		$new_articleNamespace = $title->getNamespace();
		$new_articleTitle = $title->getDBkey();
		$new_articleID = $title->getArticleID();

		if ( !$new_articleID ) {
			$page = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $newTitle );
			Threads::createTalkpageIfNeeded( $page );
			$new_articleID = $page->getId();
		}

		// Update on *all* subthreads.
		$dbw->newUpdateQueryBuilder()
			->update( 'thread' )
			->set( [
				'thread_article_namespace' => $new_articleNamespace,
				'thread_article_title' => $new_articleTitle,
				'thread_article_id' => $new_articleID,
			] )
			->where( [ 'thread_ancestor' => $this->id() ] )
			->caller( __METHOD__ )
			->execute();

		$this->articleNamespace = $new_articleNamespace;
		$this->articleTitle = $new_articleTitle;
		$this->articleId = $new_articleID;
		$this->article = null;

		$this->commitRevision( Threads::CHANGE_MOVED_TALKPAGE, $user, null, $reason );

		// Notifications
		NewMessages::writeMessageStateForUpdatedThread( $this, $this->type, $user );

		if ( $leave_trace ) {
			$this->leaveTrace( $reason, $oldTitle, $newTitle, $user );
		}
	}

	/**
	 * Drop a note at the source location of a move, noting that a thread was moved from there.
	 *
	 * @param string $reason
	 * @param Title $oldTitle
	 * @param Title $newTitle
	 * @param User $user
	 */
	public function leaveTrace( $reason, $oldTitle, $newTitle, User $user ) {
		$this->dieIfHistorical();

		// Create redirect text
		$mwRedir = \MediaWiki\MediaWikiServices::getInstance()->getMagicWordFactory()->get( 'redirect' );
		$redirectText = $mwRedir->getSynonym( 0 ) .
			' [[' . $this->title()->getPrefixedText() . "]]\n";

		// Make the article edit.
		$traceTitle = Threads::newThreadTitle( $this->subject(), new Article( $oldTitle, 0 ) );
		$redirectArticle = new Article( $traceTitle, 0 );

		$redirectArticle->getPage()->doUserEditContent(
			ContentHandler::makeContent( $redirectText, $traceTitle ),
			$user,
			$reason,
			EDIT_NEW | EDIT_SUPPRESS_RC
		);

		// Add the trace thread to the tracking table.
		$thread = self::create(
			$redirectArticle,
			new Article( $oldTitle, 0 ),
			$user,
			null,
			Threads::TYPE_MOVED,
			$this->subject()
		);

		$thread->setSortKey( $this->sortkey() );
		$thread->save();
	}

	/**
	 * Lists total reply count, including replies to replies and such
	 *
	 * @return int
	 */
	public function replyCount() {
		// Populate reply count
		if ( $this->replyCount == -1 ) {
			if ( $this->isTopmostThread() ) {
				$dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();

				$count = $dbr->newSelectQueryBuilder()
					->select( 'COUNT(*)' )
					->from( 'thread' )
					->where( [ 'thread_ancestor' => $this->id() ] )
					->caller( __METHOD__ )
					->fetchField();
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
		$this->incrementReplyCount( -$val );
	}

	/**
	 * @param stdClass $row
	 * @return self
	 */
	public static function newFromRow( $row ) {
		$id = $row->thread_id;

		if ( isset( Threads::$cache_by_id[$id] ) ) {
			return Threads::$cache_by_id[$id];
		}

		return new self( $row );
	}

	/**
	 * @param stdClass|null $line
	 * @param null $unused
	 */
	protected function __construct( $line, $unused = null ) {
		/* SCHEMA changes must be reflected here. */

		if ( $line === null ) { // For Thread::create().
			$dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();
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

	/**
	 * Load a list of threads in bulk, including all subthreads.
	 *
	 * @param stdClass[] $rows
	 * @return self[]
	 */
	public static function bulkLoad( $rows ) {
		// Preload subthreads
		$top_thread_ids = [];
		$all_thread_rows = $rows;
		$pageIds = [];
		$linkBatch = MediaWikiServices::getInstance()->getLinkBatchFactory()->newLinkBatch();
		$userIds = [];
		$loadEditorsFor = [];

		$dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();

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
			$res = $dbr->newSelectQueryBuilder()
				->select( '*' )
				->from( 'thread' )
				->where( [
					'thread_ancestor' => $top_thread_ids,
					$dbr->expr( 'thread_type', '!=', Threads::TYPE_DELETED ),
				] )
				->caller( __METHOD__ )
				->fetchResultSet();

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
			$res = $dbr->newSelectQueryBuilder()
				->select( '*' )
				->from( 'thread_reaction' )
				->where( [ 'tr_thread' => $all_thread_ids ] )
				->caller( __METHOD__ )
				->fetchResultSet();

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
			$res = $dbr->newSelectQueryBuilder()
				->select( '*' )
				->from( 'page_restrictions' )
				->where( [ 'pr_page' => $pageIds ] )
				->caller( __METHOD__ )
				->fetchResultSet();
			foreach ( $res as $row ) {
				$restrictionRows[$row->pr_page][] = $row;
			}

			$res = $dbr->newSelectQueryBuilder()
				->select( '*' )
				->from( 'page' )
				->where( [ 'page_id' => $pageIds ] )
				->caller( __METHOD__ )
				->fetchResultSet();

			$restrictionStore = MediaWikiServices::getInstance()->getRestrictionStore();
			foreach ( $res as $row ) {
				$t = Title::newFromRow( $row );

				if ( isset( $restrictionRows[$t->getArticleID()] ) ) {
					$restrictionStore->loadRestrictionsFromRows( $t, $restrictionRows[$t->getArticleID()] );
				}

				$article = new Article( $t, 0 );
				$article->getPage()->loadPageData( $row );

				self::$titleCacheById[$t->getArticleID()] = $t;
				$articlesById[$article->getPage()->getId()] = $article;

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

			$userIds[$row->thread_author_id] = true;

			if ( $row->thread_editedness > Threads::EDITED_BY_AUTHOR ) {
				$loadEditorsFor[$row->thread_root] = $thread;
				$thread->setEditors( [] );
			}
		}

		// Pull list of users who have edited
		if ( count( $loadEditorsFor ) ) {
			$revQuery = self::getRevisionQueryInfo();
			$res = $dbr->newSelectQueryBuilder()
				->select( [ 'rev_user_text' => $revQuery['fields']['rev_user_text'], 'rev_page' ] )
				->tables( $revQuery['tables'] )
				->where( [
					'rev_page' => array_keys( $loadEditorsFor ),
					$dbr->expr( 'rev_parent_id', '!=', 0 ),
				] )
				->caller( __METHOD__ )
				->joinConds( $revQuery['joins'] )
				->fetchResultSet();
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

		$dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();

		$article = $this->root();

		$revQuery = self::getRevisionQueryInfo();
		$line = $dbr->newSelectQueryBuilder()
			->select( [ 'rev_user_text' => $revQuery['fields']['rev_user_text'] ] )
			->tables( $revQuery['tables'] )
			->where( [ 'rev_page' => $article->getPage()->getId() ] )
			->caller( __METHOD__ )
			->orderBy( 'rev_timestamp' )
			->joinConds( $revQuery['joins'] )
			->fetchRow();
		if ( $line ) {
			return User::newFromName( $line->rev_user_text, false );
		} else {
			return null;
		}
	}

	public static function recursiveGetReplyCount( self $thread, $level = 1 ) {
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

	/**
	 * Lazy updates done whenever a thread is loaded.
	 * Much easier than running a long-running maintenance script.
	 */
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
			Threads::synchroniseArticleData( $this->article->getPage(), 100, 'cascade' );
		} elseif ( $articleTitle && !$articleTitle->equals( $dbTitle ) ) {
			// The page was probably moved and this was probably not updated.
			wfDebug(
				"Article ID/Title discrepancy, resetting NS/Title to article provided by ID\n"
			);
			$this->articleNamespace = $articleTitle->getNamespace();
			$this->articleTitle = $articleTitle->getDBkey();
			$this->article = new Article( $articleTitle, 0 );

			$set['thread_article_namespace'] = $articleTitle->getNamespace();
			$set['thread_article_title'] = $articleTitle->getDBkey();

			// There are probably problems on the rest of the article, trigger a small update
			Threads::synchroniseArticleData( $this->article->getPage(), 100, 'cascade' );
		}

		// Check for unfilled signature field. This field hasn't existed until
		// recently.
		if ( $this->signature === null ) {
			// Grab our signature.
			$sig = LqtView::getUserSignature( $this->author() );
			$contLang = MediaWikiServices::getInstance()->getContentLanguage();
			$set['thread_signature'] = $contLang->truncateForDatabase( $sig, 255, '' );
			$this->setSignature( $sig );
		}

		if ( count( $set ) ) {
			$dbw = MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();

			$dbw->newUpdateQueryBuilder()
				->update( 'thread' )
				->set( $set )
				->where( [ 'thread_id' => $this->id() ] )
				->caller( __METHOD__ )
				->execute();
		}

		// Done
		$doingUpdates = false;
	}

	public function addReply( self $thread ) {
		$thread->setSuperThread( $this );

		if ( is_array( $this->replies ) ) {
			$this->replies[$thread->id()] = $thread;
		} else {
			$this->replies();
			// @phan-suppress-next-line PhanTypeArraySuspicious $replies set by replies()
			$this->replies[$thread->id()] = $thread;
		}

		// Increment reply count.
		$this->incrementReplyCount( $thread->replyCount() + 1 );
	}

	private function removeReply( self $thread ) {
		$thread = $thread->id();

		$this->replies();

		unset( $this->replies[$thread] );

		// Also, decrement the reply count.
		$threadObj = Threads::withId( $thread );
		$this->decrementReplyCount( 1 + $threadObj->replyCount() );
	}

	private function checkReplies( $replies ) {
		// Fixes a bug where some history pages were not working, before
		// superthread was properly instance-cached.
		if ( $this->isHistorical() ) {
			return;
		}
		foreach ( $replies as $reply ) {
			if ( !$reply->hasSuperthread() ) {
				throw new RuntimeException( "Post " . $this->id() .
				" has contaminated reply " . $reply->id() .
				". Found no superthread." );
			}

			if ( $reply->superthread()->id() != $this->id() ) {
				throw new RuntimeException( "Post " . $this->id() .
				" has contaminated reply " . $reply->id() .
				". Expected " . $this->id() . ", got " .
				$reply->superthread()->id() );
			}
		}
	}

	/**
	 * @return self[]
	 */
	public function replies() {
		if ( !$this->id() ) {
			return [];
		}

		if ( $this->replies !== null ) {
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

		$dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();

		$res = $dbr->newSelectQueryBuilder()
			->select( '*' )
			->from( 'thread' )
			->where( [
				'thread_parent' => $this->id(),
				$dbr->expr( 'thread_type', '!=', Threads::TYPE_DELETED ),
			] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$rows = [];
		foreach ( $res as $row ) {
			$rows[] = $row;
		}

		$this->replies = self::bulkLoad( $rows );

		$this->checkReplies( $this->replies );

		return $this->replies;
	}

	public function setSuperthread( ?self $thread ) {
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

	/**
	 * @return self
	 */
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

	/**
	 * @param self|int $newAncestor
	 */
	public function setAncestor( $newAncestor ) {
		if ( is_object( $newAncestor ) ) {
			$this->ancestorId = $newAncestor->id();
		} else {
			$this->ancestorId = $newAncestor;
		}
	}

	/**
	 * Due to a bug in earlier versions, the topmost thread sometimes isn't there.
	 * Fix the corruption by repeatedly grabbing the parent until we hit the topmost thread.
	 *
	 * @return self
	 */
	public function fixMissingAncestor() {
		$thread = $this;

		$this->dieIfHistorical();

		while ( !$thread->isTopmostThread() ) {
			$thread = $thread->superthread();
		}

		$this->ancestorId = $thread->id();

		$dbw = MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();
		$dbw->newUpdateQueryBuilder()
			->update( 'thread' )
			->set( [ 'thread_ancestor' => $thread->id() ] )
			->where( [ 'thread_id' => $this->id() ] )
			->caller( __METHOD__ )
			->execute();

		// @phan-suppress-next-line PhanTypeMismatchReturnNullable Would crash above if null
		return $thread;
	}

	public function isTopmostThread() {
		return $this->ancestorId == $this->id ||
				$this->parentId == 0;
	}

	public function setArticle( Article $a ) {
		$this->articleId = $a->getPage()->getId();
		$this->articleNamespace = $a->getTitle()->getNamespace();
		$this->articleTitle = $a->getTitle()->getDBkey();
		$this->touch();
	}

	public function touch() {
		// Nothing here yet
	}

	/**
	 * @return Article
	 */
	public function article() {
		if ( $this->article ) {
			return $this->article;
		}

		if ( $this->articleId !== null ) {
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

		if ( isset( $article ) && $article->getPage()->exists() ) {
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

	/**
	 * @return int|null
	 */
	public function ancestorId() {
		return $this->ancestorId;
	}

	/**
	 * The 'root' is the page in the Thread namespace corresponding to this thread.
	 *
	 * @return Article|null
	 */
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

	/**
	 * @return int
	 */
	public function editedness() {
		return $this->editedness;
	}

	/**
	 * @return Article|null
	 */
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

	/**
	 * @param Article $post
	 */
	public function setSummary( $post ) {
		// Weird -- this was setting $this->summary to NULL before I changed it.
		// If there was some reason why, please tell me! -- Andrew
		$this->summary = $post;
		$this->summaryId = $post->getPage()->getId();
	}

	/**
	 * @return Title|null
	 */
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
			throw new LogicException(
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

	/**
	 * Currently equivalent to isTopmostThread.
	 *
	 * @return bool
	 */
	public function hasDistinctSubject() {
		return $this->isTopmostThread();
	}

	/**
	 * Synonym for replies()
	 *
	 * @return self[]
	 */
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

	public function getAnchorName() {
		$wantedId = $this->subject() . "_{$this->id()}";
		return Sanitizer::escapeIdForLink( $wantedId );
	}

	public function updateHistory() {
	}

	public function setAuthor( UserIdentity $user ) {
		$this->authorId = $user->getId();
		$this->authorName = $user->getName();
	}

	/**
	 * Load all lazy-loaded data in prep for (e.g.) serialization.
	 */
	public function loadAllData() {
		// Make sure superthread and topmost thread are loaded.
		$this->superthread();
		$this->topmostThread();

		// Make sure replies, and all the data therein, is loaded.
		foreach ( $this->replies() as $reply ) {
			$reply->loadAllData();
		}
	}

	/**
	 * On serialization, load all data because it will be different in the DB when we wake up.
	 *
	 * @return string[]
	 */
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

	/**
	 * This is a safety valve that makes sure that the DB is NEVER touched by a historical thread
	 * (even for reading, because the data will be out of date).
	 *
	 * @throws Exception
	 */
	public function dieIfHistorical() {
		if ( $this->isHistorical() ) {
			throw new LogicException( "Attempted write or DB operation on historical thread" );
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

		$dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();

		$revision = $this->topmostThread()->threadRevision;
		$timestamp = $dbr->timestamp( $revision->getTimestamp() );

		$row = $dbr->newSelectQueryBuilder()
			->select( '*' )
			->from( 'revision' )
			->join( 'page', null, 'rev_page=page_id' )
			->where( [
				$dbr->expr( 'rev_timestamp', '<=', $timestamp ),
				'page_namespace' => $this->root()->getTitle()->getNamespace(),
				'page_title' => $this->root()->getTitle()->getDBkey(),
			] )
			->caller( __METHOD__ )
			->orderBy( 'rev_timestamp', SelectQueryBuilder::SORT_DESC )
			->fetchRow();

		return $row->rev_id;
	}

	public function sortkey() {
		return $this->sortkey;
	}

	public function setSortKey( $k = null ) {
		$this->sortkey = $k ?? wfTimestamp( TS_MW );
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

	public function split( $newSubject, $reason = '', $newSortkey = null ) {
		$oldTopThread = $this->topmostThread();
		$oldParent = $this->superthread();

		$original = $this->dbVersion;

		self::recursiveSet( $this, $newSubject, $this, null );

		$oldParent->removeReply( $this );

		$bump = null;
		if ( $newSortkey !== null ) {
			$this->setSortKey( $newSortkey );
			$bump = false;
		}

		// For logging purposes, will be reset by the time this call returns.
		$this->dbVersion = $original;
		$user = RequestContext::getMain()->getUser(); // Need to inject

		$this->commitRevision( Threads::CHANGE_SPLIT, $user, null, $reason, $bump );
		$oldTopThread->commitRevision( Threads::CHANGE_SPLIT_FROM, $user, $this, $reason );
	}

	public function moveToParent( self $newParent, $reason = '' ) {
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
		$user = RequestContext::getMain()->getUser(); // Need to inject

		$oldTopThread->commitRevision( Threads::CHANGE_MERGED_FROM, $user, $this, $reason );
		$newParent->commitRevision( Threads::CHANGE_MERGED_TO, $user, $this, $reason );
	}

	/**
	 * @param self $thread
	 * @param string $subject
	 * @param self $ancestor
	 * @param self|null $superthread
	 */
	public static function recursiveSet(
		self $thread,
		$subject,
		self $ancestor,
		$superthread = null
	) {
		$thread->setSubject( $subject );
		$thread->setAncestor( $ancestor->id() );

		if ( $superthread ) {
			$thread->setSuperThread( $superthread );
		}

		$thread->save();

		foreach ( $thread->replies() as $subThread ) {
			self::recursiveSet( $subThread, $subject, $ancestor );
		}
	}

	public static function validateSubject( $subject, User $user, &$title, $replyTo, $article ) {
		$t = null;
		$ok = true;

		while ( !$t ) {
			try {
				if ( !$replyTo && $subject ) {
					$t = Threads::newThreadTitle( $subject, $article );
				} elseif ( $replyTo ) {
					$t = Threads::newReplyTitle( $replyTo, $user );
				}

				if ( $t ) {
					break;
				}
			} catch ( Exception ) {
			}

			$subject = md5( (string)mt_rand() ); // Just a random title
			$ok = false;
		}

		$title = $t;

		return $ok;
	}

	/**
	 * Returns true, or a string with either thread or talkpage, noting which is protected
	 *
	 * @param User $user
	 * @param string $rigor
	 * @return bool|string
	 */
	public function canUserReply( User $user, $rigor = PermissionManager::RIGOR_SECURE ) {
		$rootTitle = $this->topmostThread()->title();
		$restrictionStore = MediaWikiServices::getInstance()->getRestrictionStore();
		$threadRestrictions = $rootTitle ? $restrictionStore->getRestrictions( $rootTitle, 'reply' ) : [];
		$talkpageRestrictions = $restrictionStore->getRestrictions( $this->getTitle(), 'reply' );

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

	/**
	 * @param User $user
	 * @param Article $talkpage
	 * @param string $rigor
	 * @return bool
	 */
	public static function canUserPost( $user, $talkpage, $rigor = PermissionManager::RIGOR_SECURE ) {
		$restrictions = MediaWikiServices::getInstance()->getRestrictionStore()
			->getRestrictions( $talkpage->getTitle(), 'newthread' );

		foreach ( $restrictions as $right ) {
			if ( !$user->isAllowed( $right ) ) {
				return false;
			}
		}

		return self::canUserCreateThreads( $user, $rigor );
	}

	/**
	 * Generally, not some specific page
	 *
	 * @param User $user
	 * @param string $rigor
	 * @return bool
	 */
	public static function canUserCreateThreads( $user, $rigor = PermissionManager::RIGOR_SECURE ) {
		$userText = $user->getName();
		$pm = MediaWikiServices::getInstance()->getPermissionManager();

		static $canCreateNew = [];
		if ( !isset( $canCreateNew[$userText] ) ) {
			$title = Title::makeTitleSafe(
				NS_LQT_THREAD, 'Test title for LQT thread creation check' );
			$canCreateNew[$userText] = $pm->userCan( 'edit', $user, $title, $rigor );
		}

		return $canCreateNew[$userText];
	}

	/**
	 * @return string|null Signature wikitext, may be null for very old unserialized comments (T365495)
	 */
	public function signature() {
		return $this->signature;
	}

	public function setSignature( $sig ) {
		$sig = LqtView::signaturePST( $sig, $this->author() );
		$this->signature = $sig;
	}

	public function editors() {
		if ( $this->editors === null ) {
			if ( $this->editedness() < Threads::EDITED_BY_AUTHOR ) {
				return [];
			} elseif ( $this->editedness == Threads::EDITED_BY_AUTHOR ) {
				return [ $this->author()->getName() ];
			}

			// Load editors
			$this->editors = [];

			$dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();
			$revQuery = self::getRevisionQueryInfo();
			$res = $dbr->newSelectQueryBuilder()
				->select( [ 'rev_user_text' => $revQuery['fields']['rev_user_text'] ] )
				->tables( $revQuery['tables'] )
				->where( [
					'rev_page' => $this->root()->getPage()->getId(),
					$dbr->expr( 'rev_parent_id', '!=', 0 ),
				] )
				->caller( __METHOD__ )
				->joinConds( $revQuery['joins'] )
				->fetchResultSet();

			$editors = [];
			foreach ( $res as $row ) {
				$editors[$row->rev_user_text] = 1;
			}

			$this->editors = array_keys( $editors );
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
		if ( $this->reactions === null ) {
			if ( isset( self::$reactionCacheById[$this->id()] ) ) {
				$this->reactions = self::$reactionCacheById[$this->id()];
			} else {
				$reactions = [];

				$dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();

				$res = $dbr->newSelectQueryBuilder()
					->select( [ 'tr_user', 'tr_user_text', 'tr_type', 'tr_value' ] )
					->from( 'thread_reaction' )
					->where( [ 'tr_thread' => $this->id() ] )
					->caller( __METHOD__ )
					->fetchResultSet();

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

		if ( $requestedType === null ) {
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

		$dbw = MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();

		$dbw->newInsertQueryBuilder()
			->insertInto( 'thread_reaction' )
			->row( $row )
			->caller( __METHOD__ )
			->execute();
	}

	public function deleteReaction( $user, $type ) {
		$dbw = MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();

		if ( isset( $this->reactions[$type][$user->getName()] ) ) {
			unset( $this->reactions[$type][$user->getName()] );
		}

		$dbw->newDeleteQueryBuilder()
			->deleteFrom( 'thread_reaction' )
			->where( [
				'tr_thread' => $this->id(),
				'tr_user' => $user->getId(),
				'tr_type' => $type
			] )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * @return array[]
	 */
	private static function getRevisionQueryInfo() {
		$info = MediaWikiServices::getInstance()->getRevisionStore()->getQueryInfo();
		if ( !isset( $info['fields']['rev_user_text'] ) ) {
			$info['fields']['rev_user_text'] = 'rev_user_text';
		}

		return $info;
	}
}
