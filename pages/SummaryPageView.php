<?php
if ( !defined( 'MEDIAWIKI' ) ) die;

class SummaryPageView extends LqtView {
	function show() {
		$thread = Threads::withSummary( $this->article );
		if ( $thread && $thread->root() ) {
			$t = $thread->root()->getTitle();
			$link = Linker::link( $t );
			$this->output->setSubtitle(
				wfMessage( 'lqt_summary_subtitle' )->rawParams( $link )->parse() );
		}
		return true;
	}
}
