<?php

class ThreadDiffView extends LqtView {
	function customizeNavigation( $skin, &$links ) {
		$remove = array( 'views/edit', 'views/viewsource' );

		foreach ( $remove as $rem ) {
			list( $section, $item ) = explode( '/', $rem, 2 );
			unset( $links[$section][$item] );
		}

		$links['views']['history']['class'] = 'selected';
	}
}
