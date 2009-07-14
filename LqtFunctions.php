<?php
if ( !defined( 'MEDIAWIKI' ) )
	die();

/*
* Get a value from a global array if it exists, otherwise use $default
*/
function efArrayDefault( $name, $key, $default ) {
	global $$name;
	if ( isset( $$name ) && is_array( $$name ) && array_key_exists( $key, $$name ) ) {
		$foo = $$name;
		return $foo[$key];
	} else {
		return $default;
	}
}

/**
 * Recreate the original associative array so that a new pair with the given key
 * and value is inserted before the given existing key. $original_array gets
 * modified in-place.
*/
function efInsertIntoAssoc( $new_key, $new_value, $before, &$original_array ) {
	$ordered = array();
	$i = 0;
	foreach ( $original_array as $key => $value ) {
		$ordered[$i] = array( $key, $value );
		$i += 1;
	}
	$new_assoc = array();
	foreach ( $ordered as $pair ) {
		if ( $pair[0] == $before ) {
			$new_assoc[$new_key] = $new_value;
		}
		$new_assoc[$pair[0]] = $pair[1];
	}
	$original_array = $new_assoc;
}

function efVarDump( $value ) {
	wfVarDump( $value );
}

function efThreadTable( $ts ) {
	global $wgOut;
	$html = '';
	foreach ( $ts as $t ) {
		$html .= efThreadTableHelper( $t, 0 );
	}
	$html = "<table><tbody>\n$html\n</tbody></table>";
	$wgOut->addHTML( $html );
}

function efThreadTableHelper( $thread, $indent ) {
	$html = '';
	
	$html .= Xml::tags( '<td>', null, $indent );
	$html .= Xml::tags( '<td>', null, $thread->id() );
	$html .= Xml::tags( '<td>', null, $thread->title()->getPrefixedText() );

	$html = Xml::tags( 'tr', null, $html );
	
	foreach ( $t->subthreads() as $subthread ) {
		$html .= efThreadTableHelper( $subthread, $indent + 1 );
	}
	
	return $html;
}

function efLqtBeforeWatchlistHook( &$conds, &$tables, &$join_conds, &$fields ) {
	global $wgOut, $wgUser;

	if ( !in_array( 'page', $tables ) ) {
		$tables[] = 'page';
		$join_conds['page'] = array( 'LEFT JOIN', 'rc_cur_id=page_id' );
	}
	$conds[] = "page_namespace != " . NS_LQT_THREAD;

	$talkpage_messages = NewMessages::newUserMessages( $wgUser );
	$tn = count( $talkpage_messages );

	$watch_messages = NewMessages::watchedThreadsForUser( $wgUser );
	$wn = count( $watch_messages );

	if ( $tn == 0 && $wn == 0 )
		return true;

	LqtView::addJSandCSS();
	wfLoadExtensionMessages( 'LiquidThreads' );
	$messages_title = SpecialPage::getPage( 'NewMessages' )->getTitle();
	$new_messages = wfMsg ( 'lqt-new-messages' );
	
	$sk = $wgUser->getSkin();
	$link = $sk->link( $messages_title, $new_messages,
							array( 'class' => 'lqt_watchlist_messages_notice' ) );
	$wgOut->addHTML( $link );

	return true;
}

function lqtFormatMoveLogEntry( $type, $action, $title, $sk, $parameters ) {
	return wfMsgExt( 'lqt-log-action-move', 'parseinline',
					array( $title->getPrefixedText(), $parameters[0], $parameters[1] ) );
}

function lqtGetPreferences( $user, &$preferences ) {
	global $wgEnableEmail;
	
	if ($wgEnableEmail) {
		wfLoadExtensionMessages( 'LiquidThreads' );
		$preferences['lqtnotifytalk'] =
			array(
				'type' => 'toggle',
				'label-message' => 'lqt-preference-notify-talk',
				'section' => 'personal/email'
			);
	}
	
	return true;
}

function lqtUpdateNewtalkOnEdit( $article ) {
	$title = $article->getTitle();
	
	if ( LqtDispatch::isLqtPage( $title ) ) {
		// They're only editing the header, don't update newtalk.
		return false;
	}
	
	return true;
}

function lqtSetupParserFunctions() {
	global $wgParser;
	
	$wgParser->setFunctionHook( 'useliquidthreads',
				array( 'LqtParserFunctions', 'useLiquidThreads' ) );
}

