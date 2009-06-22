<?php

class LqtParserFunctions {
	function archivestartdays( &$parser, $param1 ) {
		$parser->mOutput->setProperty( 'lqt-archivestartdays', $param1 );
	}
}
