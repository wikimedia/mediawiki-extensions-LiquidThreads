<?php
class LqtHooks {
	// Used to inform hooks about edits that are taking place.
	public static $editType = null;
	public static $editThread = null;
	public static $editAppliesTo = null;
	public static $editArticle = null;
	public static $editTalkpage = null;
	public static $scriptVariables = array();
	
	public static $editedStati = array(
		Threads::EDITED_NEVER => 'never',
		Threads::EDITED_HAS_REPLY => 'has-reply',
		Threads::EDITED_BY_AUTHOR => 'by-author',
		Threads::EDITED_BY_OTHERS => 'by-others'
	);
	public static $threadTypes = array(
		Threads::TYPE_NORMAL => 'normal',
		Threads::TYPE_MOVED => 'moved',
		Threads::TYPE_DELETED => 'deleted'
	);

	static function customizeOldChangesList( &$changeslist, &$s, $rc ) {
		if ( $rc->getTitle()->getNamespace() != NS_LQT_THREAD )
			return true;

		$thread = Threads::withRoot( new Article( $rc->getTitle() ) );
		if ( !$thread ) return true;

		LqtView::addJSandCSS();
		wfLoadExtensionMessages( 'LiquidThreads' );

		global $wgUser, $wgLang;
		$sk = $wgUser->getSkin();

		// Custom display for new posts.
		if ( $rc->mAttribs['rc_new'] ) {
			global $wgOut;

			// Article link, timestamp, user
			$s = '';
			$s .= $sk->link( $thread->getTitle() );
			$changeslist->insertTimestamp( $s, $rc );
			$changeslist->insertUserRelatedLinks( $s, $rc );

			// Action text
			$msg = $thread->isTopmostThread()
				? 'lqt_rc_new_discussion' : 'lqt_rc_new_reply';
			$link = LqtView::linkInContext( $thread );
			$s .= ' ' . wfMsgExt( $msg, array( 'parseinline', 'replaceafter' ), $link );

			// add the truncated post content
			$quote = $thread->root()->getContent();
			$quote = $wgLang->truncate( $quote, 200 );
			$s .= ' ' . $sk->commentBlock( $quote );

			$classes = array();
			$changeslist->insertTags( $s, $rc, $classes );
			$changeslist->insertExtra( $s, $rc, $classes );
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
							$newmsg_t->getPrefixedText() );
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

		if ( $wgEnableEmail ) {
			$preferences['lqtnotifytalk'] =
				array(
					'type' => 'toggle',
					'label-message' => 'lqt-preference-notify-talk',
					'section' => 'personal/email'
				);
		}

		$preferences['lqt-watch-threads'] = array(
			'type' => 'toggle',
			'label-message' => 'lqt-preference-watch-threads',
			'section' => 'watchlist/advancedwatchlist',
		);

		// Display depth and count
		$preferences['lqtdisplaydepth'] = array(
			'type' => 'int',
			'label-message' => 'lqt-preference-display-depth',
			'section' => 'lqt',
		);

		$preferences['lqtdisplaycount'] = array(
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
		// Is it a thread
		if ( empty( $row->thread_id ) ) {
			return true;
		}
		
		$thread = Thread::newFromRow( $row );
		$threadInfo = "\n";
		$attribs = array();
		$attribs['ThreadSubject'] = $thread->subject();
		if ( $thread->hasSuperThread() ) {
			$attribs['ThreadParent'] = $thread->superThread()->title()->getPrefixedText();
			$attribs['ThreadAncestor'] = $thread->topmostThread()->title()->getPrefixedText();
		}
		$attribs['ThreadPage'] = $thread->getTitle()->getPrefixedText();
		$attribs['ThreadID'] = $thread->id();
		if ( $thread->hasSummary() && $thread->summary() ) {
			$attribs['ThreadSummaryPage'] = $thread->summary()->getTitle()->getPrefixedText();
		}
		$attribs['ThreadAuthor'] = $thread->author()->getName();
		$attribs['ThreadEditStatus'] = self::$editedStati[$thread->editedness()];
		$attribs['ThreadType'] = self::$threadTypes[$thread->type()];
		$attribs['ThreadSignature'] = $thread->signature();

		foreach ( $attribs as $key => $value ) {
			$threadInfo .= "\t" . Xml::element( $key, null, $value ) . "\n";
		}

		$out .= Xml::tags( 'DiscussionThreading', null, $threadInfo ) . "\n";

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
			unset( $checkboxes['watch'] );
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
			'threads' => array(
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
		$wgExtNewTables[] = array( 'thread_pending_relationship', "$dir/schema-changes/thread_pending_relationship.sql" );
		$wgExtNewTables[] = array( 'thread_reaction', "$dir/schema-changes/thread_reactions.sql" );

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
		$wgExtNewFields[] = array( 'thread', 'thread_signature', "$dir/schema-changes/thread_signature.sql" );

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

			$articleTitle = $thread->getTitle();

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
				self::$editThread->getTitle()->equals( $talkPage ) );
			$isOnTalkPage = $isOnTalkPage || ( self::$editAppliesTo &&
				self::$editAppliesTo->getTitle()->equals( $talkPage ) );
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
		$newMessagesLink = array(
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

 		if ( !$baseRevId ) {
 			// New page
 			return true;
 		}

 		$thread = Threads::withRoot( $article );

 		if ( !$thread ) {
 			// No matching thread.
 			return true;
 		}

 		LqtView::postEditUpdates(
 			'editExisting',
 			null,
 			$article,
 			$thread->article(),
 			$thread->article(),
 			$summary,
 			$thread,
 			$text
 		);

 		return true;
 	}

 	static function getProtectionTypes( $title, &$types ) {
 		$isLqtPage = LqtDispatch::isLqtPage( $title );
 		$isThread = $title->getNamespace() == NS_LQT_THREAD;

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

	static function setupUserMessageArticle( $user, &$article, $subject, $text, $signature, $summary, $editor ) {
		global $wgLqtTalkPages;

		if ( $wgLqtTalkPages && LqtDispatch::isLqtPage( $article->getTitle() ) ) {
			$threadTitle = Threads::newThreadTitle( $subject, $article );

			if ( !$threadTitle ) {
				wfDebug( __METHOD__ . ": invalid title $threadTitle\n" );
				return true;
			}

			$article = new Article( $threadTitle );
			return false;
		}
		return true;
	}

	/**
	 * Take care of formatting a user message.  We don't really need
	 * to do anything, we just need to stop others from doing stuff.
	 */
	static function formatUserMessage( $subject, &$text, $signature ) {
		return false;
	}

	static function afterUserMessage( $user, $article, $subject, $text, $signature, $summary, $editor ) {
		global $wgLqtTalkPages;
		$talk = $user->getTalkPage();

		if ( $wgLqtTalkPages && LqtDispatch::isLqtPage( $talk ) ) {
			// Need to edit as another user. Lqt does not provide an interface to alternative users,
			// so replacing $wgUser here.
			global $wgUser;
			$parkedUser = $wgUser;
			$wgUser = $editor;

			$title = preg_replace( "{/[^/]+}", "", $article->getTitle()->getBaseText() );
			$baseArticle = new Article( Title::newFromText( $title ) );
			$threadTitle = preg_replace( "{.*/([^/]+)}", '$1', $article->getTitle()->getBaseText() );

			LqtView::newPostMetadataUpdates(
				array(
					'talkpage' => $baseArticle,
					'text' => $text,
					'summary' => $summary,
					'root' => $article,
					'subject' => $threadTitle,
					'signature' => $signature,
				)
			);

			// Set $wgUser back to the newly created user
			$wgUser = $parkedUser;
			return false;
		}
		return true;
	}

	public static function onMakeGlobalVariablesScript( &$vars ) {
		$vars += self::$scriptVariables;
		return true;
	}

	/**
	 * Returns the text contents of a template page set in given key contents
	 * Returns empty string if no text could be retrieved.
	 * @param $key String: message key that should contain a template page name
	 */
	private static function getTextForPageInKey( $key ) {
		wfLoadExtensionMessages( 'LiquidThreads' );

		$templateTitleText = wfMsgForContent( $key );
		$templateTitle = Title::newFromText( $templateTitleText );

		// Do not continue if there is no valid subject title
		if ( !$templateTitle ) {
			wfDebug( __METHOD__ . ": invalid title in " . $key . "\n" );
			return '';
		}

		// Get the subject text from the page
		if ( $templateTitle->getNamespace() == NS_TEMPLATE ) {
			return $templateTitle->getText();
		} else {
			// There is no subject text
			wfDebug( __METHOD__ . ": " . $templateTitleText . " must be in NS_TEMPLATE\n" );
			return '';
		}
	}
	
	/**
	 * Handles tags in Page sections of XML dumps
	 */

	public static function handlePageXMLTag( $reader, &$pageInfo ) {
		if ( !( $reader->nodeType == XmlReader::ELEMENT &&
				$reader->name == 'DiscussionThreading' ) ) {
			return true;
		}
		
		$pageInfo['DiscussionThreading'] = array();
		$fields = array(
				'ThreadSubject',
				'ThreadParent',
				'ThreadAncestor',
				'ThreadPage',
				'ThreadID',
				'ThreadSummaryPage',
				'ThreadAuthor',
				'ThreadEditStatus',
				'ThreadType',
				'ThreadSignature',
			);
		
		$skip = false;
		
		while ( $skip ? $reader->next() : $reader->read() ) {
			if ( $reader->nodeType == XmlReader::END_ELEMENT &&
					$reader->name == 'DiscussionThreading') {
				break;
			}
			
			$tag = $reader->name;
			
			if ( in_array( $tag, $fields ) ) {
				$pageInfo['DiscussionThreading'][$tag] = $reader->nodeContents();
			}
		}
		
		return false;
	}
	
	// Processes discussion threading data in XML dumps (extracted in handlePageXMLTag).
	public static function afterImportPage( $title, $origTitle, $revCount, $sRevCount, $pageInfo ) {
		// in-process cache of pending thread relationships
		static $pendingRelationships = null;
		
		if ( $pendingRelationships === null ) {
			$pendingRelationships = self::loadPendingRelationships();
		}
		
		$titlePendingRelationships = array();
		if ( isset($pendingRelationships[$title->getPrefixedText()]) ) {
			$titlePendingRelationships = $pendingRelationships[$title->getPrefixedText()];
			
			foreach( $titlePendingRelationships as $k => $v ) {
				if ( $v['type'] == 'article' ) {
					self::applyPendingArticleRelationship( $v, $title );
					unset( $titlePendingRelationships[$k] );
				}
			}
		}
	
 		if ( ! isset( $pageInfo['DiscussionThreading'] ) ) {
 			return true;
 		}
		
 		$statusValues = array_flip( self::$editedStati );
 		$typeValues = array_flip( self::$threadTypes );
		
		$info = $pageInfo['DiscussionThreading'];
		
		$root = new Article( $title );
		$article = new Article( Title::newFromText( $info['ThreadPage'] ) );
		$type = $typeValues[$info['ThreadType']];
		$editedness = $statusValues[$info['ThreadEditStatus']];
		$subject = $info['ThreadSubject'];
		$summary = wfMsgForContent( 'lqt-imported' );
		
		$signature = null;
		if ( isset( $info['ThreadSignature'] ) ) {
			$signature = $info['ThreadSignature'];
		}
		
		$thread = Thread::create( $root, $article, null, $type,
						$subject, $summary, null, $signature );
	
		if ( isset( $info['ThreadSummaryPage'] ) ) {
			$summaryPageName = $info['ThreadSummaryPage'];
			$summaryPage = new Article( Title::newFromText( $summaryPageName ) );
			if ( $summaryPage->exists() ) {
				$thread->setSummaryPage( $summaryPage );
			} else {
				self::addPendingRelationship( $thread->id(), 'thread_summary_page',
						$summaryPageName, 'article', $pendingRelationships );
			}
		}
		
		if ( isset( $info['ThreadParent'] ) ) {
			$threadPageName = $info['ThreadParent'];
			$parentArticle = new Article( Title::newFromText( $threadPageName ) );
			$superthread = Threads::withRoot( $parentArticle );
			
			if ( $superthread ) {
				$thread->setSuperthread( $superthread );
			} else {
				self::addPendingRelationship( $thread->id(), 'thread_parent',
								$threadPageName, 'thread', $pendingRelationships );
			}
		}
		
		$thread->save();
		
		foreach( $titlePendingRelationships as $k => $v ) {
			if ( $v['type'] == 'thread' ) {
				self::applyPendingThreadRelationship( $pendingRelationship, $thread );
				unset( $titlePendingRelationships[$k] );
			}
		}
		
		return true;
	}
	
	public static function applyPendingThreadRelationship( $pendingRelationship, $thread ) {
		if ( $pendingRelationship['relationship'] == 'thread_parent' ) {
			$childThread = Threads::withID( $pendingRelationship['thread'] );
			
			$childThread->setSuperthread( $thread );
			$childThread->save();
			$thread->save();
		}
	}
	
	public static function applyPendingArticleRelationship( $pendingRelationship, $title ) {
		$articleID = $title->getArticleId();
		
		$dbw = wfGetDB( DB_MASTER );
		
		$dbw->update( 'thread', array( $pendingRelationship['relationship'] => $articleID ),
				array( 'thread_id' => $pendingRelationship['thread'] ),
				__METHOD__ );
		
		$dbw->delete( 'thread_pending_relationship',
				array( 'tpr_title' => $pendingRelationship['title'] ), __METHOD__ );
	}
	
	public static function loadPendingRelationships() {
		$dbr = wfGetDB( DB_MASTER );
		$arr = array();
		
		$res = $dbr->select( 'thread_pending_relationship', '*', array(1), __METHOD__ );
		
		foreach( $res as $row ) {
			$entry = array(
				'thread' => $row->tpr_thread,
				'relationship' => $row->tpr_relationship,
				'title' => $row->tpr_title,
				'type' => $row->tpr_type,
			);
			
			if ( !isset($arr[$title]) ) {
				$arr[$title] = array();
			}
			
			$arr[$title][] = $entry;
		}
		
		return $arr;
	}
	
	public static function addPendingRelationship( $thread, $relationship, $title, $type, &$array ) {
		$entry = array(
			'thread' => $thread,
			'relationship' => $relationship,
			'title' => $title,
			'type' => $type,
		);
		
		$row = array();
		foreach( $entry as $k => $v ) {
			$row['tpr_'.$k] = $v;
		}
		
		$dbw = wfGetDB( DB_MASTER );
		$dbw->insert( 'thread_pending_relationship', $row, __METHOD__ );
		
		if ( !isset( $array[$title] ) ) {
			$array[$title] = array();
		}
		
		$array[$title][] = $entry;
	}
}
