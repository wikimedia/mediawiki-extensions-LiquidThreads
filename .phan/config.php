<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['file_list'] = array_merge(
	$cfg['file_list'],
	[
		'i18n/Lqt.namespaces.php',
	]
);

$cfg['directory_list'] = array_merge(
	$cfg['directory_list'],
	[
		'api',
		'classes',
		'import',
		'pages',
		'../../extensions/Renameuser',
	]
);

$cfg['exclude_analysis_directory_list'] = array_merge(
	$cfg['exclude_analysis_directory_list'],
	[
		'../../extensions/Renameuser',
	]
);

return $cfg;
