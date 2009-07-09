<?php
if ( !defined( 'MEDIAWIKI' ) ) die;

/** Module of factory methods. */
class Threads {

	const TYPE_NORMAL = 0;
	const TYPE_MOVED = 1;
	const TYPE_DELETED = 2;
	static $VALID_TYPES = array( self::TYPE_NORMAL, self::TYPE_MOVED, self::TYPE_DELETED );

	const CHANGE_NEW_THREAD = 0;
	const CHANGE_REPLY_CREATED = 1;
	const CHANGE_EDITED_ROOT = 2;
	const CHANGE_EDITED_SUMMARY = 3;
	const CHANGE_DELETED = 4;
	const CHANGE_UNDELETED = 5;
	const CHANGE_MOVED_TALKPAGE = 6;
	const CHANGE_SPLIT = 7;
	const CHANGE_EDITED_SUBJECT = 8;
	
	static $VALID_CHANGE_TYPES = array( self::CHANGE_EDITED_SUMMARY, self::CHANGE_EDITED_ROOT,
		self::CHANGE_REPLY_CREATED, self::CHANGE_NEW_THREAD, self::CHANGE_DELETED, self::CHANGE_UNDELETED,
		self::CHANGE_MOVED_TALKPAGE, self::CHANGE_SPLIT, self::CHANGE_EDITED_SUBJECT );

	// Possible values of Thread->editedness.
	const EDITED_NEVER = 0;
	const EDITED_HAS_REPLY = 1;
	const EDITED_BY_AUTHOR = 2;
	const EDITED_BY_OTHERS = 3;

	static $cache_by_root = array();
	static $cache_by_id = array();
	
	/** static cache of per-page archivestartdays setting */
	static $archiveStartDays;

    static function newThread( $root, $article, $superthread = null,
    							$type = self::TYPE_NORMAL, $subject = '' ) {
		// SCHEMA changes must be reflected here.
		// TODO: It's dumb that the commitRevision code isn't used here.

        $dbw =& wfGetDB( DB_MASTER );

		if ( !in_array( $type, self::$VALID_TYPES ) ) {
			throw new MWException( __METHOD__ . ": invalid type $type." );
		}

		if ( $superthread ) {
			$change_type = self::CHANGE_REPLY_CREATED;
		} else {
			$change_type = self::CHANGE_NEW_THREAD;
		}

		global $wgUser;

		$timestamp = wfTimestampNow();

		// TODO PG support
		$newid = $dbw->nextSequenceValue( 'thread_thread_id' );

		$row = array(
					'thread_root' => $root->getID(),
					'thread_parent' => $superthread ? $superthread->id() : null,
					'thread_article_namespace' => $article->getTitle()->getNamespace(),
					'thread_article_title' => $article->getTitle()->getDBkey(),
					'thread_modified' => $timestamp,
					'thread_created' => $timestamp,
					'thread_change_type' => $change_type,
					'thread_change_comment' => "", // TODO
					'thread_change_user' => $wgUser->getID(),
					'thread_change_user_text' => $wgUser->getName(),
					'thread_type' => $type,
					'thread_editedness' => self::EDITED_NEVER,
					'thread_subject' => $subject,
				);

		if ( $superthread ) {
			$row['thread_ancestor'] = $superthread->ancestorId();
			$row['thread_change_object'] = $newid;
		} else {
			$row['thread_change_object'] = null;
		}

        $res = $dbw->insert( 'thread', $row, __METHOD__ );

		$newid = $dbw->insertId();

		$row['thread_id'] = $newid;

		// Ew, we have to do a SECOND update
		if ( $superthread ) {
			$row['thread_change_object'] = $newid;
			$dbw->update( 'thread',
				array( 'thread_change_object' => $newid ),
				array( 'thread_id' => $newid ),
				__METHOD__ );
		}

		// Sigh, convert row to an object
		$rowObj = new stdClass();
		foreach ( $row as $key => $value ) {
			$rowObj->$key = $value;
		}

		// We just created the thread, it won't have any children.
		$newthread = new Thread( $rowObj, array() );

		if ( $superthread ) {
			$superthread->addReply( $newthread );
		}

		self::createTalkpageIfNeeded( $article );

		NewMessages::writeMessageStateForUpdatedThread( $newthread, $change_type, $wgUser );
		
		// Touch the article
		$article->getTitle()->invalidateCache();

		return $newthread;
	}

	/**
	 * Create the talkpage if it doesn't exist so that links to it
	 * will show up blue instead of red. For use upon new thread creation.
	*/
	protected static function createTalkpageIfNeeded( $subjectPage ) {
		$talkpage_t = $subjectPage->getTitle();
		$talkpage = new Article( $talkpage_t );
		if ( ! $talkpage->exists() ) {
			try {
				wfLoadExtensionMessages( 'LiquidThreads' );
				$talkpage->doEdit( "", wfMsg( 'lqt_talkpage_autocreate_summary' ), EDIT_NEW | EDIT_SUPPRESS_RC );

			} catch ( DBQueryError $e ) {
				// The article already existed by now. No need to do anything.
				wfDebug( __METHOD__ . ": Article already existed by the time we tried to create it." );
			}
		}
	}
	
	static function loadFromResult( $res, $db ) {
		$rows = array();
		
		while( $row = $db->fetchObject( $res ) ) {
			$rows[] = $row;
		}
		
		return Thread::bulkLoad( $rows );
	}

	static function where( $where, $options = array() ) {
		global $wgDBprefix;
		$dbr = wfGetDB( DB_SLAVE );
		
		$res = $dbr->select( 'thread', '*', $where, __METHOD__, $options );
		$threads = Threads::loadFromResult( $res, $dbr );

		foreach ( $threads as $thread ) {
			self::$cache_by_root[$thread->root()->getID()] = $thread;
			self::$cache_by_id[$thread->id()] = $thread;
		}

		return $threads;
	}

	private static function databaseError( $msg ) {
		// TODO tie into MW's error reporting facilities.
		throw new MWException( "Corrupt liquidthreads database: $msg" );
	}

	private static function assertSingularity( $threads, $attribute, $value ) {
		if ( count( $threads ) == 0 ) { return null; }
		if ( count( $threads ) == 1 ) { return $threads[0]; }
		if ( count( $threads ) > 1 ) {
			Threads::databaseError( "More than one thread with $attribute = $value." );
			return null;
		}
	}

	private static function arrayContainsThreadWithId( $a, $id ) {
		// There's gotta be a nice way to express this in PHP. Anyone?
		foreach ( $a as $t )
			if ( $t->id() == $id )
				return true;
		return false;
	}

	static function withRoot( $post ) {
		if ( $post->getTitle()->getNamespace() != NS_LQT_THREAD ) {
			// No articles outside the thread namespace have threads associated with them;
			// avoiding the query saves time during the TitleGetRestrictions hook.
			return null;
		}
		if ( array_key_exists( $post->getID(), self::$cache_by_root ) ) {
			return self::$cache_by_root[$post->getID()];
		}
		$ts = Threads::where( array( 'thread.thread_root' => $post->getID() ) );
		return self::assertSingularity( $ts, 'thread_root', $post->getID() );
	}

	static function withId( $id ) {
		if ( array_key_exists( $id, self::$cache_by_id ) ) {
			return self::$cache_by_id[$id];
		}
		$ts = Threads::where( array( 'thread_id' => $id ) );
		
		return self::assertSingularity( $ts, 'thread_id', $id );
	}

	static function withSummary( $article ) {
		$ts = Threads::where( array( 'thread.thread_summary_page' => $article->getId() ) );
		return self::assertSingularity( $ts, 'thread_summary_page', $article->getId() );
	}

	/**
	  * Horrible, horrible!
	  * List of months in which there are >0 threads, suitable for threadsOfArticleInMonth.
	  * Returned as an array of months in the format yyyymm
	  */
	static function monthsWhereArticleHasThreads( $article ) {
		// FIXME this probably performs absolutely horribly for pages with lots of threads.
		
		$threads = Threads::where( Threads::articleClause( $article ) );
		$months = array();
		
		foreach ( $threads as $t ) {
			$month = substr( $t->modified(), 0, 6 );
			
			$months[$month] = true;
		}
		
		// Some code seems to assume that it's sorted by month, make sure it's true.
		ksort( $months );
		
		return array_keys($months);
	}

	static function articleClause( $article ) {
		$dbr = wfGetDB( DB_SLAVE );
		
		$arr = array( 'thread_article_title' => $article->getTitle()->getDBKey(),
						'thread_article_namespace' => $article->getTitle()->getNamespace() );
		
		return $dbr->makeList( $arr, LIST_AND );
	}

	static function topLevelClause() {
		$dbr = wfGetDB( DB_SLAVE );
		
		$arr = array( 'thread_ancestor=thread_id', 'thread_parent' => null );
		
		return $dbr->makeList( $arr, LIST_OR );
	}
	
	static function getArticleArchiveStartDays( $article ) {
		global $wgLqtThreadArchiveStartDays;
		
		$article = $article->getId();
		
		// Instance cache
		if ( isset( self::$archiveStartDays[$article] ) ) {
			$cacheVal = self::$archiveStartDays[$article];
			if ( !is_null( $cacheVal ) ) {
				return $cacheVal;
			} else {
				return $wgLqtThreadArchiveStartDays;
			}
		}
		
		// Memcached: It isn't clear that this is needed yet, but since I already wrote the
		//  code, I might as well leave it commented out instead of deleting it.
		//  Main reason I've left this commented out is because it isn't obvious how to
		//  purge the cache when necessary.
// 		global $wgMemc;
// 		$key = wfMemcKey( 'lqt-archive-start-days', $article );
// 		$cacheVal = $wgMemc->get( $key );
// 		if ($cacheVal != false) {
// 			if ( $cacheVal != -1 ) {
// 				return $cacheVal;
// 			} else {
// 				return $wgLqtThreadArchiveStartDays;
// 			}
// 		}
		
		// Load from the database.
		$dbr = wfGetDB( DB_SLAVE );
		
		$dbVal = $dbr->selectField( 'page_props', 'pp_value',
									array( 'pp_propname' => 'lqt-archivestartdays',
											'pp_page' => $article ), __METHOD__ );
		
		if ($dbVal) {
			self::$archiveStartDays[$article] = $dbVal;
#			$wgMemc->set( $key, $dbVal, 1800 );
			return $dbVal;
		} else {
			// Negative caching.
			self::$archiveStartDays[$article] = null;
#			$wgMemc->set( $key, -1, 86400 );
			return $wgLqtThreadArchiveStartDays;
		}
	}
}
