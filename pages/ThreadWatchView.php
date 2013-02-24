<?php
if ( !defined( 'MEDIAWIKI' ) ) die;

class ThreadWatchView extends LqtView {
	function show() {
		// Don't override core action=watch and action=unwatch.
		return true;
	}
}
