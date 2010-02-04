<?php

class LqtParserFunctions {
	static function useLiquidThreads( &$parser, $param = '1' ) {
		$offParams = array( 'no', 'off', 'disable' );
		// Figure out if they want to turn it off or on.
		$param = trim( strtolower( $param ) );
		
		if ( in_array( $param, $offParams ) || !$param ) {
			$param = 0;
		} else {
			$param = 1;
		}
		
		$parser->mOutput->setProperty( 'use-liquid-threads', $param );
	}
	
	static function lqtPageLimit( &$parser, $param = null ) {
		if ($param && $param > 0) {
			$parser->mOutput->setProperty( 'lqt-page-limit', $param );
		}
	}
	
	static function lqtTalkPage( $parser, $args, $parser, $frame ) {
		global $wgStyleVersion;
		
		// Grab article.
		$title = null;
		if ( $args['talkpage'] ) {
			$title = Title::newFromText( $args['talkpage'] );
		}
		if ( is_null($title) ) {
			$title = $parser->getTitle();
		}
		
		$article = new Article( $title );
		$out = new OutputPage;
		
		global $wgUser, $wgRequest;
		$view = new TalkpageView( $out, $article, $title, $wgUser, $wgRequest );
		
		// Handle show/hide preferences. Header gone by default.
		$view->hideItems( 'header' );
		
		if ( array_key_exists( 'show', $args ) ) {
			$show = explode( ' ', $args['show'] );
			$view->setShownItems( $show );
		}
		
		$view->show();
		
		$scriptsStyles = LqtView::getScriptsAndStyles();
		$headItems = '';
		
		foreach( $scriptsStyles['inlineScripts'] as $iscript ) {
			$headItems .= Html::inlineScript( "\n$iscript\n" );
		}
		
		foreach( $scriptsStyles['scripts'] as $script ) {
			$headItems .= Html::linkedScript( "$script?$wgStyleVersion" );
		}
		
		foreach( $scriptsStyles['styles'] as $style ) {
			$headItems .= Html::linkedStyle( $style, 'all' );
		}
		
		$parser->getOutput()->addHeadItem( $headItems, 'lqt-talk-page' );
		
		return $out->getHTML();
	}
}
