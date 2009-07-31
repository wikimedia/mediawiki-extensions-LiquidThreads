<?php

class LqtDispatch {
	public static $views = array(
		'TalkpageArchiveView' => 'TalkpageArchiveView',
		'TalkpageHeaderView' => 'TalkpageHeaderView',
		'TalkpageView' => 'TalkpageView',
		'ThreadHistoryListingView' => 'ThreadHistoryListingView',
		'ThreadHistoricalRevisionView' => 'ThreadHistoricalRevisionView',
		'IndividualThreadHistoryView' => 'IndividualThreadHistoryView',
		'ThreadDiffView' => 'ThreadDiffView',
		'ThreadPermalinkView' => 'ThreadPermalinkView',
		'ThreadProtectionFormView' => 'ThreadProtectionFormView',
		'ThreadWatchView' => 'ThreadWatchView',
		'SummaryPageView' => 'SummaryPageView'
		);
		
	/** static cache of per-page LiquidThreads activation setting */
	static $userLQTActivated;

	static function talkpageMain( &$output, &$article, &$title, &$user, &$request ) {
		// We are given a talkpage article and title. Fire up a TalkpageView
		
		if ( $title->getNamespace() == NS_LQT_THREAD + 1 /* talk page */ ) {
			// Threads don't have talk pages; redirect to the thread page.
			$output->redirect( $title->getSubjectPage()->getFullUrl() );
			return false;
		}
		
		// If we came here from a red-link, redirect to the thread page.
		$redlink = $request->getCheck( 'redlink' ) &&
					$request->getText( 'action' ) == 'edit';
		if( $redlink ) {
			$output->redirect( $title->getFullURL() );
			return false;
		}

		/* Certain actions apply to the "header", which is stored in the actual talkpage
		   in the database. Drop everything and behave like a normal page if those
		   actions come up, to avoid hacking the various history, editing, etc. code. */
		$action =  $request->getVal( 'action' );
		$header_actions = array( 'history', 'edit', 'submit', 'delete' );
		global $wgRequest;
		if ( $request->getVal( 'lqt_method', null ) === null &&
				( in_array( $action, $header_actions ) ||
					$request->getVal( 'diff', null ) !== null ) ) {
			// Pass through wrapper
			$viewname = self::$views['TalkpageHeaderView'];
		} else if ( $action == 'protect' || $action == 'unprotect' ) {
			// Pass through wrapper
			$viewname = self::$views['ThreadProtectionFormView'];
		} else {
			$viewname = self::$views['TalkpageView'];
		}
		$view = new $viewname( $output, $article, $title, $user, $request );
		return $view->show();
	}

	static function threadPermalinkMain( &$output, &$article, &$title, &$user, &$request ) {

		$action =  $request->getVal( 'action' );
		$lqt_method = $request->getVal( 'lqt_method' );

		if ( $lqt_method == 'thread_history' ) {
			$viewname = self::$views['ThreadHistoryListingView'];
		} else if ( $lqt_method == 'diff' ) {
			// this clause and the next must be in this order.
			$viewname = self::$views['ThreadDiffView'];
		} else if ( $action == 'history'
			|| $request->getVal( 'diff', null ) !== null
			|| $request->getVal( 'oldid', null ) !== null ) {
			$viewname = self::$views['IndividualThreadHistoryView'];
		} else if ( $action == 'protect' || $action == 'unprotect' ) {
			$viewname = self::$views['ThreadProtectionFormView'];
		} else if ( $request->getVal( 'lqt_oldid', null ) !== null ) {
			$viewname = self::$views['ThreadHistoricalRevisionView'];
		} else if ( $action == 'watch' || $action == 'unwatch' ) {
			$viewname = self::$views['ThreadWatchView'];
		} elseif ( $action == 'delete' ) {
			return true;
		} else {
			$viewname = self::$views['ThreadPermalinkView'];
		}
		
		$view = new $viewname( $output, $article, $title, $user, $request );
		return $view->show();
	}

	static function threadSummaryMain( &$output, &$article, &$title, &$user, &$request ) {
		$viewname = self::$views['SummaryPageView'];
		$view = new $viewname( $output, $article, $title, $user, $request );
		return $view->show();
	}
	
	static function isLqtPage( $title ) {
		global $wgLqtPages, $wgLqtTalkPages;
		$isTalkPage = ($title->isTalkPage() && $wgLqtTalkPages) ||
						in_array( $title->getPrefixedText(), $wgLqtPages ) ||
						self::hasUserEnabledLQT( $title->getArticleId() );
		
		return $isTalkPage;
	}
	
	static function hasUserEnabledLQT( $article ) {
	
		if (is_object($article)) {
			$article = $article->getId();
		}
		
		// Instance cache
		if ( isset( self::$userLQTActivated[$article] ) ) {
			$cacheVal = self::$userLQTActivated[$article];

			return $cacheVal;
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
									array( 'pp_propname' => 'use-liquid-threads',
											'pp_page' => $article ), __METHOD__ );
		
		if ($dbVal) {
			self::$userLQTActivated[$article] = true;
#			$wgMemc->set( $key, $dbVal, 1800 );
			return true;
		} else {
			// Negative caching.
			self::$userLQTActivated[$article] = false;
#			$wgMemc->set( $key, -1, 86400 );
			return false;
		}
	}

	/**
	* If the page we recieve is a LiquidThreads page of any kind, process it
	* as needed and return True. If it's a normal, non-liquid page, return false.
	*/
	static function tryPage( $output, $article, $title, $user, $request ) {
		if ( LqtDispatch::isLqtPage( $title ) ) {
			// LiquidThreads pages, Talk:X etc
			return self::talkpageMain( $output, $article, $title, $user, $request );
		} else if ( $title->getNamespace() == NS_LQT_THREAD ) {
			// Thread permalink pages, Thread:X
			return self::threadPermalinkMain( $output, $article, $title, $user, $request );
		} else if ( $title->getNamespace() == NS_LQT_SUMMARY ) {
			// Summary pages, Summary:X
			return self::threadSummaryMain( $output, $article, $title, $user, $request );
		}
		return true;
	}
}
