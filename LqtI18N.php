<?php

/**
* Internationalisation file for Language Manager extension.
*
* @package MediaWiki
* @subpackage LiquidThreads
* @author David McCabe <davemccabe@gmail.com> / I18N file by Erik Moeller
* @licence GPL2
*/

if( !defined( 'MEDIAWIKI' ) ) {
	echo( "This file is an extension to the MediaWiki software and cannot be used standalone.\n" );
	die( -1 );
}

global $wgExtensionFunctions;
$wgExtensionFunctions[]='wfInitializeLqtMessages';

function wfInitializeLqtMessages() {
	global $wgMessageCache;
	$lqtMessages = array();
	$lqtMessages['en'] = array(
        	'lqt_browse_archive' => 'Browse archive',
		'lqt_recently_archived' => 'Recently archived',
        	'lqt_add_header'=>'Add header',
		'lqt_new_thread'=>'Start a new discussion',
		'lqt_move_placeholder'=>"''Placeholder left when the thread was moved to another page.''",
		'lqt_reply'=>'Reply',
		'lqt_delete'=>'Delete',
		'lqt_undelete'=>'Undelete',
		'lqt_permalink'=>'Permalink',
		'lqt_fragment'=>'a fragment of a $1 from $2',
		'lqt_discussion_link'=>'discussion', // substituted above
		'lqt_from_talk'=>'from $1',
		'lqt_hist_comment_edited'=>'Comment text edited',
		'lqt_hist_summary_changed'=>'Summary changed',
		'lqt_hist_reply_created'=>'New reply created',
		'lqt_hist_thread_created'=>'New thread created',
		'lqt_hist_deleted'=>'Deleted',
		'lqt_hist_undeleted'=>'Undeleted',
		'lqt_hist_moved_talkpage'=>'Moved',
		'lqt_history_subtitle'=>'Viewing a history listing.',
	);
        
        foreach( $lqtMessages as $key => $value ) {
                $wgMessageCache->addMessages( $lqtMessages[$key], $key );
        }
}

?>
