<?php

class TalkpageHistoryView extends TalkpageView {
	public function show() {
		$talkpageTitle = $this->article->getTitle();
		$talkpageLink = $this->linkRenderer->makeLink( $talkpageTitle );

		$this->output->setPageTitleMsg( wfMessage( 'lqt-talkpage-history-title' ) );
		$this->output->setSubtitle( wfMessage( 'lqt-talkpage-history-subtitle' )
			->rawParams( $talkpageLink )->parse() );

		$pager = new TalkpageHistoryPager( $this, $this->article );

		$this->output->addParserOutputContent( $pager->getFullOutput() );

		return false;
	}

	public function customizeNavigation( $skin, &$links ) {
		TalkpageView::customizeTalkpageNavigation( $skin, $links, $this );
		$links['views']['history']['class'] = 'selected';
		$links['views']['view']['class'] = '';
	}
}
