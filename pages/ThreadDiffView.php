<?php

class ThreadDiffView extends LqtView {
	public function customizeNavigation( $skin, &$links ) {
		$remove = [ 'views/edit', 'views/viewsource' ];

		foreach ( $remove as $rem ) {
			list( $section, $item ) = explode( '/', $rem, 2 );
			unset( $links[$section][$item] );
		}

		$links['views']['history']['class'] = 'selected';
	}
}
