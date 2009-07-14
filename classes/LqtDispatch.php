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

	static function talkpageMain( &$output, &$talk_article, &$title, &$user, &$request ) {
		// We are given a talkpage article and title. Find the associated
		// non-talk article and pass that to the view.
		$article = new Article( $title );

		if ( $title->getNamespace() == NS_LQT_THREAD + 1 /* talk page */ ) {
			// Threads don't have talk pages; redirect to the thread page.
			$output->redirect( $title->getSubjectPage()->getFullUrl() );
			return false;
		}
		
		// If we came here from a red-link, redirect to the thread page.
		$redlink = $request->getCheck( 'redlink' );
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
		} else if ( $request->getVal( 'lqt_method' ) == 'talkpage_archive' ) {
			$viewname = self::$views['TalkpageArchiveView'];
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
		}
		else if ( $lqt_method == 'diff' ) { // this clause and the next must be in this order.
			$viewname = self::$views['ThreadDiffView'];
		}
		else if ( $action == 'history'
			|| $request->getVal( 'diff', null ) !== null
			|| $request->getVal( 'oldid', null ) !== null ) {
			$viewname = self::$views['IndividualThreadHistoryView'];
		}
		else if ( $action == 'protect' || $action == 'unprotect' ) {
			$viewname = self::$views['ThreadProtectionFormView'];
		}
		else if ( $request->getVal( 'lqt_oldid', null ) !== null ) {
			$viewname = self::$views['ThreadHistoricalRevisionView'];
		}
		else if ( $action == 'watch' || $action == 'unwatch' ) {
			$viewname = self::$views['ThreadWatchView'];
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
	* If the page we recieve is a Liquid Threads page of any kind, process it
	* as needed and return True. If it's a normal, non-liquid page, return false.
	*/
	static function tryPage( $output, $article, $title, $user, $request ) {
		if ( LqtDispatch::isLqtPage( $title ) ) {
			return self::talkpageMain ( $output, $article, $title, $user, $request );
		} else if ( $title->getNamespace() == NS_LQT_THREAD ) {
			return self::threadPermalinkMain( $output, $article, $title, $user, $request );
		} else if ( $title->getNamespace() == NS_LQT_SUMMARY ) {
			return self::threadSummaryMain( $output, $article, $title, $user, $request );
		}
		return true;
	}

	static function onPageMove( $movepage, $ot, $nt ) {
		// We are being invoked on the subject page, not the talk page.

		$threads = Threads::where( array( Threads::articleClause( new Article( $ot ) ),
		                                  Threads::topLevelClause() ) );

		foreach ( $threads as $t ) {
			$t->moveToPage( $nt, false );
		}

		return true;
	}

	static function makeLinkObj( &$returnValue, &$linker, $nt, $text, $query, $trail, $prefix ) {
		if ( ! $nt->isTalkPage() )
			return true;

		// Talkpages with headers.
		if ( $nt->getArticleID() != 0 )
			return true;

		// Talkpages without headers -- check existance of threads.
		$article = new Article( $nt );
		$threads = Threads::where( Threads::articleClause( $article ), "LIMIT 1" );
		if ( count( $threads ) == 0 ) {
			// We want it to look like a broken link, but not have action=edit, since that
			// will edit the header, so we can't use makeBrokenLinkObj. This code is copied
			// from the body of that method.
			$url = $nt->escapeLocalURL( $query );
			if ( '' == $text )
				$text = htmlspecialchars( $nt->getPrefixedText() );
			$style = $linker->getInternalLinkAttributesObj( $nt, $text, "yes" );
			list( $inside, $trail ) = Linker::splitTrail( $trail );
			$returnValue = "<a href=\"{$url}\"{$style}>{$prefix}{$text}{$inside}</a>{$trail}";
		}
		else {
			$returnValue = $linker->makeKnownLinkObj( $nt, $text, $query, $trail, $prefix );
		}
		return false;
	}

	// One major place that doesn't use makeLinkObj is the tabs. So override known/unknown there too.
	static function tabAction( &$skintemplate, $title, $message, $selected, $checkEdit,
			&$classes, &$query, &$text, &$result ) {
		if ( ! $title->isTalkPage() )
			return true;
		if ( $title->getArticleID() != 0 ) {
			$query = "";
			return true;
		}
		// It's a talkpage without a header. Get rid of action=edit always,
		// color as apropriate.
		$query = "";
		$article = new Article( $title );
		$threads = Threads::where( Threads::articleClause( $article ), "LIMIT 1" );
		if ( count( $threads ) != 0 ) {
			$i = array_search( 'new', $classes ); if ( $i !== false ) {
				array_splice( $classes, $i, 1 );
			}
		}
		return true;
	}

	static function customizeOldChangesList( &$changeslist, &$s, $rc ) {
		if ( $rc->getTitle()->getNamespace() == NS_LQT_THREAD ) {
			$thread = Threads::withRoot( new Post( $rc->getTitle() ) );
			if ( !$thread ) return true;

			LqtView::addJSandCSS();
			wfLoadExtensionMessages( 'LiquidThreads' );

			if ( $rc->mAttribs['rc_type'] != RC_NEW ) {
				// Add whether it was original author.
				// TODO: this only asks whether ANY edit has been by another, not this edit.
				// But maybe that's what we want.
				if ( $thread->editedness() == Threads::EDITED_BY_OTHERS ) {
					$appendix = Xml::tags( 'span',
											array( 'class' => 'lqt_rc_author_notice ' .
															'lqt_rc_author_notice_others' ),
											wfMsgExt( 'lqt_rc_author_others', 'parseinline' )
										);
				} else {
					$appendix = Xml::tags( 'span',
											array( 'class' => 'lqt_rc_author_notice ' .
															'lqt_rc_author_notice_original' ),
											wfMsgExt( 'lqt_rc_author_others', 'parseinline' )
										);
				}
				$s = preg_replace( '/\<\/li\>$/', $appendix . '</li>', $s ); // TODO ew
			}
			else {
				global $wgOut;
				
				$sig = "";
				$changeslist->insertUserRelatedLinks( $sig, $rc );

				// This should be stored in RC.
				$quote = Revision::newFromId( $rc->mAttribs['rc_this_oldid'] )->getText();
				if ( strlen( $quote ) > 230 ) {
					$sk = $changeslist->skin;
					$quote = substr( $quote, 0, 200 ) .
						$sk->link( $thread->title(), wfMsg( 'lqt_rc_ellipsis' ),
							array( 'class' => 'lqt_rc_ellipsis' ), array(), array( 'known' ) );
				}
				
				$quote = $wgOut->parseInline( $quote );

				if ( $thread->isTopmostThread() ) {
					$message_name = 'lqt_rc_new_discussion';
					$tmp_title = $thread->title();
				} else {
					$message_name = 'lqt_rc_new_reply';
					$tmp_title = $thread->topmostThread()->title();
					$tmp_title->setFragment( '#' . LqtView::anchorName( $thread ) );
				}

				$thread_link = $changeslist->skin->link(
					$tmp_title,
					htmlspecialchars($thread->subjectWithoutIncrement()),
					array(), array(), array( 'known' ) );

				$talkpage_link = $changeslist->skin->link(
					$thread->article()->getTitle(),
					null,
					array(), array(), array( 'known' ) );

				$s = wfMsg( $message_name, $thread_link, $talkpage_link, $sig )
					. Xml::tags( 'blockquote', array( 'class' => 'lqt_rc_blockquote' ), $quote );
			}
		}
		return true;
	}

	static function setNewtalkHTML( $skintemplate, $tpl ) {
		global $wgUser, $wgTitle, $wgOut;
		wfLoadExtensionMessages( 'LiquidThreads' );
		$newmsg_t = SpecialPage::getTitleFor( 'NewMessages' );
		$watchlist_t = SpecialPage::getTitleFor( 'Watchlist' );
		$usertalk_t = $wgUser->getTalkPage();
		if ( $wgUser->getNewtalk()
				&& ! $newmsg_t->equals( $wgTitle )
				&& ! $watchlist_t->equals( $wgTitle )
				&& ! $usertalk_t->equals( $wgTitle )
				) {
			$s = wfMsgExt( 'lqt_youhavenewmessages', array( 'parseinline' ), $newmsg_t->getFullURL() );
			$tpl->set( "newtalk", $s );
			$wgOut->setSquidMaxage( 0 );
		} else {
			$tpl->set( "newtalk", '' );
		}

		return true;
	}
}
