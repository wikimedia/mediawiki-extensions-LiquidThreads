<?php

use MediaWiki\Language\ReplacementArray;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use MediaWiki\Page\Article;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Parser\PPFrame;
use MediaWiki\Title\Title;

class LqtParserFunctions {

	private const LQT_REPLACEMENTS_DATA_KEY = 'liquidthreads_replacements';

	public static function useLiquidThreads( Parser $parser, $param = '1' ) {
		$offParams = [ 'no', 'off', 'disable' ];
		// Figure out if they want to turn it off or on.
		$param = trim( strtolower( $param ) );

		if ( in_array( $param, $offParams ) || !$param ) {
			$param = '0';
		} else {
			$param = '1';
		}

		$parser->getOutput()->setUnsortedPageProperty( 'use-liquid-threads', $param );
	}

	public static function lqtPageLimit( Parser $parser, $param = null ) {
		if ( $param && $param > 0 ) {
			$parser->getOutput()->setUnsortedPageProperty( 'lqt-page-limit', $param );
		}
	}

	/**
	 * Adds $data under $text key to ParserOutput $out extension data
	 * LQT_REPLACEMENTS_DATA_KEY property.
	 *
	 * @param ParserOutput $pout
	 * @param string $text
	 * @param array $data
	 */
	private static function addToExtensionData( ParserOutput $pout, string $text, array $data ) {
		$lqtReplacements = $pout->getExtensionData( self::LQT_REPLACEMENTS_DATA_KEY ) ?? [];
		$lqtReplacements[$text] = $data;
		$pout->setExtensionData( self::LQT_REPLACEMENTS_DATA_KEY, $lqtReplacements );
	}

	/** To bypass the parser cache just for the LiquidThreads part, we have a cute trick.
	 * We leave a placeholder comment in the HTML, which we expand out in a hook. This way,
	 * most of the page can be cached, but the LiquidThreads dynamism still works.
	 * Thanks to Tim for the idea.
	 * @param string $content
	 * @param array $args
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @return string
	 */
	public static function lqtTalkPage( $content, $args, $parser, $frame ) {
		$pout = $parser->getOutput();

		// Prepare information.
		$title = null;
		if ( !empty( $args['talkpage'] ) ) {
			$title = Title::newFromText( $args['talkpage'] );
		}
		$title ??= $parser->getTitle();

		$talkpage = new Article( $title, 0 );
		$article = new Article( $parser->getTitle(), 0 );

		$data = [
			'type' => 'talkpage',
			'args' => $args,
			'article' => $article,
			'title' => $article->getTitle(),
			'talkpage' => $talkpage,
		];

		// Generate a token
		$tok = MWCryptRand::generateHex( 32 );
		$text = '<!--LQT-PAGE-' . $tok . '-->';
		self::addToExtensionData( $pout, $text, $data );
		return $text;
	}

	public static function lqtThread( $content, $args, $parser, $frame ) {
		$pout = $parser->getOutput();

		// Prepare information.
		$title = Title::newFromText( $args['thread'] );
		$thread = null;
		if ( $args['thread'] ) {
			if ( is_numeric( $args['thread'] ) ) {
				$thread = Threads::withId( $args['thread'] );
			} elseif ( $title ) {
				$thread = Threads::withRoot(
					MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title )
				);
			}
		}

		if ( $thread === null ) {
			return '';
		}

		$data = [
			'type' => 'thread',
			'args' => $args,
			'thread' => $thread->id(),
			'title' => $thread->title(),
		];

		// Generate a token
		$tok = MWCryptRand::generateHex( 32 );
		$text = '<!--LQT-THREAD-' . $tok . '-->';
		self::addToExtensionData( $pout, $text, $data );
		return $text;
	}

	private static function runLqtTalkPage( $details, OutputPage $out ) {
		$title = $details["title"];
		$article = $details["article"];
		$talkpage = $details["talkpage"];
		$args = $details["args"];

		global $wgRequest;
		$oldOut = $out->getHTML();
		$out->clearHTML();

		$user = $out->getUser();
		$view = new TalkpageView( $out, $article, $title, $user, $wgRequest );
		$view->setTalkPage( $talkpage );

		// Handle show/hide preferences. Header gone by default.
		$view->hideItems( 'header' );

		if ( array_key_exists( 'show', $args ) ) {
			$show = explode( ' ', $args['show'] );
			$view->setShownItems( $show );
		}

		$view->show();

		$html = $out->getHTML();
		$out->clearHTML();

		return $html;
	}

	private static function showLqtThread( $details, OutputPage $out ) {
		$title = $details["title"];
		$article = $details["article"];

		global $wgRequest;
		$oldOut = $out->getHTML();
		$out->clearHTML();

		$thread = Threads::withRoot( MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title ) );

		$user = $out->getUser();
		$view = new LqtView( $out, $article, $title, $user, $wgRequest );

		$view->showThread( $thread );

		$html = $out->getHTML();
		$out->clearHTML();

		return $html;
	}

	public static function onAddParserOutput( OutputPage $out, ParserOutput $pout ) {
		if ( !$pout->getExtensionData( self::LQT_REPLACEMENTS_DATA_KEY ) ) {
			return true;
		}

		if ( !isset( $out->mLqtReplacements ) ) {
			$out->mLqtReplacements = [];
		}

		$lqtReplacements = $pout->getExtensionData( self::LQT_REPLACEMENTS_DATA_KEY );
		foreach ( $lqtReplacements as $text => $details ) {
			$result = '';

			if ( !is_array( $details ) ) {
				continue;
			}

			if ( $details['type'] == 'talkpage' ) {
				$result = self::runLqtTalkPage( $details, $out );
			} elseif ( $details['type'] == 'thread' ) {
				$result = self::showLqtThread( $details, $out );
			}

			$out->mLqtReplacements[$text] = $result;
			$out->addModules( 'ext.liquidThreads' );
		}

		return true;
	}

	public static function onAddHTML( OutputPage $out, &$text ) {
		if ( !isset( $out->mLqtReplacements ) || !count( $out->mLqtReplacements ) ) {
			return true;
		}

		$replacements = $out->mLqtReplacements;

		$replacer = new ReplacementArray( $replacements );
		$text = $replacer->replace( $text );

		return true;
	}
}
