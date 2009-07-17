<?php

class LqtDeletionController {
	static $pageids_to_revive;
	
	static function onArticleDeleteComplete( &$article, &$user, $reason, $id ) {
		$title = $article->getTitle();
		
		if ($title->getNamespace() != NS_LQT_THREAD) {
			return true;
		}
		
		$threads = Threads::where( array( 'thread_root' => $id ) );
		
		if (!count($threads)) {
			wfDebugLog( __METHOD__.": no threads with root $id, ignoring...\n" );
			return true;
		}
		
		$thread = $threads[0];
		
		// Mark the thread as deleted
		$thread->delete($reason);
		
		// Avoid orphaning subthreads, update their parentage.
		foreach( $thread->replies() as $reply ) {
			$reply->setSuperthread( $thread->superthread() );
			$reply->commitRevision( Threads::CHANGE_PARENT_DELETED, null, $reason );
		}
		
		return true;
	}
	
	static function onArticleDelete( &$article, &$user, &$reason, &$error ) {
		$thread = Threads::withRoot( $article );
		
		if ( is_object( $thread ) && $thread->isTopmostThread() && count($thread->replies())) {
			$error = wfMsgExt( 'lqt-delete-has-subthreads', 'parse' );
			return false;
		}
		
		return true;
	}
	
	static function onArticleRevisionUndeleted( &$title, $revision, $page_id ) {
		if ( $title->getNamespace() == NS_LQT_THREAD ) {
			self::$pageids_to_revive[$page_id] = $title;
		}
		
		return true;
	}
	
	static function onArticleUndelete( &$udTitle, $created, $comment = '' ) {
		foreach( self::$pageids_to_revive as $pageid => $title ) {
			if ($pageid == 0) {
				continue;
			}
			
			// Try to get comment for old versions where it isn't passed, hacky :(
			if (!$comment) {
				global $wgRequest;
				$comment = $wgRequest->getText( 'wpComment' );
			}
			
			// TX has not been committed yet, so we must select from the master
			$dbw = wfGetDB( DB_MASTER );
			$res = $dbw->select( 'thread', '*', array( 'thread_root' => $pageid ), __METHOD__ );
			$threads = Threads::loadFromResult( $res, $dbw );
			
			if ( count($threads) ) {
				$thread = $threads[0];
				$thread->setRoot( new Article( $title ) );
				$thread->undelete( $comment );
			} else {
				wfDebug( __METHOD__. ":No thread found with root set to $pageid (??)\n" );
			}
		}
		
		return true;
	}
}
