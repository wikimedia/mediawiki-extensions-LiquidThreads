<?php

use MediaWiki\MediaWikiServices;
use UtfNormal\Validator;

class LqtHooks {
	// Used to inform hooks about edits that are taking place.
	public static $editType = null;
	public static $editThread = null;
	public static $editAppliesTo = null;

	/**
	 * @var Article
	 */
	public static $editArticle = null;
	public static $editTalkpage = null;

	public static $editedStati = [
		Threads::EDITED_NEVER => 'never',
		Threads::EDITED_HAS_REPLY => 'has-reply',
		Threads::EDITED_BY_AUTHOR => 'by-author',
		Threads::EDITED_BY_OTHERS => 'by-others'
	];
	public static $threadTypes = [
		Threads::TYPE_NORMAL => 'normal',
		Threads::TYPE_MOVED => 'moved',
		Threads::TYPE_DELETED => 'deleted'
	];

	/**
	 * @param ChangesList $changeslist
	 * @param string &$s
	 * @param RecentChange $rc
	 * @param array &$classes
	 * @return bool
	 */
	public static function customizeOldChangesList( ChangesList $changeslist, &$s, $rc, &$classes ) {
		if ( $rc->getTitle()->getNamespace() != NS_LQT_THREAD ) {
			return true;
		}

		$thread = Threads::withRoot( new Article( $rc->getTitle(), 0 ) );
		if ( !$thread ) {
			return true;
		}

		global $wgLang, $wgOut;
		$wgOut->addModules( 'ext.liquidThreads' );

		// Custom display for new posts.
		if ( $rc->getAttribute( 'rc_new' ) ) {
			// Article link, timestamp, user
			$s = MediaWikiServices::getInstance()->getLinkRenderer()->makeLink(
				$thread->getTitle()
			);
			$changeslist->insertTimestamp( $s, $rc );
			$changeslist->insertUserRelatedLinks( $s, $rc );

			// Action text
			$msg = $thread->isTopmostThread()
				? 'lqt_rc_new_discussion' : 'lqt_rc_new_reply';
			$link = LqtView::linkInContext( $thread );
			$s .= ' ' . wfMessage( $msg )->rawParams( $link )->parse();

			$s .= $wgLang->getDirMark();

			// add the truncated post content
			$quote = ContentHandler::getContentText( $thread->root()->getPage()->getContent() );
			$quote = $wgLang->truncateForVisual( $quote, 200 );
			$s .= ' ' . Linker::commentBlock( $quote );

			$classes = [];
			$changeslist->insertTags( $s, $rc, $classes );
			$changeslist->insertExtra( $s, $rc, $classes );
		}

		return true;
	}

	public static function setNewtalkHTML( $skintemplate, $tpl ) {
		global $wgUser, $wgOut;

		// If the user isn't using LQT on their talk page, bail out
		if ( !LqtDispatch::isLqtPage( $wgUser->getTalkPage() ) ) {
			return true;
		}

		$pageTitle = $skintemplate->getTitle();
		$newmsg_t = SpecialPage::getTitleFor( 'NewMessages' );
		$watchlist_t = SpecialPage::getTitleFor( 'Watchlist' );
		$usertalk_t = $wgUser->getTalkPage();
		if ( $wgUser->getNewtalk()
				&& !$newmsg_t->equals( $pageTitle )
				&& !$watchlist_t->equals( $pageTitle )
				&& !$usertalk_t->equals( $pageTitle )
				) {
			$s = wfMessage( 'lqt_youhavenewmessages', $newmsg_t->getPrefixedText() )->parse();
			$tpl->set( "newtalk", $s );
			$wgOut->setCdnMaxage( 0 );
		} else {
			$tpl->set( "newtalk", '' );
		}

		return true;
	}

	public static function beforeWatchlist(
		$name, &$tables, &$fields, &$conds, &$query_options, &$join_conds, $opts
	) {
		global $wgLiquidThreadsEnableNewMessages, $wgOut, $wgUser;

		if ( !$wgLiquidThreadsEnableNewMessages ) {
			return true;
		}

		if ( $name !== 'Watchlist' ) {
			return true;
		}

		$db = wfGetDB( DB_REPLICA );

		if ( !in_array( 'page', $tables ) ) {
			$tables[] = 'page';
			// Yes, this is the correct field to join to. Weird naming.
			$join_conds['page'] = [ 'LEFT JOIN', 'rc_cur_id=page_id' ];
		}
		$conds[] = "page_namespace IS NULL OR page_namespace != " . $db->addQuotes( NS_LQT_THREAD );

		$talkpage_messages = NewMessages::newUserMessages( $wgUser );
		$tn = count( $talkpage_messages );

		$watch_messages = NewMessages::watchedThreadsForUser( $wgUser );
		$wn = count( $watch_messages );

		if ( $tn == 0 && $wn == 0 ) {
			return true;
		}

		$wgOut->addModules( 'ext.liquidThreads' );
		$messages_title = SpecialPage::getTitleFor( 'NewMessages' );
		$new_messages = wfMessage( 'lqt-new-messages' )->parse();

		$link = MediaWikiServices::getInstance()->getLinkRenderer()->makeLink(
			$messages_title,
			new HtmlArmor( $new_messages ),
			[ 'class' => 'lqt_watchlist_messages_notice' ] );
		$wgOut->addHTML( $link );

		return true;
	}

	public static function getPreferences( $user, &$preferences ) {
		global $wgEnableEmail, $wgLqtTalkPages, $wgLiquidThreadsEnableNewMessages, $wgHiddenPrefs;

		if ( $wgEnableEmail ) {
			$preferences['lqtnotifytalk'] =
				[
					'type' => 'toggle',
					'label-message' => 'lqt-preference-notify-talk',
					'section' => 'personal/email'
				];
		}

		$preferences['lqt-watch-threads'] = [
			'type' => 'toggle',
			'label-message' => 'lqt-preference-watch-threads',
			'section' => 'watchlist/advancedwatchlist',
		];

		// Display depth and count
		$preferences['lqtdisplaydepth'] = [
			'type' => 'int',
			'label-message' => 'lqt-preference-display-depth',
			'section' => 'lqt',
			'min' => 1,
		];

		$preferences['lqtdisplaycount'] = [
			'type' => 'int',
			'label-message' => 'lqt-preference-display-count',
			'section' => 'lqt',
			'min' => 1,
		];

		// Don't show any preferences if the wiki's LQT status is frozen
		if ( !( $wgLqtTalkPages || $wgLiquidThreadsEnableNewMessages ) ) {
			$wgHiddenPrefs[] = 'lqtnotifytalk';
			$wgHiddenPrefs[] = 'lqt-watch-threads';
			$wgHiddenPrefs[] = 'lqtdisplaydepth';
			$wgHiddenPrefs[] = 'lqtdisplaycount';
		}

		return true;
	}

	/**
	 * @param WikiPage $wikiPage
	 *
	 * @return bool
	 */
	public static function updateNewtalkOnEdit( WikiPage $wikiPage ) {
		$title = $wikiPage->getTitle();

		// They're only editing the header, don't update newtalk.
		return !LqtDispatch::isLqtPage( $title );
	}

	public static function dumpThreadData( $writer, &$out, $row, $title ) {
		// Is it a thread
		if ( empty( $row->thread_id ) || $row->thread_type >= 2 ) {
			return true;
		}

		$thread = Thread::newFromRow( $row );
		$threadInfo = "\n";
		$attribs = [];
		$attribs['ThreadSubject'] = $thread->subject();

		if ( $thread->hasSuperThread() ) {
			if ( $thread->superThread()->title() ) {
				$attribs['ThreadParent'] = $thread->superThread()->title()->getPrefixedText();
			}

			if ( $thread->topmostThread()->title() ) {
				$attribs['ThreadAncestor'] = $thread->topmostThread()->title()->getPrefixedText();
			}
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

		$out .= Validator::cleanUp( Xml::tags( 'DiscussionThreading', null, $threadInfo ) . "\n" );

		return true;
	}

	public static function modifyExportQuery( $db, &$tables, &$cond, &$opts, &$join ) {
		$tables[] = 'thread';

		$join['thread'] = [ 'left join', [ 'thread_root=page_id' ] ];

		return true;
	}

	public static function modifyOAIQuery( &$tables, &$fields, &$conds,
		&$options, &$join_conds
	) {
		$tables[] = 'thread';

		$join_conds['thread'] = [ 'left join', [ 'thread_root=page_id' ] ];

		$db = wfGetDB( DB_REPLICA );
		$fields[] = $db->tableName( 'thread' ) . '.*';

		return true;
	}

	public static function customiseSearchResultTitle( &$title, &$text, $result, $terms, $page ) {
		if ( $title->getNamespace() != NS_LQT_THREAD ) {
			return true;
		}

		$thread = Threads::withRoot( new Article( $title, 0 ) );

		if ( $thread ) {
			$text = $thread->subject();

			$title = clone $thread->topmostThread()->title();
			$title->setFragment( '#' . $thread->getAnchorName() );
		}

		return true;
	}

	/**
	 * For integration with the Renameuser extension.
	 *
	 * @param RenameuserSQL $renameUserSQL
	 * @return bool
	 */
	public static function onUserRename( $renameUserSQL ) {
		// Always use the job queue, talk page edits will take forever
		foreach ( self::$userTables as $table => $fields ) {
			$renameUserSQL->tablesJob[$table] = $fields;
		}
		return true;
	}

	private static $userTables = [
		'thread' => [ 'thread_author_name', 'thread_author_id', 'thread_modified' ],
		'thread_history' => [ 'th_user_text', 'th_user', 'th_timestamp' ]
	];

	/**
	 * For integration with the UserMerge extension.
	 *
	 * @param array &$updateFields
	 * @return bool
	 */
	public static function onUserMergeAccountFields( &$updateFields ) {
		// array( tableName, idField, textField )
		foreach ( self::$userTables as $table => $fields ) {
			$updateFields[] = [ $table, $fields[1], $fields[0] ];
		}
		return true;
	}

	/**
	 * Handle EditPageGetCheckboxesDefinition hook
	 *
	 * @param EditPage $editPage
	 * @param array &$checkboxes
	 * @return bool
	 */
	public static function editCheckboxes( $editPage, &$checkboxes ) {
		global $wgRequest, $wgLiquidThreadsShowBumpCheckbox;

		$article = $editPage->getArticle();
		$title = $article->getTitle();

		if ( !$article->exists() && $title->getNamespace() == NS_LQT_THREAD ) {
			unset( $checkboxes['minor'] );
			unset( $checkboxes['watch'] );
		}

		if ( $title->getNamespace() == NS_LQT_THREAD && self::$editType != 'new' &&
			$wgLiquidThreadsShowBumpCheckbox
		) {
			$checkboxes['wpBumpThread'] = [
				'id' => 'wpBumpThread',
				'label-message' => 'lqt-edit-bump',
				'title-message' => 'lqt-edit-bump-tooltip',
				'legacy-name' => 'bump',
				'default' => !$wgRequest->wasPosted() || $wgRequest->getBool( 'wpBumpThread' ),
			];
		}

		return true;
	}

	public static function customiseSearchProfiles( &$profiles ) {
		$namespaces = [ NS_LQT_THREAD, NS_LQT_SUMMARY ];

		// Add odd namespaces
		$searchableNS = MediaWikiServices::getInstance()->getSearchEngineConfig()
			->searchableNamespaces();
		foreach ( $searchableNS as $ns => $nsName ) {
			if ( $ns % 2 == 1 ) {
				$namespaces[] = $ns;
			}
		}

		$insert = [
			'threads' => [
				'message' => 'searchprofile-threads',
				'tooltip' => 'searchprofile-threads-tooltip',
				'namespaces' => $namespaces,
				'namespace-messages' => MediaWikiServices::getInstance()->getSearchEngineConfig()
					->namespacesAsText( $namespaces ),
			],
		];

		// Insert translations before 'all'
		$index = array_search( 'all', array_keys( $profiles ) );

		// Or just at the end if all is not found
		if ( $index === false ) {
			wfWarn( '"all" not found in search profiles' );
			$index = count( $profiles );
		}

		$profiles = array_merge(
			array_slice( $profiles, 0, $index ),
			$insert,
			array_slice( $profiles, $index )
		);

		return true;
	}

	/**
	 * @param DatabaseUpdater|null $updater
	 * @return bool
	 */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater = null ) {
		$dir = realpath( __DIR__ . '/..' );

		if ( $updater instanceof PostgresUpdater ) {
			$updater->addExtensionTable( 'thread', "$dir/lqt.pg.sql" );
			$updater->addExtensionTable( 'thread_history',
				"$dir/schema-changes/thread_history_table.pg.sql" );
			$updater->addExtensionTable( 'thread_pending_relationship',
				"$dir/schema-changes/thread_pending_relationship.pg.sql" );
			$updater->addExtensionTable( 'thread_reaction',
				"$dir/schema-changes/thread_reactions.pg.sql" );
			$updater->addExtensionField( 'user_message_state', 'ums_conversation',
				"$dir/schema-changes/ums_conversation.pg.sql" );
		} else {
			$updater->addExtensionTable( 'thread', "$dir/lqt.sql" );
			$updater->addExtensionTable( 'thread_history',
				"$dir/schema-changes/thread_history_table.sql" );
			$updater->addExtensionTable( 'thread_pending_relationship',
				"$dir/schema-changes/thread_pending_relationship.sql" );
			$updater->addExtensionTable( 'thread_reaction',
				"$dir/schema-changes/thread_reactions.sql" );
			$updater->addExtensionField( 'user_message_state', 'ums_conversation',
				"$dir/schema-changes/ums_conversation.sql" );
		}

		$updater->addExtensionField( 'thread', "thread_article_namespace",
			"$dir/schema-changes/split-thread_article.sql" );
		$updater->addExtensionField( 'thread', "thread_article_title",
			"$dir/schema-changes/split-thread_article.sql" );
		$updater->addExtensionField( 'thread', "thread_ancestor",
			"$dir/schema-changes/normalise-ancestry.sql" );
		$updater->addExtensionField( 'thread', "thread_parent",
			"$dir/schema-changes/normalise-ancestry.sql" );
		$updater->addExtensionField( 'thread', "thread_modified",
			"$dir/schema-changes/split-timestamps.sql" );
		$updater->addExtensionField( 'thread', "thread_created",
			"$dir/schema-changes/split-timestamps.sql" );
		$updater->addExtensionField( 'thread', "thread_editedness",
			"$dir/schema-changes/store-editedness.sql" );
		$updater->addExtensionField( 'thread', "thread_subject",
			"$dir/schema-changes/store_subject-author.sql" );
		$updater->addExtensionField( 'thread', "thread_author_id",
			"$dir/schema-changes/store_subject-author.sql" );
		$updater->addExtensionField( 'thread', "thread_author_name",
			"$dir/schema-changes/store_subject-author.sql" );
		$updater->addExtensionField( 'thread', "thread_sortkey",
			"$dir/schema-changes/new-sortkey.sql" );
		$updater->addExtensionField( 'thread', 'thread_replies',
			"$dir/schema-changes/store_reply_count.sql" );
		$updater->addExtensionField( 'thread', 'thread_article_id',
			"$dir/schema-changes/store_article_id.sql" );
		$updater->addExtensionField( 'thread', 'thread_signature',
			"$dir/schema-changes/thread_signature.sql" );

		$updater->addExtensionIndex( 'thread', 'thread_summary_page',
			"$dir/schema-changes/index-summary_page.sql" );
		$updater->addExtensionIndex( 'thread', 'thread_parent',
			"$dir/schema-changes/index-thread_parent.sql" );

		$updater->dropExtensionIndex(
			'thread',
			'thread_root_2',
			"$dir/schema-changes/thread-drop-thread_root_2.sql"
		);

		return true;
	}

	public static function onTitleMoveComplete(
		Title $ot, Title $nt, User $user, $oldid, $newid, $reason = null
	) {
		// Check if it's a talk page.
		if ( !LqtDispatch::isLqtPage( $ot ) && !LqtDispatch::isLqtPage( $nt ) ) {
			return true;
		}

		// Synchronise the first 500 threads, in reverse order by thread id. If
		// there are more threads to synchronise, the job queue will take over.
		Threads::synchroniseArticleData( new Article( $nt, 0 ), 500, 'cascade' );

		return true;
	}

	public static function onMovePageIsValidMove( Title $oldTitle ) {
		// Synchronise article data so that moving the article doesn't break any
		// article association.
		Threads::synchroniseArticleData( new Article( $oldTitle, 0 ) );

		return true;
	}

	public static function onArticleMove( $ot, $nt, $user, &$err, $reason ) {
		return self::onMovePageIsValidMove( $ot );
	}

	/**
	 * @param User $user
	 * @param Title $title
	 * @param bool &$isBlocked
	 * @param bool &$allowUserTalk
	 * @return bool
	 */
	public static function userIsBlockedFrom( $user, $title, &$isBlocked, &$allowUserTalk ) {
		// Limit applicability
		if ( !( $isBlocked && $allowUserTalk && $title->getNamespace() == NS_LQT_THREAD ) ) {
			return true;
		}

		// Now we're dealing with blocked users with user talk editing allowed editing pages
		// in the thread namespace.

		if ( $title->exists() ) {
			// If the page actually exists, allow the user to edit posts on their own talk page.
			$thread = Threads::withRoot( new Article( $title, 0 ) );

			if ( !$thread ) {
				return true;
			}

			$articleTitle = $thread->getTitle();

			if ( $articleTitle->getNamespace() == NS_USER_TALK &&
					$user->getName() == $title->getText() ) {
				$isBlocked = false;
				return true;
			}
		} else {
			// Otherwise, it's a bit trickier. Allow creation of thread titles prefixed by the
			// user's talk page.

			// Figure out if it's on the talk page
			$talkPage = $user->getTalkPage();
			$isOnTalkPage = ( self::$editThread &&
				self::$editThread->getTitle()->equals( $talkPage ) );
			$isOnTalkPage = $isOnTalkPage || ( self::$editAppliesTo &&
				self::$editAppliesTo->getTitle()->equals( $talkPage ) );

			# FIXME: self::$editArticle is sometimes not set;
			# is that ok and if not why is it happening?
			if ( self::$editArticle instanceof Article ) {
				$isOnTalkPage = $isOnTalkPage ||
					( self::$editArticle->getTitle()->equals( $talkPage ) );
			}

			if ( self::$editArticle instanceof Article
				&& self::$editArticle->getTitle()->equals( $title )
				&& $isOnTalkPage
			) {
				$isBlocked = false;
				return true;
			}
		}

		return true;
	}

	public static function onPersonalUrls( &$personal_urls, &$title ) {
		global $wgUser;

		if ( $wgUser->isAnon() ) {
			return true;
		}

		global $wgLiquidThreadsEnableNewMessages;

		if ( $wgLiquidThreadsEnableNewMessages ) {
			$newMessagesCount = NewMessages::newMessageCount( $wgUser );

			// Add new messages link.
			$url = SpecialPage::getTitleFor( 'NewMessages' )->getLocalURL();
			$msg = 'lqt-newmessages-n';
			$newMessagesLink = [
				'href' => $url,
				'text' => wfMessage( $msg )->numParams( $newMessagesCount )->text(),
				'active' => $newMessagesCount > 0,
			];

			$insertUrls = [ 'newmessages' => $newMessagesLink ];

			// User has viewmywatchlist permission
			if ( isset( $personal_urls['watchlist'] ) ) {
				$personal_urls = wfArrayInsertAfter( $personal_urls, $insertUrls, 'watchlist' );
			} else {
				$personal_urls = wfArrayInsertAfter( $personal_urls, $insertUrls, 'preferences' );
			}
		}

		return true;
	}

	/**
	 * @param WikiPage $wikiPage
	 * @param User $user
	 * @param Content $content
	 * @param string $summary
	 * @param bool $minoredit
	 * @param bool $watchthis
	 * @param string $sectionanchor
	 * @param int &$flags
	 * @param Revision $revision
	 * @param Status $status
	 * @param int $baseRevId
	 *
	 * @return bool
	 */
	public static function onPageContentSaveComplete(
		WikiPage $wikiPage, $user, $content, $summary,
		$minoredit, $watchthis, $sectionanchor, &$flags, $revision,
		$status, $baseRevId
	) {
		if ( !$status->isGood() ) {
			// Failed
			return true;
		}

		$title = $wikiPage->getTitle();
		if ( $title->getNamespace() != NS_LQT_THREAD ) {
			// Not a thread
			return true;
		}

		if ( !$baseRevId ) {
			// New page
			return true;
		}

		$thread = Threads::withRoot( $wikiPage );

		if ( !$thread ) {
			// No matching thread.
			return true;
		}

		LqtView::editMetadataUpdates(
			[
			'root' => $wikiPage,
			'thread' => $thread,
			'summary' => $summary,
			'text' => ContentHandler::getContentText( $content ),
		] );

		return true;
	}

	/**
	 * @param Title $title
	 * @param array &$types
	 * @return bool
	 */
	public static function getProtectionTypes( $title, &$types ) {
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

	/**
	 * Returns the text contents of a template page set in given key contents
	 * Returns empty string if no text could be retrieved.
	 * @param string $key message key that should contain a template page name
	 * @return String
	 */
	private static function getTextForPageInKey( $key ) {
		$templateTitleText = wfMessage( $key )->inContentLanguage()->text();
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
	 * @param XMLReader $reader
	 * @param array &$pageInfo
	 * @return bool
	 */
	public static function handlePageXMLTag( $reader, &$pageInfo ) {
		if ( !isset( $reader->nodeType ) || !( $reader->nodeType == XmlReader::ELEMENT &&
				$reader->name == 'DiscussionThreading' ) ) {
			return true;
		}

		$pageInfo['DiscussionThreading'] = [];
		$fields = [
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
		];

		$skip = false;

		while ( $skip ? $reader->next() : $reader->read() ) {
			if ( $reader->nodeType == XmlReader::END_ELEMENT &&
				$reader->name == 'DiscussionThreading'
			) {
				break;
			}

			$tag = $reader->name;

			if ( in_array( $tag, $fields ) ) {
				// @FIXME nodeContents does not exists here, only as BaseDump::nodeContents
				// @phan-suppress-next-line PhanUndeclaredMethod
				$pageInfo['DiscussionThreading'][$tag] = $reader->nodeContents();
			}
		}

		return false;
	}

	/**
	 * Processes discussion threading data in XML dumps (extracted in handlePageXMLTag).
	 *
	 * @param Title $title
	 * @param Title $origTitle
	 * @param int $revCount
	 * @param int $sRevCount
	 * @param array $pageInfo
	 * @return bool
	 */
	public static function afterImportPage( $title, $origTitle, $revCount, $sRevCount, $pageInfo ) {
		// in-process cache of pending thread relationships
		static $pendingRelationships = null;

		if ( $pendingRelationships === null ) {
			$pendingRelationships = self::loadPendingRelationships();
		}

		$titlePendingRelationships = [];
		if ( isset( $pendingRelationships[$title->getPrefixedText()] ) ) {
			$titlePendingRelationships = $pendingRelationships[$title->getPrefixedText()];

			foreach ( $titlePendingRelationships as $k => $v ) {
				if ( $v['type'] == 'article' ) {
					self::applyPendingArticleRelationship( $v, $title );
					unset( $titlePendingRelationships[$k] );
				}
			}
		}

		if ( !isset( $pageInfo['DiscussionThreading'] ) ) {
			return true;
		}

		$statusValues = array_flip( self::$editedStati );
		$typeValues = array_flip( self::$threadTypes );

		$info = $pageInfo['DiscussionThreading'];

		$root = new Article( $title, 0 );
		$article = new Article( Title::newFromText( $info['ThreadPage'] ), 0 );
		$type = $typeValues[$info['ThreadType']];
		$subject = $info['ThreadSubject'];
		$summary = wfMessage( 'lqt-imported' )->inContentLanguage()->text();

		$signature = null;
		if ( isset( $info['ThreadSignature'] ) ) {
			$signature = $info['ThreadSignature'];
		}

		$thread = Thread::create( $root, $article, null, $type,
			$subject, $summary, null, $signature );

		if ( isset( $info['ThreadSummaryPage'] ) ) {
			$summaryPageName = $info['ThreadSummaryPage'];
			$summaryPage = new Article( Title::newFromText( $summaryPageName ), 0 );

			if ( $summaryPage->exists() ) {
				$thread->setSummary( $summaryPage );
			} else {
				self::addPendingRelationship( $thread->id(), 'thread_summary_page',
					$summaryPageName, 'article', $pendingRelationships );
			}
		}

		if ( isset( $info['ThreadParent'] ) ) {
			$threadPageName = $info['ThreadParent'];
			$parentArticle = new Article( Title::newFromText( $threadPageName ), 0 );
			$superthread = Threads::withRoot( $parentArticle );

			if ( $superthread ) {
				$thread->setSuperthread( $superthread );
			} else {
				self::addPendingRelationship( $thread->id(), 'thread_parent',
					$threadPageName, 'thread', $pendingRelationships );
			}
		}

		$thread->save();

		foreach ( $titlePendingRelationships as $k => $v ) {
			if ( $v['type'] == 'thread' ) {
				self::applyPendingThreadRelationship( $v, $thread );
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

	/**
	 * @param array $pendingRelationship
	 * @param Title $title
	 */
	public static function applyPendingArticleRelationship( $pendingRelationship, $title ) {
		$articleID = $title->getArticleID();

		$dbw = wfGetDB( DB_MASTER );

		$dbw->update( 'thread', [ $pendingRelationship['relationship'] => $articleID ],
			[ 'thread_id' => $pendingRelationship['thread'] ],
			__METHOD__ );

		$dbw->delete( 'thread_pending_relationship',
			[ 'tpr_title' => $pendingRelationship['title'] ], __METHOD__ );
	}

	/**
	 * @return array
	 */
	public static function loadPendingRelationships() {
		$dbr = wfGetDB( DB_MASTER );
		$arr = [];

		$res = $dbr->select( 'thread_pending_relationship', '*', [ 1 ], __METHOD__ );

		foreach ( $res as $row ) {
			$title = $row->tpr_title;
			$entry = [
				'thread' => $row->tpr_thread,
				'relationship' => $row->tpr_relationship,
				'title' => $title,
				'type' => $row->tpr_type,
			];

			if ( !isset( $arr[$title] ) ) {
				$arr[$title] = [];
			}

			$arr[$title][] = $entry;
		}

		return $arr;
	}

	public static function addPendingRelationship(
		$thread, $relationship, $title, $type, &$array
	) {
		$entry = [
			'thread' => $thread,
			'relationship' => $relationship,
			'title' => $title,
			'type' => $type,
		];

		$row = [];
		foreach ( $entry as $k => $v ) {
			$row['tpr_' . $k] = $v;
		}

		$dbw = wfGetDB( DB_MASTER );
		$dbw->insert( 'thread_pending_relationship', $row, __METHOD__ );

		if ( !isset( $array[$title] ) ) {
			$array[$title] = [];
		}

		$array[$title][] = $entry;
	}

	/**
	 * Do not allow users to read threads on talkpages that they cannot read.
	 *
	 * @param Title $title
	 * @param User $user
	 * @param string $action
	 * @param bool &$result
	 * @return bool
	 */
	public static function onGetUserPermissionsErrors( $title, $user, $action, &$result ) {
		if ( $title->getNamespace() != NS_LQT_THREAD || $action != 'read' ) {
			return true;
		}

		$thread = Threads::withRoot( new Article( $title, 0 ) );

		if ( !$thread ) {
			return true;
		}

		$talkpage = $thread->article();

		$canRead = $talkpage->getTitle()->quickUserCan( 'read', $user );

		if ( $canRead ) {
			return true;
		} else {
			$result = false;
			return false;
		}
	}

	/**
	 * @param Parser $parser
	 * @return bool
	 */
	public static function onParserFirstCallInit( $parser ) {
		$parser->setFunctionHook(
			'useliquidthreads',
			[ 'LqtParserFunctions', 'useLiquidThreads' ]
		);

		$parser->setFunctionHook(
			'lqtpagelimit',
			[ 'LqtParserFunctions', 'lqtPageLimit' ]
		);

		global $wgLiquidThreadsAllowEmbedding;

		if ( $wgLiquidThreadsAllowEmbedding ) {
			$parser->setHook( 'talkpage', [ 'LqtParserFunctions', 'lqtTalkPage' ] );
			$parser->setHook( 'thread', [ 'LqtParserFunctions', 'lqtThread' ] );
		}

		return true;
	}

	/**
	 * @param array &$list
	 * @return bool
	 */
	public static function onCanonicalNamespaces( &$list ) {
		$list[NS_LQT_THREAD] = 'Thread';
		$list[NS_LQT_THREAD_TALK] = 'Thread_talk';
		$list[NS_LQT_SUMMARY] = 'Summary';
		$list[NS_LQT_SUMMARY_TALK] = 'Summary_talk';
		return true;
	}

	public static function onAPIQueryAfterExecute( $module ) {
		if ( $module instanceof ApiQueryInfo ) {
			$result = $module->getResult();

			$data = (array)$result->getResultData( [ 'query', 'pages' ], [
				'Strip' => 'base'
			] );
			foreach ( $data as $pageid => $page ) {
				if ( $page == 'page' ) {
					continue;
				}

				if ( isset( $page['title'] )
					&& LqtDispatch::isLqtPage( Title::newFromText( $page['title'] ) )
				) {
					$result->addValue(
						[ 'query', 'pages' ],
						$pageid,
						[ 'islqttalkpage' => '' ]
					);
				}
			}
		}

		return true;
	}

	public static function onInfoAction( $context, $pageInfo ) {
		if ( LqtDispatch::isLqtPage( $context->getTitle() ) ) {
			$pageInfo['header-basic'][] = [
				wfMessage( 'pageinfo-usinglqt' ), wfMessage( 'pageinfo-usinglqt-yes' )
			];
		}

		return true;
	}

	public static function onSpecialPage_initList( &$aSpecialPages ) {
		global $wgLiquidThreadsEnableNewMessages;

		if ( !$wgLiquidThreadsEnableNewMessages ) {
			if ( isset( $aSpecialPages['NewMessages'] ) ) {
				unset( $aSpecialPages['NewMessages'] );
			}
		}
		return true;
	}
}
