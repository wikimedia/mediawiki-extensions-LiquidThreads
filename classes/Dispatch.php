<?php

class LqtDispatch {
	/** static cache of per-page LiquidThreads activation setting */
	static $userLqtOverride;
	static $primaryView = null;

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
		if ( $redlink ) {
			$output->redirect( $title->getFullURL() );
			return false;
		}

		/* Certain actions apply to the "header", which is stored in the actual talkpage
		   in the database. Drop everything and behave like a normal page if those
		   actions come up, to avoid hacking the various history, editing, etc. code. */
		$action =  $request->getVal( 'action' );
		$header_actions = array( 'history', 'edit', 'submit', 'delete' );
		global $wgRequest;
		
		$lqt_action = $request->getVal( 'lqt_method' );
		if ( $action == 'edit' && $request->getVal( 'section' ) == 'new' ) {
			// Hijack section=new for "new thread".
			$request->setVal( 'lqt_method', 'talkpage_new_thread' );
			$request->setVal( 'section', '' );
			
			$viewname = 'TalkpageView';
			
		} elseif ( !$lqt_action && ( in_array( $action, $header_actions ) ||
				$request->getVal( 'diff', null ) !== null ) ) {
			// Pass through wrapper
			$viewname = 'TalkpageHeaderView';
		} elseif ( $action == 'protect' || $action == 'unprotect' ) {
			// Pass through wrapper
			$viewname = 'ThreadProtectionFormView';
		} elseif ( $lqt_action == 'talkpage_history' ) {
			$viewname = 'TalkpageHistoryView';
		} else {
			$viewname = 'TalkpageView';
		}
		
		$view = new $viewname( $output, $article, $title, $user, $request );
		self::$primaryView = $view;
		return $view->show();
	}

	static function threadPermalinkMain( &$output, &$article, &$title, &$user, &$request ) {

		$action =  $request->getVal( 'action' );
		$lqt_method = $request->getVal( 'lqt_method' );

		if ( $lqt_method == 'thread_history' ) {
			$viewname = 'ThreadHistoryListingView';
		} else if ( $lqt_method == 'diff' ) {
			// this clause and the next must be in this order.
			$viewname = 'ThreadDiffView';
		} else if ( $action == 'history'
			|| $request->getVal( 'diff', null ) !== null
			|| $request->getVal( 'oldid', null ) !== null ) {
			$viewname = 'IndividualThreadHistoryView';
		} else if ( $action == 'protect' || $action == 'unprotect' ) {
			$viewname = 'ThreadProtectionFormView';
		} else if ( $request->getVal( 'lqt_oldid', null ) !== null ) {
			$viewname = 'ThreadHistoricalRevisionView';
		} else if ( $action == 'watch' || $action == 'unwatch' ) {
			$viewname = 'ThreadWatchView';
		} elseif ( $action == 'delete' ) {
			return true;
		} else {
			$viewname = 'ThreadPermalinkView';
		}
		
		$view = new $viewname( $output, $article, $title, $user, $request );
		self::$primaryView = $view;
		return $view->show();
	}

	static function threadSummaryMain( &$output, &$article, &$title, &$user, &$request ) {
		$viewname = 'SummaryPageView';
		$view = new $viewname( $output, $article, $title, $user, $request );
		self::$primaryView = $view;
		return $view->show();
	}
	
	static function isLqtPage( $title ) {
		global $wgLqtPages, $wgLqtTalkPages;
		$isTalkPage = ( $title->isTalkPage() && $wgLqtTalkPages ) ||
				in_array( $title->getPrefixedText(), $wgLqtPages );
				
		$override = self::getUserLqtOverride( $title->getArticleId() );
		
		if ( !is_null($override) ) {
			$isTalkPage = $override;
		}
		
		$isTalkPage = $isTalkPage && !$title->isRedirect();
		
		return $isTalkPage;
	}
	
	static function getUserLqtOverride( $article ) {
		if ( is_object( $article ) ) {
			$article = $article->getId();
		}
		
		// Instance cache
		if ( isset( self::$userLqtOverride[$article] ) ) {
			$cacheVal = self::$userLqtOverride[$article];

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
		
		$row = $dbr->selectRow( 'page_props', 'pp_value',
					array( 'pp_propname' => 'use-liquid-threads',
						'pp_page' => $article ), __METHOD__ );
		
		if ( $row ) {
			$dbVal = $row->pp_value;
			
			self::$userLqtOverride[$article] = $dbVal;
#			$wgMemc->set( $key, $dbVal, 1800 );
			return $dbVal;
		} else {
			// Negative caching.
			self::$userLqtOverride[$article] = null;
#			$wgMemc->set( $key, -1, 86400 );
			return null;
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
	
	static function onSkinTemplateNavigation( $skinTemplate, &$links ) {
		if ( !self::$primaryView ) return true;
		
		self::$primaryView->customizeNavigation( $skinTemplate, $links );
		
		return true;
	}
	
	static function onSkinTemplateTabs( $skinTemplate, &$links ) {
		if ( !self::$primaryView ) return true;
		
		self::$primaryView->customizeTabs( $skinTemplate, $links );
		
		return true;
	}
}
