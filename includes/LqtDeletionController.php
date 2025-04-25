<?php

use MediaWiki\Context\RequestContext;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use MediaWiki\Page\Article;
use MediaWiki\Page\WikiPage;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use MediaWiki\User\User;

class LqtDeletionController {
	/** @var Title[]|null */
	public static $pageids_to_revive;

	public static function onArticleDeleteComplete( WikiPage &$article, User &$user, $reason, $id ) {
		$title = $article->getTitle();

		if ( $title->getNamespace() != NS_LQT_THREAD ) {
			return true;
		}

		$threads = Threads::where( [ 'thread_root' => $id ] );

		if ( !count( $threads ) ) {
			wfDebugLog( 'LiquidThreads', __METHOD__ . ": no threads with root $id, ignoring...\n" );
			return true;
		}

		$thread = array_pop( $threads );

		// Mark the thread as deleted
		$thread->delete( $reason );

		// Avoid orphaning subthreads, update their parentage.
		if ( $thread->replies() && $thread->isTopmostThread() ) {
			$reason = wfMessage( 'lqt-delete-parent-deleted', $reason )->text();
			self::recursivelyDeleteReplies( $thread, $reason, $user );
			$out = RequestContext::getMain()->getOutput();
			$out->addWikiMsg( 'lqt-delete-replies-done' );
		} elseif ( $thread->replies() ) {
			foreach ( $thread->replies() as $reply ) {
				$reply->setSuperthread( $thread->superthread() );
				$reply->save();
			}
		}

		// Synchronise the first 500 threads, in reverse order by thread id. If
		// there are more threads to synchronise, the job queue will take over.
		Threads::synchroniseArticleData( $article, 500, 'cascade' );

		return true;
	}

	public static function recursivelyDeleteReplies( Thread $thread, $reason, User $user ) {
		foreach ( $thread->replies() as $reply ) {
			$reply->root()->getPage()->doDeleteArticleReal( $reason, $user );
			$reply->delete( $reason );
			self::recursivelyDeleteReplies( $reply, $reason, $user );
		}
	}

	public static function onRevisionUndeleted( RevisionRecord $revisionRecord, $oldPageId ) {
		$linkTarget = $revisionRecord->getPageAsLinkTarget();
		if ( $linkTarget->getNamespace() == NS_LQT_THREAD ) {
			self::$pageids_to_revive[$oldPageId] = Title::newFromLinkTarget( $linkTarget );
		}

		return true;
	}

	public static function onArticleUndelete( &$udTitle, $created, $comment = '' ) {
		if ( !self::$pageids_to_revive ) {
			return true;
		}

		foreach ( self::$pageids_to_revive as $pageid => $title ) {
			if ( $pageid == 0 ) {
				continue;
			}

			// Try to get comment for old versions where it isn't passed, hacky :(
			if ( !$comment ) {
				global $wgRequest;
				$comment = $wgRequest->getText( 'wpComment' );
			}

			// TX has not been committed yet, so we must select from the master
			$dbw = MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();
			$res = $dbw->newSelectQueryBuilder()
				->select( '*' )
				->from( 'thread' )
				->where( [ 'thread_root' => $pageid ] )
				->caller( __METHOD__ )
				->fetchResultSet();
			$threads = Threads::loadFromResult( $res, $dbw );

			if ( count( $threads ) ) {
				$thread = array_pop( $threads );
				$thread->setRoot( new Article( $title, 0 ) );
				$thread->undelete( $comment );
			} else {
				wfDebug( __METHOD__ . ":No thread found with root set to $pageid (??)\n" );
			}
		}

		// Synchronise the first 500 threads, in reverse order by thread id. If
		// there are more threads to synchronise, the job queue will take over.
		Threads::synchroniseArticleData(
			MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $udTitle ),
			500,
			'cascade'
		);

		return true;
	}

	/**
	 * @param Article $article
	 * @param OutputPage $out
	 * @param string &$reason
	 * @return bool
	 */
	public static function onArticleConfirmDelete( $article, $out, &$reason ) {
		if ( $article->getTitle()->getNamespace() != NS_LQT_THREAD ) {
			return true;
		}

		$thread = Threads::withRoot( $article->getPage() );

		if ( !$thread ) {
			return true;
		}

		if ( $thread->isTopmostThread() && count( $thread->replies() ) ) {
			$out->wrapWikiMsg(
				'<strong>$1</strong>',
				'lqt-delete-parent-warning'
			);
		}

		return true;
	}

	public static function onArticleDelete( $wikiPage ) {
		// Synchronise article data so that moving the article doesn't break any
		// article association.
		Threads::synchroniseArticleData( $wikiPage );

		return true;
	}
}
