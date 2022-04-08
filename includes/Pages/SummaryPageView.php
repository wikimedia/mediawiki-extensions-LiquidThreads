<?php

use MediaWiki\MediaWikiServices;

class SummaryPageView extends LqtView {
	public function show() {
		$thread = Threads::withSummary( $this->article->getPage() );
		if ( $thread && $thread->root() ) {
			$t = $thread->root()->getTitle();
			$link = MediaWikiServices::getInstance()->getLinkRenderer()->makeLink( $t );
			$this->output->setSubtitle(
				wfMessage( 'lqt_summary_subtitle' )->rawParams( $link )->parse() );
		}
		return true;
	}
}
