<?php

namespace MediaWiki\Extension\LiquidThreads;

use Article;
use ChangesList;
use HtmlArmor;
use LqtDispatch;
use LqtParserFunctions;
use LqtView;
use MediaWiki\Api\ApiQueryInfo;
use MediaWiki\Content\TextContent;
use MediaWiki\Context\RequestContext;
use MediaWiki\EditPage\EditPage;
use MediaWiki\Installer\DatabaseUpdater;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use MediaWiki\Parser\Parser;
use MediaWiki\RenameUser\RenameuserSQL;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Storage\EditResult;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;
use MediaWiki\Xml\Xml;
use NewMessages;
use RecentChange;
use Thread;
use Threads;
use UtfNormal\Validator;
use WikiImporter;
use Wikimedia\Message\MessageSpecifier;
use WikiPage;
use XMLReader;

class Hooks {
	/** @var string|null Used to inform hooks about edits that are taking place. */
	public static $editType = null;
	/** @var Thread|null Used to inform hooks about edits that are taking place. */
	public static $editThread = null;
	/** @var Thread|null Used to inform hooks about edits that are taking place. */
	public static $editAppliesTo = null;

	/**
	 * @var Article|null
	 */
	public static $editArticle = null;
	/**
	 * @var Article|null
	 */
	public static $editTalkpage = null;

	/** @var string[] */
	public static $editedStati = [
		Threads::EDITED_NEVER => 'never',
		Threads::EDITED_HAS_REPLY => 'has-reply',
		Threads::EDITED_BY_AUTHOR => 'by-author',
		Threads::EDITED_BY_OTHERS => 'by-others'
	];
	/** @var string[] */
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
		$rcTitle = $rc->getTitle();
		if ( $rcTitle->getNamespace() != NS_LQT_THREAD ) {
			return true;
		}

		$thread = Threads::withRoot( MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $rcTitle ) );
		if ( !$thread ) {
			return true;
		}

		$changeslist->getOutput()->addModules( 'ext.liquidThreads' );
		$lang = $changeslist->getLanguage();

		// Custom display for new posts.
		if ( $rc->getAttribute( 'rc_source' ) === RecentChange::SRC_NEW ) {
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
			$s .= ' ' . $changeslist->msg( $msg )->rawParams( $link )->parse();

			$s .= $lang->getDirMark();

			// add the truncated post content
			$content = $thread->root()->getPage()->getContent();
			$quote = ( $content instanceof TextContent ) ? $content->getText() : '';
			$quote = $lang->truncateForVisual( $quote, 200 );
			$s .= ' ' . MediaWikiServices::getInstance()->getCommentFormatter()->formatBlock( $quote );

			$classes = [];
			$changeslist->insertTags( $s, $rc, $classes );
			$changeslist->insertExtra( $s, $rc, $classes );
		}

		return true;
	}

	/**
	 * @param string &$newMessagesAlert
	 * @param array $newtalks
	 * @param User $user
	 * @param OutputPage $out
	 * @return bool
	 */
	public static function setNewtalkHTML( &$newMessagesAlert, $newtalks, $user, $out ) {
		$usertalk_t = $user->getTalkPage();

		// If the user isn't using LQT on their talk page, bail out
		if ( !LqtDispatch::isLqtPage( $usertalk_t ) ) {
			return true;
		}

		$pageTitle = $out->getTitle();
		$newmsg_t = SpecialPage::getTitleFor( 'NewMessages' );
		$watchlist_t = SpecialPage::getTitleFor( 'Watchlist' );

		if ( $newtalks
			&& !$newmsg_t->equals( $pageTitle )
			&& !$watchlist_t->equals( $pageTitle )
			&& !$usertalk_t->equals( $pageTitle )
		) {
			$newMessagesAlert = $out->msg( 'lqt_youhavenewmessages', $newmsg_t->getPrefixedText() )->parse();
			$out->setCdnMaxage( 0 );
			return true;
		}

		return false;
	}

	public static function beforeWatchlist(
		$name, &$tables, &$fields, &$conds, &$query_options, &$join_conds, $opts
	) {
		global $wgLiquidThreadsEnableNewMessages;

		if ( !$wgLiquidThreadsEnableNewMessages ) {
			return true;
		}

		if ( $name !== 'Watchlist' ) {
			return true;
		}

		// Only reading from this DB, but presumably getting primary for latest content.
		$dbw = MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();
		$context = RequestContext::getMain();
		$user = $context->getUser();

		if ( !in_array( 'page', $tables ) ) {
			$tables[] = 'page';
			// Yes, this is the correct field to join to. Weird naming.
			$join_conds['page'] = [ 'LEFT JOIN', 'rc_cur_id=page_id' ];
		}
		$conds[] = $dbw->expr( 'page_namespace', '=', null )->or( 'page_namespace', '!=', NS_LQT_THREAD );

		$talkpage_messages = NewMessages::newUserMessages( $user );
		$tn = count( $talkpage_messages );

		$watch_messages = NewMessages::watchedThreadsForUser( $user );
		$wn = count( $watch_messages );

		if ( $tn == 0 && $wn == 0 ) {
			return true;
		}

		$out = $context->getOutput();
		$out->addModules( 'ext.liquidThreads' );
		$messages_title = SpecialPage::getTitleFor( 'NewMessages' );
		$new_messages = wfMessage( 'lqt-new-messages' )->parse();

		$link = MediaWikiServices::getInstance()->getLinkRenderer()->makeLink(
			$messages_title,
			new HtmlArmor( $new_messages ),
			[ 'class' => 'lqt_watchlist_messages_notice' ] );
		$out->addHTML( $link );

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

		if ( $thread->hasSuperthread() ) {
			if ( $thread->superthread()->title() ) {
				$attribs['ThreadParent'] = $thread->superthread()->title()->getPrefixedText();
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

	public static function customiseSearchResultTitle( &$title, &$text, $result, $terms, $page ) {
		if ( $title->getNamespace() != NS_LQT_THREAD ) {
			return true;
		}

		$thread = Threads::withRoot( MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title ) );

		if ( $thread ) {
			$text = $thread->subject();

			$title = clone $thread->topmostThread()->title();
			$title->setFragment( '#' . $thread->getAnchorName() );
		}

		return true;
	}

	/**
	 * For integration with user renames.
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

	/** @var string[][] */
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

		if (
			!$article->getPage()->exists()
			&& $title->getNamespace() == NS_LQT_THREAD
		) {
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
	public static function onLoadExtensionSchemaUpdates( ?DatabaseUpdater $updater = null ) {
		$dir = realpath( __DIR__ . '/../sql' );
		$dbType = $updater->getDB()->getType();
		$updater->addExtensionTable( 'thread', "$dir/$dbType/tables-generated.sql" );

		// 1.31
		$updater->dropExtensionIndex(
			'thread',
			'thread_root_2',
			"$dir/patches/thread-drop-thread_root_2.sql"
		);

		if ( $dbType === 'mysql' ) {
			// 1.39
			$updater->modifyExtensionField(
				'thread',
				'thread_created',
				"$dir/$dbType/patch-thread-timestamps.sql"
			);
			$updater->modifyExtensionField(
				'user_message_state',
				'ums_read_timestamp',
				"$dir/$dbType/patch-user_message_state-ums_read_timestamp.sql"
			);
			$updater->modifyExtensionField(
				'thread_history',
				'th_timestamp',
				"$dir/$dbType/patch-thread_history-th_timestamp.sql"
			);
			$updater->dropExtensionIndex(
				'thread',
				'thread_root_2',
				"$dir/$dbType/patch-thread-drop-index.sql"
			);
		} elseif ( $dbType === 'postgres' ) {
			// 1.39
			$updater->addExtensionUpdate( [
				'dropDefault', 'thread', 'thread_modified'
			] );
			$updater->addExtensionUpdate( [
				'dropDefault', 'thread', 'thread_created'
			] );
			$updater->addExtensionUpdate( [
				'changeField', 'thread_history', 'th_timestamp', 'TIMESTAMPTZ', 'th_timestamp::timestamp with time zone'
			] );
			$updater->addExtensionUpdate( [
				'dropConstraint', 'thread', 'thread_thread_root_key', 'unique'
			] );
			$updater->addExtensionUpdate( [
				'renameIndex', 'thread', 'thread_root_page', 'thread_root'
			] );
			$updater->addExtensionUpdate( [
				'renameIndex', 'thread', 'thread_author', 'thread_author_name'
			] );
			$updater->addExtensionUpdate( [
				'addPgIndex', 'thread', 'thread_parent', '(thread_parent)'
			] );
			$updater->addExtensionUpdate( [
				'changeField', 'thread', 'thread_editedness', 'INT', 'thread_editedness::INT DEFAULT 0'
			] );
			$updater->addExtensionUpdate( [
				'changeField', 'thread', 'thread_article_namespace', 'INT', ''
			] );
			$updater->addExtensionUpdate( [
				'changeField', 'thread', 'thread_type', 'INT', 'thread_type::INT DEFAULT 0'
			] );
			$updater->addExtensionUpdate( [
				'changeNullableField', 'thread', 'thread_replies', 'NULL', true
			] );
			$updater->addExtensionIndex(
				'historical_thread', 'historical_thread_pkey', "$dir/$dbType/patch-historical_thread-pk.sql"
			);
			$updater->addExtensionIndex(
				'user_message_state', 'user_message_state_pkey', "$dir/$dbType/patch-user_message_state-pk.sql"
			);
			$updater->addExtensionUpdate( [
				'renameIndex', 'thread_history', 'thread_history_thread', 'th_thread_timestamp'
			] );
			$updater->addExtensionUpdate( [
				'renameIndex', 'thread_history', 'thread_history_user', 'th_user_text'
			] );
			$updater->addExtensionUpdate( [
				'addPgIndex', 'thread_history', 'th_timestamp_thread', '(th_timestamp,th_thread)'
			] );
			$updater->addExtensionUpdate( [
				'renameIndex', 'thread_reaction', 'thread_reaction_user_text_value', 'tr_user_text_value'
			] );
		}

		$updater->dropExtensionIndex(
			'thread',
			'thread_root_page',
			"$dir/patches/thread-drop-thread_root_page.sql"
		);

		return true;
	}

	public static function onPageMoveComplete(
		LinkTarget $oldLinkTarget,
		LinkTarget $newLinkTarget,
		UserIdentity $user,
		int $pageId,
		int $redirId,
		string $reason,
		RevisionRecord $revisionRecord
	) {
		$oldTitle = Title::newFromLinkTarget( $oldLinkTarget );
		$newTitle = Title::newFromLinkTarget( $newLinkTarget );
		// Check if it's a talk page.
		if ( !LqtDispatch::isLqtPage( $oldTitle ) &&
			!LqtDispatch::isLqtPage( $newTitle )
		) {
			return true;
		}

		// Synchronise the first 500 threads, in reverse order by thread id. If
		// there are more threads to synchronise, the job queue will take over.
		Threads::synchroniseArticleData(
			MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $newTitle ),
			500,
			'cascade'
		);
	}

	public static function onMovePageIsValidMove( Title $oldTitle ) {
		// Synchronise article data so that moving the article doesn't break any
		// article association.
		Threads::synchroniseArticleData(
			MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $oldTitle )
		);

		return true;
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
			$thread = Threads::withRoot(
				MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title )
			);

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

	public static function onSkinTemplateNavigation( $skinTemplate, &$links ) {
		$user = $skinTemplate->getUser();

		if ( $user->isAnon() ) {
			return true;
		}

		global $wgLiquidThreadsEnableNewMessages;

		if ( $wgLiquidThreadsEnableNewMessages ) {
			$newMessagesCount = NewMessages::newMessageCount( $user );

			// Add new messages link.
			$url = SpecialPage::getTitleFor( 'NewMessages' )->getLocalURL();
			$msg = 'lqt-newmessages-n';
			$newMessagesLink = [
				'href' => $url,
				'text' => wfMessage( $msg )->numParams( $newMessagesCount )->text(),
				'active' => $newMessagesCount > 0,
			];

			$insertUrls = [ 'newmessages' => $newMessagesLink ];
			$personal_urls = $links['user-menu'] ?? [];
			// User has viewmywatchlist permission
			if ( isset( $personal_urls['watchlist'] ) ) {
				$personal_urls = wfArrayInsertAfter( $personal_urls, $insertUrls, 'watchlist' );
			} else {
				$personal_urls = wfArrayInsertAfter( $personal_urls, $insertUrls, 'preferences' );
			}
			$links['user-menu'] = $personal_urls;
		}

		return true;
	}

	/**
	 * @param WikiPage $wikiPage
	 * @param UserIdentity $user
	 * @param string $summary
	 * @param int $flags
	 * @param RevisionRecord $revisionRecord
	 * @param EditResult $editResult
	 * @return bool
	 */
	public static function onPageSaveComplete(
		WikiPage $wikiPage,
		UserIdentity $user,
		string $summary,
		int $flags,
		RevisionRecord $revisionRecord,
		EditResult $editResult
	) {
		$title = $wikiPage->getTitle();
		if ( $title->getNamespace() != NS_LQT_THREAD ) {
			// Not a thread
			return true;
		}

		if ( $flags & EDIT_NEW ) {
			// New page
			return true;
		}

		$thread = Threads::withRoot( $wikiPage );

		if ( !$thread ) {
			// No matching thread.
			return true;
		}

		$content = $wikiPage->getContent();
		LqtView::editMetadataUpdates(
			[
				'root' => $wikiPage,
				'thread' => $thread,
				'summary' => $summary,
				'text' => ( $content instanceof TextContent ) ? $content->getText() : '',
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
	 * Handles tags in Page sections of XML dumps
	 * @param WikiImporter $importer
	 * @param array &$pageInfo
	 * @return bool
	 */
	public static function handlePageXMLTag( $importer, &$pageInfo ) {
		$reader = $importer->getReader();
		if ( !( $reader->nodeType == XMLReader::ELEMENT &&
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
			if ( $reader->nodeType == XMLReader::END_ELEMENT &&
				$reader->name == 'DiscussionThreading'
			) {
				break;
			}

			$tag = $reader->name;

			if ( in_array( $tag, $fields ) ) {
				$pageInfo['DiscussionThreading'][$tag] = $importer->nodeContents();
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
		$pendingRelationships ??= self::loadPendingRelationships();

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

		$user = RequestContext::getMain()->getUser();
		$thread = Thread::create( $root, $article, $user, null, $type,
			$subject, $summary, null, $signature );

		if ( isset( $info['ThreadSummaryPage'] ) ) {
			$summaryPageName = $info['ThreadSummaryPage'];
			$summaryPage = new Article( Title::newFromText( $summaryPageName ), 0 );

			if ( $summaryPage->getPage()->exists() ) {
				$thread->setSummary( $summaryPage );
			} else {
				self::addPendingRelationship( $thread->id(), 'thread_summary_page',
					$summaryPageName, 'article', $pendingRelationships );
			}
		}

		if ( isset( $info['ThreadParent'] ) ) {
			$threadPageName = $info['ThreadParent'];
			$superthread = Threads::withRoot(
				MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle(
					Title::newFromText( $threadPageName )
				)
			);

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

	public static function applyPendingThreadRelationship( $pendingRelationship, Thread $thread ) {
		if ( $pendingRelationship['relationship'] == 'thread_parent' ) {
			$childThread = Threads::withId( $pendingRelationship['thread'] );

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

		$dbw = MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();

		$dbw->newUpdateQueryBuilder()
			->update( 'thread' )
			->set( [ $pendingRelationship['relationship'] => $articleID ] )
			->where( [ 'thread_id' => $pendingRelationship['thread'] ] )
			->caller( __METHOD__ )
			->execute();

		$dbw->newDeleteQueryBuilder()
			->deleteFrom( 'thread_pending_relationship' )
			->where( [ 'tpr_title' => $pendingRelationship['title'] ] )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * @return array
	 */
	public static function loadPendingRelationships() {
		$dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();
		$arr = [];

		$res = $dbr->newSelectQueryBuilder()
			->select( '*' )
			->from( 'thread_pending_relationship' )
			->caller( __METHOD__ )
			->fetchResultSet();

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

		$dbw = MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();
		$dbw->newInsertQueryBuilder()
			->insertInto( 'thread_pending_relationship' )
			->row( $row )
			->caller( __METHOD__ )
			->execute();

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
	 * @param array|string|MessageSpecifier &$result
	 * @return bool
	 */
	public static function onGetUserPermissionsErrors( $title, $user, $action, &$result ) {
		if ( $title->getNamespace() != NS_LQT_THREAD || $action != 'read' ) {
			return true;
		}

		$thread = Threads::withRoot( MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title ) );

		if ( !$thread ) {
			return true;
		}

		$talkpage = $thread->article();

		$canRead = MediaWikiServices::getInstance()->getPermissionManager()
			->quickUserCan( 'read', $user, $talkpage->getTitle() );

		if ( $canRead ) {
			return true;
		} else {
			$result = 'liquidthreads-blocked-read';
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
			[ LqtParserFunctions::class, 'useLiquidThreads' ]
		);

		$parser->setFunctionHook(
			'lqtpagelimit',
			[ LqtParserFunctions::class, 'lqtPageLimit' ]
		);

		global $wgLiquidThreadsAllowEmbedding;

		if ( $wgLiquidThreadsAllowEmbedding ) {
			$parser->setHook( 'talkpage', [ LqtParserFunctions::class, 'lqtTalkPage' ] );
			$parser->setHook( 'thread', [ LqtParserFunctions::class, 'lqtThread' ] );
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

	public static function onInfoAction( $context, &$pageInfo ) {
		if ( LqtDispatch::isLqtPage( $context->getTitle() ) ) {
			$pageInfo['header-basic'][] = [
				$context->msg( 'pageinfo-usinglqt' ), $context->msg( 'pageinfo-usinglqt-yes' )
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

	public static function onRegistration() {
		if ( !defined( 'NS_LQT_THREAD' ) ) {
			define( 'NS_LQT_THREAD', 90 );
			define( 'NS_LQT_THREAD_TALK', 91 );
			define( 'NS_LQT_SUMMARY', 92 );
			define( 'NS_LQT_SUMMARY_TALK', 93 );
		}
	}

	/**
	 * Add icon for Special:Preferences mobile layout
	 *
	 * @param array &$iconNames Array of icon names for their respective sections.
	 */
	public static function onPreferencesGetIcon( &$iconNames ) {
		$iconNames[ 'lqt' ] = 'speechBubbles';
	}
}
