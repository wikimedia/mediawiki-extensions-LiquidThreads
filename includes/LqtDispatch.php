<?php

use MediaWiki\Context\RequestContext;
use MediaWiki\Language\Language;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use MediaWiki\Page\Article;
use MediaWiki\Request\WebRequest;
use MediaWiki\Title\Title;
use MediaWiki\User\User;

class LqtDispatch {
	/** @var (int|null)[] static cache of per-page LiquidThreads activation setting */
	public static $userLqtOverride = [];
	/** @var LqtView|null */
	public static $primaryView = null;

	/**
	 * @param OutputPage &$output
	 * @param Article &$article
	 * @param Title &$title
	 * @param User &$user
	 * @param WebRequest &$request
	 * @return bool
	 */
	public static function talkpageMain( &$output, &$article, &$title, &$user, &$request ) {
		// We are given a talkpage article and title. Fire up a TalkpageView

		if ( $title->getNamespace() == NS_LQT_THREAD + 1 /* talk page */ ) {
			// Threads don't have talk pages; redirect to the thread page.
			$output->redirect( $title->getSubjectPage()->getLocalURL() );
			return false;
		}

		// If we came here from a red-link, redirect to the thread page.
		$redlink = $request->getCheck( 'redlink' ) &&
			$request->getText( 'action' ) == 'edit';
		if ( $redlink ) {
			$output->redirect( $title->getLocalURL() );
			return false;
		}

		$action = $request->getVal( 'action', 'view' );

		// Actions handled by LQT.
		$lqt_actions = [ 'view', 'protect', 'unprotect' ];

		$lqt_action = $request->getVal( 'lqt_method' );
		if ( $action == 'edit' && $request->getVal( 'section' ) == 'new' ) {
			// Hijack section=new for "new thread".
			$request->setVal( 'lqt_method', 'talkpage_new_thread' );
			$request->setVal( 'section', '' );

			$viewname = TalkpageView::class;
		} elseif ( !$lqt_action && (
			( !in_array( $action, $lqt_actions ) && $action ) ||
			$request->getVal( 'diff', null ) !== null ||
			$request->getVal( 'oldid', null ) !== null )
		) {
			// Pass through wrapper
			$viewname = TalkpageHeaderView::class;
		} elseif ( $action == 'protect' || $action == 'unprotect' ) {
			// Pass through wrapper
			$viewname = ThreadProtectionFormView::class;
		} elseif ( $lqt_action == 'talkpage_history' ) {
			$viewname = TalkpageHistoryView::class;
		} else {
			$viewname = TalkpageView::class;
		}

		Thread::$titleCacheById[$article->getPage()->getId()] = $title;

		/** @var LqtView $view */
		$view = new $viewname( $output, $article, $title, $user, $request );
		self::$primaryView = $view;
		return $view->show();
	}

	/**
	 * @param OutputPage &$output
	 * @param Article &$article
	 * @param Title &$title
	 * @param User &$user
	 * @param WebRequest &$request
	 * @return bool
	 */
	public static function threadPermalinkMain( &$output, &$article, &$title, &$user, &$request ) {
		$action = $request->getVal( 'action' );
		$lqt_method = $request->getVal( 'lqt_method' );

		if ( $lqt_method == 'thread_history' ) {
			$viewname = ThreadHistoryListingView::class;
		} elseif ( $lqt_method == 'diff' ) {
			// this clause and the next must be in this order.
			$viewname = ThreadDiffView::class;
		} elseif ( $action == 'history'
			|| $request->getVal( 'diff', null ) !== null ) {
			$viewname = IndividualThreadHistoryView::class;
		} elseif ( $action == 'protect' || $action == 'unprotect' ) {
			$viewname = ThreadProtectionFormView::class;
		} elseif ( $request->getVal( 'lqt_oldid', null ) !== null ) {
			$viewname = ThreadHistoricalRevisionView::class;
		} elseif ( $action == 'watch' || $action == 'unwatch' ) {
			$viewname = ThreadWatchView::class;
		} elseif ( $action == 'delete' || $action == 'rollback' || $action == 'markpatrolled' ) {
			return true;
		} else {
			$viewname = ThreadPermalinkView::class;
		}

		/** @var LqtView $view */
		$view = new $viewname( $output, $article, $title, $user, $request );
		self::$primaryView = $view;
		return $view->show();
	}

	public static function threadSummaryMain( &$output, &$article, &$title, &$user, &$request ) {
		$view = new SummaryPageView( $output, $article, $title, $user, $request );
		self::$primaryView = $view;
		return $view->show();
	}

	/**
	 * @param Title|null $title
	 * @return bool|null
	 */
	public static function isLqtPage( $title ) {
		if ( !$title ) {
			return false;
		}
		// Ignore it if it's a thread or a summary, makes no sense to have LiquidThreads there.
		if ( $title->getNamespace() == NS_LQT_THREAD || $title->getNamespace() == NS_LQT_SUMMARY ) {
			return false;
		}

		global $wgLqtPages, $wgLqtTalkPages, $wgLqtNamespaces;
		$isTalkPage = ( $title->isTalkPage() && $wgLqtTalkPages ) ||
			in_array( $title->getPrefixedText(), $wgLqtPages );

		if ( in_array( $title->getNamespace(), $wgLqtNamespaces ) ) {
			$isTalkPage = true;
		}

		if ( $title->exists() ) {
			$override = self::getUserLqtOverride( $title );
		} else {
			$override = null;
		}

		global $wgLiquidThreadsAllowUserControl;
		if ( $override !== null && $wgLiquidThreadsAllowUserControl ) {
			$isTalkPage = $override;
		}

		$isTalkPage = $isTalkPage && !$title->isRedirect();

		MediaWikiServices::getInstance()->getHookContainer()
			->run( 'LiquidThreadsIsLqtPage', [ $title, &$isTalkPage ] );

		return $isTalkPage;
	}

	/**
	 * @param Title $title
	 * @return null|int
	 */
	public static function getUserLqtOverride( $title ) {
		if ( !is_object( $title ) ) {
			return null;
		}

		global $wgLiquidThreadsAllowUserControl;

		if ( !$wgLiquidThreadsAllowUserControl ) {
			return null;
		}

		global $wgLiquidThreadsAllowUserControlNamespaces;

		$namespaces = $wgLiquidThreadsAllowUserControlNamespaces;

		if ( $namespaces !== null && !in_array( $title->getNamespace(), $namespaces ) ) {
			return null;
		}

		$articleid = $title->getArticleID();

		// Check instance cache.
		if ( array_key_exists( $articleid, self::$userLqtOverride ) ) {
			return self::$userLqtOverride[$articleid];
		}

		// Load from the database.
		$dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();

		$row = $dbr->newSelectQueryBuilder()
			->select( 'pp_value' )
			->from( 'page_props' )
			->where( [
				'pp_propname' => 'use-liquid-threads',
				'pp_page' => $articleid
			] )
			->caller( __METHOD__ )
			->fetchRow();

		if ( $row ) {
			$dbVal = $row->pp_value;

			self::$userLqtOverride[$articleid] = $dbVal;
			return $dbVal;
		} else {
			// Negative caching.
			self::$userLqtOverride[$articleid] = null;
			return null;
		}
	}

	/**
	 * If the page we receive is a LiquidThreads page of any kind, process it
	 * as needed and return True. If it's a normal, non-liquid page, return false.
	 * @param OutputPage $output
	 * @param Article $article
	 * @param Title $title
	 * @param User $user
	 * @param WebRequest $request
	 * @return bool
	 */
	public static function tryPage( $output, $article, $title, $user, $request ) {
		if ( self::isLqtPage( $title ) ) {
			// LiquidThreads pages, Talk:X etc
			return self::talkpageMain( $output, $article, $title, $user, $request );
		} elseif ( $title->getNamespace() == NS_LQT_THREAD ) {
			// Thread permalink pages, Thread:X
			return self::threadPermalinkMain( $output, $article, $title, $user, $request );
		} elseif ( $title->getNamespace() == NS_LQT_SUMMARY ) {
			// Summary pages, Summary:X
			return self::threadSummaryMain( $output, $article, $title, $user, $request );
		}
		return true;
	}

	public static function onSkinTemplateNavigation( $skinTemplate, &$links ) {
		if ( !self::$primaryView ) {
			return true;
		}

		self::$primaryView->customizeNavigation( $skinTemplate, $links );

		return true;
	}

	/**
	 * Most stuff is in the user language.
	 * @param Title $title
	 * @param Language &$pageLang
	 */
	public static function onPageContentLanguage( $title, &$pageLang ) {
		global $wgRequest;
		$method = $wgRequest->getVal( 'lqt_method' );
		$oldid = $wgRequest->getVal( 'lqt_oldid' );
		if ( $title->inNamespace( NS_LQT_THREAD ) ) {
			$pageLang = RequestContext::getMain()->getLanguage();
		} elseif ( $method == 'diff' ) {
			# the diff contains the wikitext, which is in the content language
			return;
		} elseif ( $method == 'talkpage_history' || $method == 'thread_history' || $oldid != '' ) {
			$pageLang = RequestContext::getMain()->getLanguage();
		}
	}
}
