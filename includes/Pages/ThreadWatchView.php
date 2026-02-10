<?php

class ThreadWatchView extends LqtView {

	/** @inheritDoc */
	public function show() {
		// Don't override core action=watch and action=unwatch.
		return true;
	}
}
