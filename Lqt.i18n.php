<?php

/**
* Internationalisation file for Liquid Threads extension.
*
* @package MediaWiki
* @subpackage LiquidThreads
* @author David McCabe <davemccabe@gmail.com> / I18n file by Erik Moeller
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
		'lqt_add_header' => 'Add header',
		'lqt_new_thread' => 'Start a new discussion',
		'lqt_move_placeholder' => "''Placeholder left when the thread was moved to another page.''",
		'lqt_reply' => 'Reply',
		'lqt_delete' => 'Delete',
		'lqt_undelete' => 'Undelete',
		'lqt_permalink' => 'Permalink',
		'lqt_fragment' => 'a fragment of a $1 from $2',
		'lqt_discussion_link' => 'discussion', // substituted above
		'lqt_from_talk' => 'from $1',
		'lqt_newer' => '«newer',
		'lqt_older' => 'older»',
		'lqt_hist_comment_edited' => 'Comment text edited',
		'lqt_hist_summary_changed' => 'Summary changed',
		'lqt_hist_reply_created' => 'New reply created',
		'lqt_hist_thread_created' => 'New thread created',
		'lqt_hist_deleted' => 'Deleted',
		'lqt_hist_undeleted' => 'Undeleted',
		'lqt_hist_moved_talkpage' => 'Moved',
		'lqt_hist_listing_subtitle' => 'Viewing a history listing.',
		'lqt_hist_view_whole_thread' => 'View history for the entire thread',
		'lqt_hist_no_revisions_error' => 'This thread doesn\'t have any history revisions. That\'s pretty weird.',
		'lqt_hist_past_last_page_error' => 'You are beyond the number of pages of history that exist.',
		'lqt_hist_tooltip_newer_disabled' => 'This link is disabled because you are on the first page.',
		'lqt_hist_tooltip_older_disabled' => 'This link is disabled because you are on the last page.',
		'lqt_revision_as_of' => "Revision as of $1.",
		'lqt_change_new_thread' => 'This is the thread\'s initial revision.',
		'lqt_change_reply_created' => 'The highlighted comment was created in this revision.',
		'lqt_change_edited_root' => 'The highlighted comment was edited in this revision.',
	);

	$lqtMessages['nl'] = array(
		'lqt_browse_archive' => 'Archief bekijken',
		'lqt_recently_archived' => 'Recent gearchiveerd',
		'lqt_add_header' => 'Voeg kopje toe',
		'lqt_new_thread' => 'Start een nieuw overleg',
		'lqt_move_placeholder' => "''Deze markering is achtergelaten toen de thread is verplaatst naar een andere pagina.''",
		'lqt_reply' => 'Antwoorden',
		'lqt_delete' => 'Verwijderen',
		'lqt_undelete' => 'Terugplaatsen',
		'lqt_permalink' => 'Permalink',
		'lqt_fragment' => 'een fragment van een $1 van $2',
		'lqt_discussion_link' => 'overleg', // substituted above
		'lqt_from_talk' => 'van $1',
		'lqt_newer' => 'nieuwer',
		'lqt_older' => 'ouder',
		'lqt_hist_comment_edited' => 'Tekst opmerking bewerkt',
		'lqt_hist_summary_changed' => 'Samenvatting aangepast',
		'lqt_hist_reply_created' => 'Nieuw antwoord gemaakt',
		'lqt_hist_thread_created' => 'Nieuwe thread gemaakt',
		'lqt_hist_deleted' => 'Verwijderd',
		'lqt_hist_undeleted' => 'Teruggeplaatst',
		'lqt_hist_moved_talkpage' => 'Verplaatst',
		'lqt_hist_listing_subtitle' => 'U bent een oudere versie aan het bekijken.',
		'lqt_hist_view_whole_thread' => 'Geschiedenis van de hele thread bekijken',
		'lqt_hist_no_revisions_error' => 'Deze thread heeft geen oudere versies. Dat is nogal vreemd..',
		'lqt_hist_past_last_page_error' => 'U heeft een hoger paginanummer gekozen dan bestaat in de geschiedenis.',
		'lqt_hist_tooltip_newer_disabled' => 'Deze link is niet actief omdat u op de eerste pagina bent.',
		'lqt_hist_tooltip_older_disabled' => 'Deze link is niet actief omdat u op de laatste pagina bent.',
		'lqt_revision_as_of' => "Versie per $1.",
		'lqt_change_new_thread' => 'Dit is de eerste versie van de thread.',
		'lqt_change_reply_created' => 'De gemarkeerde opmerking is in deze versie toegevoegd.',
		'lqt_change_edited_root' => 'De gemarkeerde opmerking is in deze versie bewerkt.',
	);

	foreach( $lqtMessages as $key => $value ) {
		$wgMessageCache->addMessages( $lqtMessages[$key], $key );
	}
}

?>
