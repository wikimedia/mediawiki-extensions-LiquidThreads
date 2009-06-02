<?php

// Utility script to fix your database to work with breaking changes made in r51021 of
// the LiquidThreads extension.

// NOTE: This script may not work properly if you have taken advantage of the features made
// possible by that revision (i.e. if you have set $wgLqtPages).

require_once ( getenv('MW_INSTALL_PATH') !== false
	? getenv('MW_INSTALL_PATH')."/maintenance/commandLine.inc"
	: dirname( __FILE__ ) . '/../../maintenance/commandLine.inc' );
	
$db = wfGetDB( DB_MASTER );

do { 
	$db->update( 'thread',
				array( 'thread_article_namespace=thread_article_namespace+1' ),
				array( 'thread_article_namespace mod 2 = 0'),
				__METHOD__,
				array( 'LIMIT' => 500 ) );
} while ( $db->affectedRows() > 0 );
