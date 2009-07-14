<?php

class LqtParserFunctions {
	static function useLiquidThreads( &$parser ) {
		$parser->mOutput->setProperty( 'use-liquid-threads', 1 );
	}
}
