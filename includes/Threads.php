<?php

use MediaWiki\Content\ContentHandler;
use MediaWiki\Context\RequestContext;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\WikiPage;
use MediaWiki\Title\MediaWikiTitleCodec;
use MediaWiki\Title\Title;
use Wikimedia\Rdbms\DBQueryError;

/** Module of factory methods. */
class Threads {
	public const TYPE_NORMAL = 0;
	public const TYPE_MOVED = 1;
	public const TYPE_DELETED = 2;
	public const TYPE_HIDDEN = 4;

	public const CHANGE_NEW_THREAD = 0;
	public const CHANGE_REPLY_CREATED = 1;
	public const CHANGE_EDITED_ROOT = 2;
	public const CHANGE_EDITED_SUMMARY = 3;
	public const CHANGE_DELETED = 4;
	public const CHANGE_UNDELETED = 5;
	public const CHANGE_MOVED_TALKPAGE = 6;
	public const CHANGE_SPLIT = 7;
	public const CHANGE_EDITED_SUBJECT = 8;
	public const CHANGE_PARENT_DELETED = 9;
	public const CHANGE_MERGED_FROM = 10;
	public const CHANGE_MERGED_TO = 11;
	public const CHANGE_SPLIT_FROM = 12;
	public const CHANGE_ROOT_BLANKED = 13;
	public const CHANGE_ADJUSTED_SORTKEY = 14;
	public const CHANGE_EDITED_SIGNATURE = 15;

	// Possible values of Thread->editedness.
	public const EDITED_NEVER = 0;
	public const EDITED_HAS_REPLY = 1;
	public const EDITED_BY_AUTHOR = 2;
	public const EDITED_BY_OTHERS = 3;

	/** @var Thread[] */
	public static $cache_by_root = [];
	/** @var Thread[] */
	public static $cache_by_id = [];
	/** @var string[] */
	public static $occupied_titles = [];

	/**
	 * Create the talkpage if it doesn't exist so that links to it
	 * will show up blue instead of red. For use upon new thread creation.
	 *
	 * @param WikiPage $talkpage
	 */
	public static function createTalkpageIfNeeded( WikiPage $talkpage ) {
		if ( !$talkpage->exists() ) {
			try {
				// TODO figure out injecting the context user instead of
				// using RequestContext::getMain()
				$user = RequestContext::getMain()->getUser();
				$talkpage->doUserEditContent(
					ContentHandler::makeContent( "", $talkpage->getTitle() ),
					$user,
					wfMessage( 'lqt_talkpage_autocreate_summary' )->inContentLanguage()->text(),
					EDIT_NEW | EDIT_SUPPRESS_RC
				);
			} catch ( DBQueryError $e ) {
				// The page already existed by now. No need to do anything.
				wfDebug( __METHOD__ . ": Page already exists." );
			}
		}
	}

	public static function loadFromResult( $res, $db, $bulkLoad = false ) {
		$rows = [];
		$threads = [];

		foreach ( $res as $row ) {
			$rows[] = $row;

			if ( !$bulkLoad ) {
				$threads[$row->thread_id] = Thread::newFromRow( $row );
			}
		}

		if ( !$bulkLoad ) {
			return $threads;
		}

		return Thread::bulkLoad( $rows );
	}

	/**
	 * @param array $where
	 * @param array $options
	 * @param bool $bulkLoad
	 * @return Thread[]
	 */
	public static function where( $where, $options = [], $bulkLoad = true ) {
		$dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();

		$res = $dbr->newSelectQueryBuilder()
			->select( '*' )
			->from( 'thread' )
			->where( $where )
			->caller( __METHOD__ )
			->options( $options )
			->fetchResultSet();
		$threads = self::loadFromResult( $res, $dbr, $bulkLoad );

		foreach ( $threads as $thread ) {
			if ( $thread->root() ) {
				self::$cache_by_root[$thread->root()->getPage()->getId()] = $thread;
			}
			self::$cache_by_id[$thread->id()] = $thread;
		}

		return $threads;
	}

	/**
	 * @param string $msg
	 * @return never
	 */
	private static function databaseError( $msg ) {
		// @todo Tie into MW's error reporting facilities.
		throw new RuntimeException( "Corrupt LiquidThreads database: $msg" );
	}

	private static function assertSingularity( array $threads, $attribute, $value ) {
		if ( count( $threads ) == 0 ) {
			return null;
		}

		if ( count( $threads ) == 1 ) {
			return array_pop( $threads );
		}

		if ( count( $threads ) > 1 ) {
			self::databaseError( "More than one thread with $attribute = $value." );
		}

		return null;
	}

	/**
	 * @param WikiPage $post
	 * @param bool $bulkLoad
	 * @return Thread|null
	 */
	public static function withRoot( WikiPage $post, $bulkLoad = true ) {
		if ( $post->getTitle()->getNamespace() != NS_LQT_THREAD ) {
			// No articles outside the thread namespace have threads associated with them;
			return null;
		}

		if ( !$post->getId() ) {
			// Page ID zero doesn't exist.
			return null;
		}

		if ( array_key_exists( $post->getId(), self::$cache_by_root ) ) {
			return self::$cache_by_root[$post->getId()];
		}

		$ts = self::where( [ 'thread_root' => $post->getId() ], [], $bulkLoad );

		return self::assertSingularity( $ts, 'thread_root', $post->getId() );
	}

	/**
	 * @param int $id
	 * @param bool $bulkLoad
	 * @return Thread
	 */
	public static function withId( $id, $bulkLoad = true ) {
		if ( array_key_exists( $id, self::$cache_by_id ) ) {
			return self::$cache_by_id[$id];
		}

		$ts = self::where( [ 'thread_id' => $id ], [], $bulkLoad );

		return self::assertSingularity( $ts, 'thread_id', $id );
	}

	/**
	 * @param WikiPage $page
	 * @param bool $bulkLoad
	 * @return Thread
	 */
	public static function withSummary( WikiPage $page, $bulkLoad = true ) {
		$ts = self::where(
			[ 'thread_summary_page' => $page->getId() ],
			[],
			$bulkLoad
		);
		return self::assertSingularity(
			$ts,
			'thread_summary_page',
			$page->getId()
		);
	}

	/**
	 * @param WikiPage $page
	 * @return string
	 */
	public static function articleClause( WikiPage $page ) {
		$dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();

		$titleCond = [ 'thread_article_title' => $page->getTitle()->getDBKey(),
			'thread_article_namespace' => $page->getTitle()->getNamespace() ];
		$titleCond = $dbr->makeList( $titleCond, LIST_AND );

		$conds = [ $titleCond ];

		if ( $page->getId() ) {
			$idCond = [ 'thread_article_id' => $page->getId() ];
			$conds[] = $dbr->makeList( $idCond, LIST_AND );
		}

		return $dbr->makeList( $conds, LIST_OR );
	}

	public static function topLevelClause() {
		$dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();

		$arr = [ 'thread_ancestor=thread_id', 'thread_parent' => null ];

		return $dbr->makeList( $arr, LIST_OR );
	}

	public static function newThreadTitle( $subject, $article ) {
		$base = $article->getTitle()->getPrefixedText() . "/$subject";

		return self::incrementedTitle( $base, NS_LQT_THREAD );
	}

	public static function newSummaryTitle( Thread $t ) {
		return self::incrementedTitle( $t->title()->getText(), NS_LQT_SUMMARY );
	}

	public static function newReplyTitle( Thread $thread, $user ) {
		$topThread = $thread->topmostThread();

		$base = $topThread->title()->getText() . '/'
			. wfMessage( 'lqt-reply-subpage' )->inContentLanguage()->text();

		return self::incrementedTitle( $base, NS_LQT_THREAD );
	}

	/**
	 * This will attempt to replace invalid characters and sequences in a title with a safe
	 * replacement (_, currently). Before doing this, it will parse any wikitext and strip the HTML,
	 * before converting HTML entities back into their corresponding characters.
	 *
	 * @param string $text
	 * @return string
	 */
	public static function makeTitleValid( $text ) {
		$text = self::stripWikitext( $text );
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );

		$rxTc = MediaWikiTitleCodec::getTitleInvalidRegex();

		$text = preg_replace( $rxTc, '_', $text );

		return $text;
	}

	/**
	 * This will strip wikitext of its formatting.
	 *
	 * @param string $text
	 * @return string
	 */
	public static function stripWikitext( $text ) {
		$out = RequestContext::getMain()->getOutput();
		# The $text may not actually be in the interface language, but we
		# don't want to subject it to language conversion, so
		# parseAsInterface() is better than parseAsContent()
		$text = $out->parseInlineAsInterface( $text );

		$text = StringUtils::delimiterReplace( '<', '>', '', $text );

		return $text;
	}

	public static function stripHTML( $text ) {
		return StringUtils::delimiterReplace( '<', '>', '', $text );
	}

	/**
	 * Keep trying titles starting with $basename until one is unoccupied.
	 * @param string $basename
	 * @param int $namespace
	 * @return Title
	 */
	public static function incrementedTitle( $basename, $namespace ) {
		$i = 2;

		// Try to make the title valid.
		$basename = self::makeTitleValid( $basename );

		$t = Title::makeTitleSafe( $namespace, $basename );
		$contLang = MediaWikiServices::getInstance()->getContentLanguage();
		while ( !$t ||
			in_array( $t->getPrefixedDBkey(), self::$occupied_titles ) ||
			$t->exists() ||
			$t->isDeletedQuick()
		) {
			if ( !$t ) {
				throw new LogicException( "Error in creating title for basename $basename" );
			}

			$n = $contLang->formatNum( $i );
			$t = Title::makeTitleSafe( $namespace, $basename . ' (' . $n . ')' );
			$i++;
		}
		// @phan-suppress-next-line PhanTypeMismatchReturnNullable
		return $t;
	}

	/**
	 * Called just before any function that might cause a loss of article association.
	 * by breaking either a NS-title reference (by moving the article), or a page-id
	 * reference (by deleting the article).
	 * Basically ensures that all subthreads have the two stores of article association
	 * synchronised.
	 * Can also be called with a "limit" parameter to slowly convert old threads. This
	 * is intended to be used by jobs created by move and create operations to slowly
	 * propagate the change through the data set without rushing the whole conversion
	 * when a second breaking change is made. If a limit is set and more rows require
	 * conversion, this function will return false. Otherwise, true will be returned.
	 * If the queueMore parameter is set and rows are left to update, a job queue item
	 * will then be added with the same limit, to finish the remainder of the update.
	 *
	 * @param WikiPage $page
	 * @param int|false $limit
	 * @param string|false $queueMore
	 * @return bool
	 */
	public static function synchroniseArticleData( WikiPage $page, $limit = false, $queueMore = false ) {
		$dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();
		$dbw = MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();

		$title = $page->getTitle();
		$id = $page->getId();

		$titleCond = [ 'thread_article_namespace' => $title->getNamespace(),
			'thread_article_title' => $title->getDBkey() ];
		$titleCondText = $dbr->makeList( $titleCond, LIST_AND );

		$idCond = [ 'thread_article_id' => $id ];
		$idCondText = $dbr->makeList( $idCond, LIST_AND );

		$fixTitleCond = [ $idCondText, "NOT ($titleCondText)" ];
		$fixIdCond = [ $titleCondText, "NOT ($idCondText)" ];

		// Try to hit the most recent threads first.
		$options = [ 'LIMIT' => 500, 'ORDER BY' => 'thread_id DESC' ];

		// Batch in 500s
		if ( $limit ) {
			$options['LIMIT'] = min( $limit, 500 );
		}

		$rowsAffected = 0;
		$roundRowsAffected = 1;

		while ( ( !$limit || $rowsAffected < $limit ) && $roundRowsAffected > 0 ) {
			$roundRowsAffected = 0;

			// Fix wrong title.
			$fixTitleCount = $dbr->newSelectQueryBuilder()
				->select( 'COUNT(*)' )
				->from( 'thread' )
				->where( $fixTitleCond )
				->caller( __METHOD__ )
				->fetchField();
			if ( intval( $fixTitleCount ) ) {
				$dbw->newUpdateQueryBuilder()
					->update( 'thread' )
					->set( $titleCond )
					->where( $fixTitleCond )
					->options( $options )
					->caller( __METHOD__ )
					->execute();
				$roundRowsAffected += $dbw->affectedRows();
			}

			// Fix wrong ID
			$fixIdCount = $dbr->newSelectQueryBuilder()
				->select( 'COUNT(*)' )
				->from( 'thread' )
				->where( $fixIdCond )
				->caller( __METHOD__ )
				->fetchField();
			if ( intval( $fixIdCount ) ) {
				$dbw->newUpdateQueryBuilder()
					->update( 'thread' )
					->set( $idCond )
					->where( $fixIdCond )
					->options( $options )
					->caller( __METHOD__ )
					->execute();
				$roundRowsAffected += $dbw->affectedRows();
			}

			$rowsAffected += $roundRowsAffected;
		}

		if ( $limit && ( $rowsAffected >= $limit ) && $queueMore ) {
			$jobParams = [ 'limit' => $limit, 'cascade' => true ];
			MediaWikiServices::getInstance()->getJobQueueGroup()->push(
				new SynchroniseThreadArticleDataJob(
					$page->getTitle(),
					$jobParams
				)
			);
		}

		return $limit ? ( $rowsAffected < $limit ) : true;
	}
}
