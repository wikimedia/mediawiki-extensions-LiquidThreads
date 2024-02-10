<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

// To migrate later
$cfg['suppress_issue_types'][] = 'MediaWikiNoBaseException';

$cfg['file_list'] = array_merge(
	$cfg['file_list'],
	[
		'i18n/Lqt.namespaces.php',
	]
);

return $cfg;
