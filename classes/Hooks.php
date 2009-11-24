<?php

class LqtHooks {
	// Used to inform hooks about edits that are taking place.
	public static $editType = null;
	public static $editThread = null;
	public static $editAppliesTo = null;
	public static $editArticle = null;
	public static $editTalkpage = null;
	
	static function customizeOldChangesList( &$changeslist, &$s, $rc ) {
		if ( $rc->getTitle()->getNamespace() != NS_LQT_THREAD )
			return true;
	
		$thread = Threads::withRoot( new Article( $rc->getTitle() ) );
		if ( !$thread ) return true;

		LqtView::addJSandCSS();
		wfLoadExtensionMessages( 'LiquidThreads' );

		if ( $rc->mAttribs['rc_new'] ) {
			global $wgOut;
			
			$sig = "";
			$changeslist->insertUserRelatedLinks( $sig, $rc );

			// This should be stored in RC.
			$rev = Revision::newFromId( $rc->mAttribs['rc_this_oldid'] );
			$quote = $rev->getText();
			$link = '';
			if ( strlen( $quote ) > 230 ) {
				$sk = $changeslist->skin;
				$quote = substr( $quote, 0, 200 );
				$link = $sk->link( $thread->title(), wfMsg( 'lqt_rc_ellipsis' ),
						array( 'class' => 'lqt_rc_ellipsis' ), array(), array( 'known' ) );
			}
			
			$quote = htmlspecialchars( $quote ) . $link;

			if ( $thread->isTopmostThread() ) {
				$message_name = 'lqt_rc_new_discussion';
			} else {
				$message_name = 'lqt_rc_new_reply';
			}
			
			$tmp_title = $thread->article()->getTitle();
			$tmp_title->setFragment( '#' . LqtView::anchorName( $thread ) );
			
			// Make sure it points to the right page. The Pager seems to use the DB
			//  representation of a timestamp for its offset field, odd.
			$dbr = wfGetDB( DB_SLAVE );
			$offset = wfTimestamp( TS_UNIX, $thread->topmostThread()->sortkey() ) + 1;
			$offset = $dbr->timestamp( $offset );

			$thread_link = $changeslist->skin->link( $tmp_title,
				htmlspecialchars( $thread->subjectWithoutIncrement() ),
				array(), array( 'offset' => $offset ), array( 'known' ) );

			$talkpage_link = $changeslist->skin->link(
				$thread->article()->getTitle(),
				null,
				array(), array(), array( 'known' ) );

			$s = wfMsg( $message_name, $thread_link, $talkpage_link, $sig )
				. Xml::tags( 'blockquote', array( 'class' => 'lqt_rc_blockquote' ), $quote );
				
			$classes = array();
			$changeslist->insertTags( $s, $rc, $classes );
			$changeslist->insertExtra( $s, $rc, $classes );
		} else {
			// Add whether it was original author.
			if ( $thread->author()->getName() != $rc->mAttribs['rc_user_text'] ) {
				$appendix = Xml::tags( 'span',
										array( 'class' => 'lqt_rc_author_notice ' .
														'lqt_rc_author_notice_others' ),
										wfMsgExt( 'lqt_rc_author_others', 'parseinline' )
									);
			
				$s .= ' ' . $appendix;
			}
		}
		return true;
	}

	static function setNewtalkHTML( $skintemplate, $tpl ) {
		global $wgUser, $wgTitle, $wgOut;
		
		if ( ! LqtDispatch::isLqtPage( $wgUser->getTalkPage() ) ) {
			return true;
		}
		
		wfLoadExtensionMessages( 'LiquidThreads' );
		$newmsg_t = SpecialPage::getTitleFor( 'NewMessages' );
		$watchlist_t = SpecialPage::getTitleFor( 'Watchlist' );
		$usertalk_t = $wgUser->getTalkPage();
		if ( $wgUser->getNewtalk()
				&& ! $newmsg_t->equals( $wgTitle )
				&& ! $watchlist_t->equals( $wgTitle )
				&& ! $usertalk_t->equals( $wgTitle )
				) {
			$s = wfMsgExt( 'lqt_youhavenewmessages', array( 'parseinline' ),
							$newmsg_t->getFullURL() );
			$tpl->set( "newtalk", $s );
			$wgOut->setSquidMaxage( 0 );
		} else {
			$tpl->set( "newtalk", '' );
		}

		return true;
	}
	
	static function beforeWatchlist( &$conds, &$tables, &$join_conds, &$fields ) {
		global $wgOut, $wgUser;
		
		$db = wfGetDB( DB_SLAVE );
	
		if ( !in_array( 'page', $tables ) ) {
			$tables[] = 'page';
			// Yes, this is the correct field to join to. Weird naming.
			$join_conds['page'] = array( 'LEFT JOIN', 'rc_cur_id=page_id' );
		}
		$conds[] = "page_namespace != " . $db->addQuotes( NS_LQT_THREAD );
	
		$talkpage_messages = NewMessages::newUserMessages( $wgUser );
		$tn = count( $talkpage_messages );
	
		$watch_messages = NewMessages::watchedThreadsForUser( $wgUser );
		$wn = count( $watch_messages );
	
		if ( $tn == 0 && $wn == 0 )
			return true;
	
		LqtView::addJSandCSS();
		wfLoadExtensionMessages( 'LiquidThreads' );
		$messages_title = SpecialPage::getTitleFor( 'NewMessages' );
		$new_messages = wfMsgExt( 'lqt-new-messages', 'parseinline' );
		
		$sk = $wgUser->getSkin();
		$link = $sk->link( $messages_title, $new_messages,
					array( 'class' => 'lqt_watchlist_messages_notice' ) );
		$wgOut->addHTML( $link );
	
		return true;
	}
	
	static function getPreferences( $user, &$preferences ) {
		global $wgEnableEmail;
		wfLoadExtensionMessages( 'LiquidThreads' );
		
		// Whether or not to show user signatures
		$preferences['lqtcustomsignatures'] =
			array(
				'type' => 'toggle',
				'label-message' => 'lqt-preference-custom-signatures',
				'section' => 'lqt',
			);
		
		if ( $wgEnableEmail ) {
			$preferences['lqtnotifytalk'] =
				array(
					'type' => 'toggle',
					'label-message' => 'lqt-preference-notify-talk',
					'section' => 'personal/email'
				);
		}
		
		
		$preferences['lqt-watch-threads'] =
			array(
				'type' => 'toggle',
				'label-message' => 'lqt-preference-watch-threads',
				'section' => 'watchlist/advancedwatchlist',
			);
		
		// Display depth and count
		$preferences['lqtdisplaydepth'] =
			array(
				'type' => 'int',
				'label-message' => 'lqt-preference-display-depth',
				'section' => 'lqt',
			);
			
		$preferences['lqtdisplaycount'] =
			array(
				'type' => 'int',
				'label-message' => 'lqt-preference-display-count',
				'section' => 'lqt',
			);
		
		return true;
	}
	
	static function updateNewtalkOnEdit( $article ) {
		$title = $article->getTitle();
		
		if ( LqtDispatch::isLqtPage( $title ) ) {
			// They're only editing the header, don't update newtalk.
			return false;
		}
		
		return true;
	}
	
	static function dumpThreadData( $writer, &$out, $row, $title ) {
		$editedStati = array( Threads::EDITED_NEVER => 'never',
								Threads::EDITED_HAS_REPLY => 'has-reply',
								Threads::EDITED_BY_AUTHOR => 'by-author',
								Threads::EDITED_BY_OTHERS => 'by-others' );
		$threadTypes = array( Threads::TYPE_NORMAL => 'normal',
								Threads::TYPE_MOVED => 'moved',
								Threads::TYPE_DELETED => 'deleted' );
		// Is it a thread
		if ( !empty($row->thread_id) ) {
			$thread = Thread::newFromRow( $row );
			$threadInfo = "\n";
			$attribs = array();
			$attribs['ThreadSubject'] = $thread->subject();
			if ( $thread->hasSuperThread() ) {
				$attribs['ThreadParent'] = $thread->superThread()->id();
			}
			$attribs['ThreadAncestor'] = $thread->topmostThread()->id();
			$attribs['ThreadPage'] = $thread->article()->getTitle()->getPrefixedText();
			$attribs['ThreadID'] = $thread->id();
			if ( $thread->hasSummary() && $thread->summary() ) {
				$attribs['ThreadSummaryPage'] = $thread->summary()->getId();
			}
			$attribs['ThreadAuthor'] = $thread->author()->getName();
			$attribs['ThreadEditStatus'] = $editedStati[$thread->editedness()];
			$attribs['ThreadType'] = $threadTypes[$thread->type()];
			
			foreach ( $attribs as $key => $value ) {
				$threadInfo .= "\t" . Xml::element( $key, null, $value ) . "\n";
			}
			
			$out .= Xml::tags( 'DiscussionThreading', null, $threadInfo ) . "\n";
		}
		
		return true;
	}
	
	static function modifyExportQuery( $db, &$tables, &$cond, &$opts, &$join ) {
		$tables[] = 'thread';
		
		$join['thread'] = array( 'left join', array( 'thread_root=page_id' ) );
		
		return true;
	}
	
	static function modifyOAIQuery( &$tables, &$fields, &$conds,
					&$options, &$join_conds ) {

		$tables[] = 'thread';

		$join_conds['thread'] = array( 'left join', array( 'thread_root=page_id' ) );

		$db = wfGetDB( DB_SLAVE );
		$fields[] = $db->tableName( 'thread' ) . '.*';

		return true;
	}
	
	static function customiseSearchResultTitle( &$title, &$text, $result, $terms, $page ) {
		if ( $title->getNamespace() != NS_LQT_THREAD ) {
			return true;
		}
		
		$thread = Threads::withRoot( new Article( $title ) );
		$text = $thread->subject();
		
		$title = clone $thread->topmostThread()->title();
		$title->setFragment( '#' . $thread->getAnchorName() );
		
		return true;
	}
	
	static function onUserRename( $renameUserSQL ) {
		// Always use the job queue, talk page edits will take forever
		$renameUserSQL->tablesJob['thread'] =
				array( 'thread_author_name', 'thread_author_id', 'thread_modified' );
		$renameUserSQL->tablesJob['thread_history'] =
				array( 'th_user_text', 'th_user', 'th_timestamp' );
		return true;
	}
	
	static function editCheckboxes( $editPage, &$checkboxes, &$tabIndex ) {
		global $wgRequest;
		$article = $editPage->getArticle();
		$title = $article->getTitle();
		
		if ( !$article->exists() && $title->getNamespace() == NS_LQT_THREAD ) {
			unset( $checkboxes['minor'] );
		}
		
		if ( $title->getNamespace() == NS_LQT_THREAD && self::$editType != 'new' ) {
			wfLoadExtensionMessages( 'LiquidThreads' );
			$label = wfMsgExt( 'lqt-edit-bump', 'parseinline' );
			$tooltip = wfMsgExt( 'lqt-edit-bump-tooltip', 'parsemag' );
			
			$checked = ! $wgRequest->wasPosted() ||
					$wgRequest->getBool( 'wpBumpThread' );
			
			$html =
				Xml::check( 'wpBumpThread', $checked, array(
						'title' => $tooltip, 'id' => 'wpBumpThread'
					) );
			
			$html .= Xml::tags( 'label', array( 'for' => 'wpBumpThread',
					'title' => $tooltip ), $label );
					
			$checkboxes['bump'] = $html;
		}
		
		return true;
	}
	
	static function customiseSearchProfiles( &$profiles ) {
		wfLoadExtensionMessages( 'LiquidThreads' );
		
		$namespaces = array( NS_LQT_THREAD, NS_LQT_SUMMARY );
		
		// Add odd namespaces
		foreach ( SearchEngine::searchableNamespaces() as $ns => $nsName ) {
			if ( $ns % 2 == 1 ) {
				$namespaces[] = $ns;
			}
		}
		
		$insert = array(
			'threads' =>
				array(
					'message' => 'searchprofile-threads',
					'tooltip' => 'searchprofile-threads-tooltip',
					'namespaces' => $namespaces,
					'namespace-messages' => SearchEngine::namespacesAsText( $namespaces ),
				),
		);
		
		$profiles = wfArrayInsertAfter( $profiles, $insert, 'help' );
		
		return true;
	}
	
	public static function onLoadExtensionSchemaUpdates() {
		global $wgExtNewTables, $wgExtNewFields, $wgExtPGNewFields,
				$wgExtPGAlteredFields, $wgExtNewIndexes, $wgDBtype;

		$dir = realpath( dirname( __FILE__ ) . '/..' );
		
		// DB updates
		$wgExtNewTables[] = array( 'thread', "$dir/lqt.sql" );
		$wgExtNewTables[] = array( 'user_message_state', "$dir/lqt.sql" );
		$wgExtNewTables[] = array( 'thread_history', "$dir/schema-changes/thread_history_table.sql" );
		
		
		$wgExtNewFields[] = array( "thread", "thread_article_namespace", "$dir/schema-changes/split-thread_article.sql" );
		$wgExtNewFields[] = array( "thread", "thread_article_title", "$dir/schema-changes/split-thread_article.sql" );
		$wgExtNewFields[] = array( "thread", "thread_ancestor", "$dir/schema-changes/normalise-ancestry.sql" );
		$wgExtNewFields[] = array( "thread", "thread_parent", "$dir/schema-changes/normalise-ancestry.sql" );
		$wgExtNewFields[] = array( "thread", "thread_modified", "$dir/schema-changes/split-timestamps.sql" );
		$wgExtNewFields[] = array( "thread", "thread_created", "$dir/schema-changes/split-timestamps.sql" );
		$wgExtNewFields[] = array( "thread", "thread_editedness", "$dir/schema-changes/store-editedness.sql" );
		$wgExtNewFields[] = array( "thread", "thread_subject", "$dir/schema-changes/store_subject-author.sql" );
		$wgExtNewFields[] = array( "thread", "thread_author_id", "$dir/schema-changes/store_subject-author.sql" );
		$wgExtNewFields[] = array( "thread", "thread_author_name", "$dir/schema-changes/store_subject-author.sql" );
		$wgExtNewFields[] = array( "thread", "thread_sortkey", "$dir/schema-changes/new-sortkey.sql" );
		$wgExtNewFields[] = array( 'thread', 'thread_replies', "$dir/schema-changes/store_reply_count.sql" );
		$wgExtNewFields[] = array( 'thread', 'thread_article_id', "$dir/schema-changes/store_article_id.sql" );
		
		$wgExtNewIndexes[] = array( 'thread', 'thread_summary_page', '(thread_summary_page)' );
		
		return true;
	}
	
	static function onArticleMoveComplete( &$form, &$ot, &$nt ) {
		// Check if it's a talk page.
		if ( !LqtDispatch::isLqtPage( $ot ) && !LqtDispatch::isLqtPage( $nt ) ) {
			return true;
		}
		
		// Synchronise the first 500 threads, in reverse order by thread id. If
		// there are more threads to synchronise, the job queue will take over.
		Threads::synchroniseArticleData( new Article( $nt ), 500, 'cascade' );
		
		return true;
	}
	
	static function onArticleMove( $ot, $nt, $user, &$err, $reason ) {
		// Synchronise article data so that moving the article doesn't break any
		//  article association.
		Threads::synchroniseArticleData( new Article( $ot ) );
		
		return true;
	}
	
	static function userIsBlockedFrom( $user, $title, &$isBlocked, &$allowUserTalk ) {
		// Limit applicability
		if ( !( $isBlocked && $allowUserTalk && $title->getNamespace() == NS_LQT_THREAD ) ) {
			return true;
		}
		
		// Now we're dealing with blocked users with user talk editing allowed editing pages
		//  in the thread namespace.

		if ( $title->exists() ) {
			// If the page actually exists, allow the user to edit posts on their own talk page.
			$thread = Threads::withRoot( new Article( $title ) );
			
			if ( !$thread )
				return true;
			
			$articleTitle = $thread->article()->getTitle();
			
			if ( $articleTitle->getNamespace() == NS_USER_TALK &&
					$user->getName() == $title->getText() ) {
				$isBlocked = false;
				return true;
			}
		} else {
			// Otherwise, it's a bit trickier. Allow creation of thread titles prefixed by the
			//  user's talk page.

			// Figure out if it's on the talk page
			$talkPage = $user->getTalkPage();
			$isOnTalkPage = ( self::$editThread &&
				self::$editThread->article()->getTitle()->equals( $talkPage ) );
			$isOnTalkPage = $isOnTalkPage || ( self::$editAppliesTo &&
				self::$editAppliesTo->article()->getTitle()->equals( $talkPage ) );
			$isOnTalkPage = $isOnTalkPage ||
				( self::$editArticle->getTitle()->equals( $talkPage ) );
			
			if ( self::$editArticle->getTitle()->equals( $title ) && $isOnTalkPage ) {
				$isBlocked = false;
				return true;
			}
		}
		
		return true;
	}
	
	static function onPersonalUrls( &$personal_urls, &$title ) {
		global $wgUser, $wgLang;
		
		if ( $wgUser->isAnon() ) return true;
		
		wfLoadExtensionMessages( 'LiquidThreads' );
		
		$dbr = wfGetDB( DB_SLAVE );
		
		$newMessagesCount = NewMessages::newMessageCount( $wgUser );
		
		// Add new messages link.
		$url = SpecialPage::getTitleFor( 'NewMessages' )->getLocalURL();
		$msg = $newMessagesCount ? 'lqt-newmessages-n' : 'lqt_newmessages';
		$newMessagesLink =
			array(
				'href' => $url,
				'text' => wfMsg( $msg, $wgLang->formatNum( $newMessagesCount ) ),
				'active' => $newMessagesCount > 0,
			);
		
		$insertUrls = array( 'newmessages' => $newMessagesLink );
		
		$personal_urls = wfArrayInsertAfter( $personal_urls, $insertUrls, 'watchlist' );
		
		return true;
	}
	
	static function onArticleSaveComplete( &$article, &$user, $text, $summary,
			$minoredit, $watchthis, $sectionanchor, &$flags, $revision,
			&$status, $baseRevId ) {
 		if ( !$status->isGood() ) {
 			// Failed
 			return true;
 		}
 		
 		$title = $article->getTitle();
 		if ( $title->getNamespace() != NS_LQT_THREAD ) {
 			// Not a thread
 			return true;
 		}
 		
 		if (!$baseRevId) {
 			// New page
 			return true;
 		}
 		
 		$thread = Threads::withRoot( $article );
 		
 		if (!$thread) {
 			// No matching thread.
 			return true;
 		}
 		
 		LqtView::postEditUpdates( 'editExisting', null, $article, $thread->article(),
 					$thread->article(), $summary, $thread, $text );
 					
 		return true;
 	}
 	
 	static function getProtectionTypes( $title, &$types ) {
 		$isLqtPage = LqtDispatch::isLqtPage( $title );
 		$isThread = $title->getNamespace() == NS_LQT_THREAD;

		// Bulk load is a no-no, causes infinite recursion.
		$thread = Threads::withRoot( new Article( $title ), false );
		
		$isThread = $isThread && $thread && $thread->isTopmostThread();
 		
 		if ( !$isLqtPage && !$isThread ) {
 			return true;
 		}
 		
 		if ( $isLqtPage ) {
 			$types[] = 'newthread';
 			$types[] = 'reply';
 		}
 		
 		if ( $isThread ) {
 			$types[] = 'reply';
 		}
 	
 		return true;
 	}
}
