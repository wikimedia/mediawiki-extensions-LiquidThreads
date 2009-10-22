<?php

if ( !defined( 'MEDIAWIKI' ) ) die;

class ThreadHistoryListingView extends ThreadPermalinkView {
	function show() {
		global $wgHooks;
		$wgHooks['SkinTemplateTabs'][] = array( $this, 'customizeTabs' );

		if ( ! $this->thread ) {
			$this->showMissingThreadPage();
			return false;
		}
		self::addJSandCSS();
		wfLoadExtensionMessages( 'LiquidThreads' );
		
		$this->thread->updateHistory();

		$this->output->setPageTitle( wfMsg( 'lqt-history-title' ) );
		$this->output->setSubtitle( $this->getSubtitle() . '<br />' .
									wfMsg( 'lqt_hist_listing_subtitle' ) );
		$this->showThreadHeading( $this->thread );
		
		$pager = new ThreadHistoryPager( $this, $this->thread );
		
		$html = $pager->getNavigationBar() .
				$pager->getBody() .
				$pager->getNavigationBar();
				
		$this->output->addHTML( $html );
		
		$this->showThread( $this->thread );
		
		return false;
	}
}

