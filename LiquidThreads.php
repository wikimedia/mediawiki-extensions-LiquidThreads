<?php

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'LiquidThreads' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['LiquidThreads'] = __DIR__ . '/i18n';
	$wgExtensionMessagesFiles['LiquidThreadsMagic'] = __DIR__ . '/i18n/LiquidThreads.magic.php';
	$wgExtensionMessagesFiles['LiquidThreadsNamespaces'] = __DIR__ . '/i18n/Lqt.namespaces.php';
	$wgExtensionMessagesFiles['LiquidThreadsAlias'] = __DIR__ . '/i18n/Lqt.alias.php';
	/* wfWarn(
		'Deprecated PHP entry point used for LiquidThreads extension. ' .
		'Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	); */
	return;
} else {
	die( 'This version of the LiquidThreads extension requires MediaWiki 1.29+' );
}
