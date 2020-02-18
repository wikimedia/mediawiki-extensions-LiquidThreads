<?php

class IndividualThreadHistoryView extends ThreadPermalinkView {
	protected $oldid;

	public function customizeNavigation( $skin, &$links ) {
		$links['views']['history']['class'] = 'selected';
		parent::customizeNavigation( $skin, $links );
		return true;
	}

	/**
	 * This customizes the subtitle of a history *listing* from the hook, and of an old revision
	 * from getSubtitle() below.
	 *
	 * @return true
	 */
	public function customizeSubtitle() {
		$msg = wfMessage( 'lqt_hist_view_whole_thread' )->text();
		$threadhist = $this->permalink(
			$this->thread->topmostThread(),
			$msg,
			'thread_history'
		);
		$this->output->setSubtitle(
			parent::getSubtitle() . '<br />' .
			$this->output->getSubtitle() .
			"<br />$threadhist"
		);
		return true;
	}

	public function getSubtitle() {
		$this->article->setOldSubtitle( $this->oldid );
		$this->customizeSubtitle();
		return $this->output->getSubtitle();
	}

	public function show() {
		global $wgHooks;

		if ( !$this->thread ) {
			$this->showMissingThreadPage();
			return false;
		}

		$wgHooks['PageHistoryBeforeList'][] = [ $this, 'customizeSubtitle' ];

		return true;
	}
}
