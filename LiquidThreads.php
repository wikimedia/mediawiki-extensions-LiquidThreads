<?php
if ( !defined( 'MEDIAWIKI' ) )
	die();

$wgExtensionCredits['other'][] = array(
	'path'           => __FILE__,
	'name'           => 'Liquid Threads',
	'version'        => '2.2-alpha',
	'url'            => 'https://www.mediawiki.org/wiki/Extension:LiquidThreads',
	'author'         => array( 'David McCabe', 'Andrew Garrett' ),
	'descriptionmsg' => 'lqt-desc',
);

require( 'LqtFunctions.php' );

define( 'NS_LQT_THREAD', efArrayDefault( 'egLqtNamespaceNumbers', 'Thread', 90 ) );
define( 'NS_LQT_THREAD_TALK', efArrayDefault( 'egLqtNamespaceNumbers', 'Thread_talk', 91 ) );
define( 'NS_LQT_SUMMARY', efArrayDefault( 'egLqtNamespaceNumbers', 'Summary', 92 ) );
define( 'NS_LQT_SUMMARY_TALK', efArrayDefault( 'egLqtNamespaceNumbers', 'Summary_talk', 93 ) );
define( 'LQT_NEWEST_CHANGES', 'nc' );
define( 'LQT_NEWEST_THREADS', 'nt' );
define( 'LQT_OLDEST_THREADS', 'ot' );

// Localisation
$wgMessagesDirs['LiquidThreads'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['LiquidThreadsMagic'] = __DIR__ . '/i18n/LiquidThreads.magic.php';
$wgExtensionMessagesFiles['LiquidThreadsNamespaces'] = __DIR__ . '/i18n/Lqt.namespaces.php';
$wgExtensionMessagesFiles['LiquidThreadsAlias'] = __DIR__ . '/i18n/Lqt.alias.php';

$lqtMessages = array(
	'lqt-ajax-updated',
	'lqt-ajax-update-link',
	'watch',
	'unwatch',
	'lqt-thread-link-url',
	'lqt-thread-link-title',
	'lqt-thread-link-copy',
	'lqt-sign-not-necessary',
	'lqt-summary-sign-not-necessary',
	'lqt-marked-as-read-placeholder',
	'lqt-email-undo',
	'lqt-change-subject',
	'lqt-save-subject',
	'lqt-ajax-no-subject',
	'lqt-ajax-invalid-subject',
	'lqt-save-subject-error-unknown',
	'lqt-cancel-subject-edit',
	'lqt-drag-activate',
	'lqt-drag-drop-zone',
	'lqt-drag-confirm',
	'lqt-drag-reparent',
	'lqt-drag-split',
	'lqt-drag-setsortkey',
	'lqt-drag-bump',
	'lqt-drag-save',
	'lqt-drag-reason',
	'lqt-drag-subject',
	'lqt-edit-signature',
	'lqt-preview-signature',
	'lqt_contents_title',
	'lqt-empty-text',
	'lqt-pagechange-editformopen',
);

// ResourceLoader
$lqtResourceTemplate = array(
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'LiquidThreads'
);

$wgResourceModules['ext.liquidThreads'] = $lqtResourceTemplate + array(
	'styles' => array(
		'lqt.css',
		'jquery/jquery.thread_collapse.css',
		'lqt.dialogs.css',
	),
	'scripts' => array(
		'lqt.js',
		'jquery/jquery.thread_collapse.js',
		'jquery/jquery.autogrow.js',
	),
	'dependencies' => array(
		'jquery.ui.dialog',
		'jquery.ui.droppable',
		'mediawiki.action.edit.preview',
		'mediawiki.api.watch',
		'user.tokens',
		'jquery.client',
		'user.options',
		'mediawiki.api',
		'mediawiki.util',
	),
	'messages' => $lqtMessages
);

$wgResourceModules['ext.liquidThreads.newMessages'] = $lqtResourceTemplate + array(
	'scripts' => array( 'newmessages.js' ),
	'dependencies' => array( 'ext.liquidThreads' ),
	'position' => 'top',
);

// Hooks
// Parser Function Setup
$wgHooks['ParserFirstCallInit'][] = 'LqtHooks::onParserFirstCallInit';

// Namespaces
$wgHooks['CanonicalNamespaces'][] = 'LqtHooks::onCanonicalNamespaces';

// Main dispatch hook
$wgHooks['MediaWikiPerformAction'][] = 'LqtDispatch::tryPage';
$wgHooks['SkinTemplateNavigation'][] = 'LqtDispatch::onSkinTemplateNavigation';
$wgHooks['PageContentLanguage'][] = 'LqtDispatch::onPageContentLanguage';

// Customisation of recentchanges
$wgHooks['OldChangesListRecentChangesLine'][] = 'LqtHooks::customizeOldChangesList';

// Notification (watchlist, newtalk)
$wgHooks['SkinTemplateOutputPageBeforeExec'][] = 'LqtHooks::setNewtalkHTML';
$wgHooks['SpecialWatchlistQuery'][] = 'LqtHooks::beforeWatchlist';
$wgHooks['ArticleEditUpdateNewTalk'][] = 'LqtHooks::updateNewtalkOnEdit';
$wgHooks['PersonalUrls'][] = 'LqtHooks::onPersonalUrls';
$wgHooks['EchoGetDefaultNotifiedUsers'][] = 'NewMessages::getDefaultNotifiedUsers';

// Preferences
$wgHooks['GetPreferences'][] = 'LqtHooks::getPreferences';

// Export-related
$wgHooks['XmlDumpWriterOpenPage'][] = 'LqtHooks::dumpThreadData';
$wgHooks['ModifyExportQuery'][] = 'LqtHooks::modifyExportQuery';
$wgHooks['OAIFetchRowsQuery'][] = 'LqtHooks::modifyOAIQuery';
$wgHooks['OAIFetchRecordQuery'][] = 'LqtHooks::modifyOAIQuery';

// Import-related
$wgHooks['ImportHandlePageXMLTag'][] = 'LqtHooks::handlePageXMLTag';
$wgHooks['AfterImportPage'][] = 'LqtHooks::afterImportPage';

// Deletion
$wgHooks['ArticleDeleteComplete'][] = 'LqtDeletionController::onArticleDeleteComplete';
$wgHooks['ArticleRevisionUndeleted'][] = 'LqtDeletionController::onArticleRevisionUndeleted';
$wgHooks['ArticleUndelete'][] = 'LqtDeletionController::onArticleUndelete';
$wgHooks['ArticleConfirmDelete'][] = 'LqtDeletionController::onArticleConfirmDelete';
$wgHooks['ArticleDelete'][] = 'LqtDeletionController::onArticleDelete';

// Moving
$wgHooks['TitleMoveComplete'][] = 'LqtHooks::onTitleMoveComplete';
$wgHooks['AbortMove'][] = 'LqtHooks::onArticleMove';
$wgHooks['MovePageIsValidMove'][] = 'LqtHooks::onMovePageIsValidMove';

// Search
$wgHooks['ShowSearchHitTitle'][] = 'LqtHooks::customiseSearchResultTitle';
$wgHooks['SpecialSearchProfiles'][] = 'LqtHooks::customiseSearchProfiles';

// Updates
$wgHooks['LoadExtensionSchemaUpdates'][] = 'LqtHooks::onLoadExtensionSchemaUpdates';

// Rename
$wgHooks['RenameUserSQL'][] = 'LqtHooks::onUserRename';

// UserMerge
$wgHooks['UserMergeAccountFields'][] = 'LqtHooks::onUserMergeAccountFields';

// Edit-related
$wgHooks['EditPageBeforeEditChecks'][] = 'LqtHooks::editCheckBoxes';
$wgHooks['ArticleSaveComplete'][] = 'LqtHooks::onArticleSaveComplete';

// Blocking
$wgHooks['UserIsBlockedFrom'][] = 'LqtHooks::userIsBlockedFrom';

// Protection
$wgHooks['TitleGetRestrictionTypes'][] = 'LqtHooks::getProtectionTypes';

// New User Messages
$wgHooks['SetupNewUserMessageSubject'][] = 'LqtHooks::setupNewUserMessageSubject';
$wgHooks['SetupNewUserMessageBody'][] = 'LqtHooks::setupNewUserMessageBody';

// JS variables
$wgHooks['MakeGlobalVariablesScript'][] = 'LqtHooks::onMakeGlobalVariablesScript';

// API
$wgHooks['APIQueryAfterExecute'][] = 'LqtHooks::onAPIQueryAfterExecute';

// Info
$wgHooks['InfoAction'][] = 'LqtHooks::onInfoAction';

// Special pages registration
$wgHooks['SpecialPage_initList'][] = 'LqtHooks::onSpecialPage_initList';

// Special pages
$wgSpecialPages['MoveThread'] = 'SpecialMoveThread';
$wgSpecialPages['NewMessages'] = 'SpecialNewMessages';
$wgSpecialPages['SplitThread'] = 'SpecialSplitThread';
$wgSpecialPages['MergeThread'] = 'SpecialMergeThread';

// Embedding
$wgHooks['OutputPageParserOutput'][] = 'LqtParserFunctions::onAddParserOutput';
$wgHooks['OutputPageBeforeHTML'][] = 'LqtParserFunctions::onAddHTML';

// Permissions
$wgHooks['userCan'][] = 'LqtHooks::onGetUserPermissionsErrors';

// Classes
$wgAutoloadClasses['LqtDispatch'] = __DIR__ . '/classes/Dispatch.php';
$wgAutoloadClasses['LqtView'] = __DIR__ . '/classes/View.php';
$wgAutoloadClasses['HistoricalThread'] = __DIR__ . '/classes/HistoricalThread.php';
$wgAutoloadClasses['Thread'] = __DIR__ . '/classes/Thread.php';
$wgAutoloadClasses['Threads'] = __DIR__ . '/classes/Threads.php';
$wgAutoloadClasses['NewMessages'] = __DIR__ . '/classes/NewMessagesController.php';
$wgAutoloadClasses['EchoLiquidThreadsFormatter'] = __DIR__. "/classes/EchoLiquidThreadsFormatter.php";
$wgAutoloadClasses['LqtParserFunctions'] = __DIR__ . '/classes/ParserFunctions.php';
$wgAutoloadClasses['LqtDeletionController'] = __DIR__ . '/classes/DeletionController.php';
$wgAutoloadClasses['LqtHooks'] = __DIR__ . '/classes/Hooks.php';
$wgAutoloadClasses['ThreadRevision'] = __DIR__ . '/classes/ThreadRevision.php';
$wgAutoloadClasses['SynchroniseThreadArticleDataJob'] = __DIR__ . '/classes/SynchroniseThreadArticleDataJob.php';
$wgAutoloadClasses['ThreadHistoryPager'] = __DIR__ . '/classes/ThreadHistoryPager.php';
$wgAutoloadClasses['TalkpageHistoryView'] = __DIR__ . '/pages/TalkpageHistoryView.php';
$wgAutoloadClasses['LqtLogFormatter'] = __DIR__ . '/classes/LogFormatter.php';

// View classes
$wgAutoloadClasses['TalkpageView'] = __DIR__ . '/pages/TalkpageView.php';
$wgAutoloadClasses['ThreadPermalinkView'] = __DIR__ . '/pages/ThreadPermalinkView.php';
$wgAutoloadClasses['TalkpageHeaderView'] = __DIR__ . '/pages/TalkpageHeaderView.php';
$wgAutoloadClasses['IndividualThreadHistoryView'] = __DIR__ . '/pages/IndividualThreadHistoryView.php';
$wgAutoloadClasses['ThreadDiffView'] = __DIR__ . '/pages/ThreadDiffView.php';
$wgAutoloadClasses['ThreadWatchView'] = __DIR__ . '/pages/ThreadWatchView.php';
$wgAutoloadClasses['ThreadProtectionFormView'] = __DIR__ . '/pages/ThreadProtectionFormView.php';
$wgAutoloadClasses['ThreadHistoryListingView'] = __DIR__ . '/pages/ThreadHistoryListingView.php';
$wgAutoloadClasses['ThreadHistoricalRevisionView'] = __DIR__ . '/pages/ThreadHistoricalRevisionView.php';
$wgAutoloadClasses['SummaryPageView'] = __DIR__ . '/pages/SummaryPageView.php';
$wgAutoloadClasses['NewUserMessagesView'] = __DIR__ . '/pages/NewUserMessagesView.php';

// Pagers
$wgAutoloadClasses['LqtDiscussionPager'] = __DIR__ . '/pages/TalkpageView.php';
$wgAutoloadClasses['LqtNewMessagesPager'] = __DIR__ . '/pages/NewUserMessagesView.php';
$wgAutoloadClasses['TalkpageHistoryPager'] = __DIR__ . '/pages/TalkpageHistoryView.php';

// Special pages
$wgAutoloadClasses['ThreadActionPage'] = __DIR__ . '/pages/ThreadActionPage.php';
$wgAutoloadClasses['SpecialMoveThread'] = __DIR__ . '/pages/SpecialMoveThread.php';
$wgAutoloadClasses['SpecialNewMessages'] = __DIR__ . '/pages/SpecialNewMessages.php';
$wgAutoloadClasses['SpecialSplitThread'] = __DIR__ . '/pages/SpecialSplitThread.php';
$wgAutoloadClasses['SpecialMergeThread'] = __DIR__ . '/pages/SpecialMergeThread.php';

// Job queue
$wgJobClasses['synchroniseThreadArticleData'] = 'SynchroniseThreadArticleDataJob';

// Logging
$wgLogTypes[] = 'liquidthreads';
$wgLogNames['liquidthreads']		  = 'lqt-log-name';
$wgLogHeaders['liquidthreads']		  = 'lqt-log-header';

foreach ( array( 'move', 'split', 'merge', 'subjectedit', 'resort', 'signatureedit' ) as $action ) {
	$wgLogActionsHandlers["liquidthreads/$action"] = 'LqtLogFormatter::formatLogEntry';
}

// Preferences
$wgDefaultUserOptions['lqtnotifytalk'] = false;
$wgDefaultUserOptions['lqtdisplaydepth'] = 5;
$wgDefaultUserOptions['lqtdisplaycount'] = 25;
$wgDefaultUserOptions['lqtcustomsignatures'] = true;

// API
$wgAutoloadClasses['ApiQueryLQTThreads'] = __DIR__ . '/api/ApiQueryLQTThreads.php';
$wgAPIListModules['threads'] = 'ApiQueryLQTThreads';
$wgAutoloadClasses['ApiFeedLQTThreads'] = __DIR__ . '/api/ApiFeedLQTThreads.php';
$wgAPIModules['feedthreads'] = 'ApiFeedLQTThreads';
$wgAutoloadClasses['ApiThreadAction'] = __DIR__ . '/api/ApiThreadAction.php';
$wgAPIModules['threadaction'] = 'ApiThreadAction';

// Whether or not to use the standard LiquidThreads notifications
$wgLiquidThreadsNotificationTypes = array( 'standard' );

// Echo
$wgExtensionFunctions[] = 'wfLiquidThreadsSetupEcho';

function wfLiquidThreadsSetupEcho() {
	// LiquidThreads echo notifications have not been fully tested,
	// turn it off temporarily till expected behaviors are verified
	/*
	global $wgLiquidThreadsNotificationTypes;
	global $wgEchoNotificationFormatters;
	global $wgEchoEnabledEvents;

	if ( isset( $wgEchoNotificationFormatters ) ) {
		$wgLiquidThreadsNotificationTypes = array( 'echo' );

		$wgEchoNotificationFormatters += array(
			'lqt-new-topic' => array(
				'class' => 'EchoLiquidThreadsFormatter',
				'title-message' => 'notification-add-talkpage-topic',
				'title-params' => array( 'agent', 'subject', 'title', 'content-page' ),
				'content-message' => 'notification-talkpage-content',
				'content-params' => array( 'commentText' ),
				'icon' => 'chat',
			),
			'lqt-reply' => array(
				'class' => 'EchoLiquidThreadsFormatter',
				'title-message' => 'notification-add-comment',
				'title-params' => array( 'agent', 'subject', 'title', 'content-page' ),
				'content-message' => 'notification-talkpage-content',
				'content-params' => array( 'commentText' ),
				'icon' => 'chat',
			),
		);

		$wgEchoEnabledEvents = array_merge( $wgEchoEnabledEvents, array(
			'lqt-new-topic',
			'lqt-reply',
		) );
	}
	*/
}

// Path to the LQT directory
$wgLiquidThreadsExtensionPath = "{$wgScriptPath}/extensions/LiquidThreads";

/** CONFIGURATION SECTION */

$wgDefaultUserOptions['lqt-watch-threads'] = true;

$wgGroupPermissions['user']['lqt-split'] = true;
$wgGroupPermissions['user']['lqt-merge'] = true;
$wgGroupPermissions['user']['lqt-react'] = true;

$wgAvailableRights[] = 'lqt-split';
$wgAvailableRights[] = 'lqt-merge';
$wgAvailableRights[] = 'lqt-react';

$wgPageProps['use-liquid-threads'] = 'Whether or not the page enabled or disabled LiquidThreads through a parser function';

/* Allows activation of LiquidThreads on individual pages */
$wgLqtPages = array();

/* Allows switching LiquidThreads off for regular talk pages
	(intended for testing and transition) */
$wgLqtTalkPages = true;

/* Whether or not to activate LiquidThreads email notifications */
$wgLqtEnotif = true;

/* Thread actions which do *not* cause threads to be "bumped" to the top */
/* Using numbers because the change type constants are defined in Threads.php, don't
	want to have to parse it on every page view */
$wgThreadActionsNoBump = array(
	3 /* Edited summary */,
	10 /* Merged from */,
	12 /* Split from */,
	2 /* Edited root */,
	14 /* Adjusted sortkey */
);

/** Switch this on if you've migrated from a version before around May 2009 */
$wgLiquidThreadsMigrate = false;

/** The default number of threads per page */
$wgLiquidThreadsDefaultPageLimit = 20;

/** Whether or not to allow users to activate/deactivate LiquidThreads per-page */
$wgLiquidThreadsAllowUserControl = true;

/** Whether or not to allow users to activate/deactivate LiquidThreads
	in specific namespaces.  NULL means either all or none, depending
	on the above. */
$wgLiquidThreadsAllowUserControlNamespaces = null;

/** Allow LiquidThreads embedding */
$wgLiquidThreadsAllowEmbedding = true;

// Namespaces in which to enable LQT
$wgLqtNamespaces = array();

/** Enable/disable the bump checkbox. **/
$wgLiquidThreadsShowBumpCheckbox = false;

/** Enable/Disable 'New messages' link and special page (Special:NewMessages) */
$wgLiquidThreadsEnableNewMessages = true;
