<?php

class LiquidThreadsMagicWords {
	static function getMagicWords( &$magicWords, $lang ) {
		$words = array();
		
		/**
		 * English
		 */
		$words['en'] = array(
			'useliquidthreads' => array( 0, 'UseLiquidThreads' ),
		);
		
		$magicWords += ( $lang == 'en' || !isset( $words[$lang] ) )
			? $words['en']
			: array_merge( $words['en'], $words[$lang] );
			
		return true;
	}
}
