<?php

class LqtParserFunctions {
	static function archivestartdays( &$parser, $param1 ) {
		$parser->mOutput->setProperty( 'lqt-archivestartdays', $param1 );
	}
}
