<?php
if ( !defined( 'MEDIAWIKI' ) ) die;

/** Module of factory methods. */
class Threads {

	const TYPE_NORMAL = 0;
	const TYPE_MOVED = 1;
	const TYPE_DELETED = 2;

	const CHANGE_NEW_THREAD = 0;
	const CHANGE_REPLY_CREATED = 1;
	const CHANGE_EDITED_ROOT = 2;
	const CHANGE_EDITED_SUMMARY = 3;
	const CHANGE_DELETED = 4;
	const CHANGE_UNDELETED = 5;
	const CHANGE_MOVED_TALKPAGE = 6;
	const CHANGE_SPLIT = 7;
	const CHANGE_EDITED_SUBJECT = 8;
	const CHANGE_PARENT_DELETED = 9;
	const CHANGE_MERGED_FROM = 10;
	const CHANGE_MERGED_TO = 11;
	const CHANGE_SPLIT_FROM = 12;
	
	static $VALID_CHANGE_TYPES = array( self::CHANGE_EDITED_SUMMARY, self::CHANGE_EDITED_ROOT,
		self::CHANGE_REPLY_CREATED, self::CHANGE_NEW_THREAD, self::CHANGE_DELETED, self::CHANGE_UNDELETED,
		self::CHANGE_MOVED_TALKPAGE, self::CHANGE_SPLIT, self::CHANGE_EDITED_SUBJECT,
		self::CHANGE_PARENT_DELETED, self::CHANGE_MERGED_FROM, self::CHANGE_MERGED_TO,
		self::CHANGE_SPLIT_FROM );

	// Possible values of Thread->editedness.
	const EDITED_NEVER = 0;
	const EDITED_HAS_REPLY = 1;
	const EDITED_BY_AUTHOR = 2;
	const EDITED_BY_OTHERS = 3;

	static $cache_by_root = array();
	static $cache_by_id = array();
	static protected $occupied_titles = array();

    static function newThread( $root, $article, $superthread = null,
    							$type = self::TYPE_NORMAL, $subject = '' ) {
		return Thread::create( $root, $article, $superthread, $type, $subject );
	}

	/**
	 * Create the talkpage if it doesn't exist so that links to it
	 * will show up blue instead of red. For use upon new thread creation.
	 */
	public static function createTalkpageIfNeeded( $talkpage ) {
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
			if ($thread->root()) {
				self::$cache_by_root[$thread->root()->getID()] = $thread;
			}
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
		if ( count( $threads ) == 1 ) { return array_pop($threads); }
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
	
	static function scratchTitle() {
		$token = md5( uniqid( rand(), true ) );
		return Title::newFromText( "Thread:$token" );
	}
	
	static function newThreadTitle( $subject, $article ) {
		wfLoadExtensionMessages( 'LiquidThreads' );
		$subject = $subject ? $subject : wfMsg( 'lqt_nosubject' );
		
		$base = $article->getTitle()->getPrefixedText() . "/$subject";
		
		return self::incrementedTitle( $base, NS_LQT_THREAD );
	}
	
	static function newSummaryTitle( $t ) {
		return self::incrementedTitle( $t->title()->getText(), NS_LQT_SUMMARY );
	}
	
	static function newReplyTitle( $thread, $user) {
		$topThread = $thread->topmostThread();
		
		$base = $topThread->title()->getText() . '/' . $user->getName();
		
		return self::incrementedTitle( $base, NS_LQT_THREAD );
	}
	
	// This will attempt to replace invalid characters and sequences in a title with
	//  a safe replacement (_, currently).
	public static function makeTitleValid( $text ) {
		static $rxTc;
		
		if ( is_callable( array( 'Title', 'getTitleInvalidRegex' ) ) ) {
			$rxTc = Title::getTitleInvalidRegex();
		} elseif (!$rxTc) { // Back-compat
			$rxTc = '/' .
				# Any character not allowed is forbidden...
				'[^' . Title::legalChars() . ']' .
				# URL percent encoding sequences interfere with the ability
				# to round-trip titles -- you can't link to them consistently.
				'|%[0-9A-Fa-f]{2}' .
				# XML/HTML character references produce similar issues.
				'|&[A-Za-z0-9\x80-\xff]+;' .
				'|&#[0-9]+;' .
				'|&#x[0-9A-Fa-f]+;' .
				'/S';
		}
		
		$text = preg_replace( $rxTc, '_', $text );
		
		return $text;
	}
	
	/** Keep trying titles starting with $basename until one is unoccupied. */
	public static function incrementedTitle( $basename, $namespace ) {
		$i = 2;
		
		// Try to make the title valid.
		$basename = Threads::makeTitleValid( $basename );
		
		$t = Title::makeTitleSafe( $namespace, $basename );
		while ( !$t || $t->exists() ||
				in_array( $t->getPrefixedDBkey(), self::$occupied_titles ) ) {
			
			if (!$t) {
				throw new MWException( "Error in creating title for basename $basename" );
			}
			
			$t = Title::makeTitleSafe( $namespace, $basename . ' (' . $i . ')' );
			$i++;
		}
		return $t;
	}	
}
