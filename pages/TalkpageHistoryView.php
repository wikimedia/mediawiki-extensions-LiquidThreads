<?php

class TalkpageHistoryView extends TalkpageView {
	function show() {
		$talkpageTitle = $this->article->getTitle();
		$talkpageLink = Linker::link( $talkpageTitle );

		$this->output->setPageTitle( wfMessage( 'lqt-talkpage-history-title' ) );
		$this->output->setSubtitle( wfMessage( 'lqt-talkpage-history-subtitle' )
			->rawParams( $talkpageLink )->parse() );

		$pager = new TalkpageHistoryPager( $this, $this->article );

		$html = $pager->getNavigationBar() .
			$pager->getBody() .
			$pager->getNavigationBar();

		$this->output->addHTML( $html );

		return false;
	}

	function customizeTabs( $skin, &$links ) {
		TalkpageView::customizeTalkpageTabs( $skin, $links, $this );

		$links['history']['class'] = 'selected';
	}

	function customizeNavigation( $skin, &$links ) {
		TalkpageView::customizeTalkpageNavigation( $skin, $links, $this );
		$links['views']['history']['class'] = 'selected';
		$links['views']['view']['class'] = '';
	}
}

class TalkpageHistoryPager extends ThreadHistoryPager {
	function __construct( $view, $talkpage ) {
		$this->talkpage = $talkpage;

		parent::__construct( $view, null );
	}

	function getFieldMessages() {
		$headers = array(
			'th_timestamp' => $this->msg( 'lqt-history-time' )->text(),
			'thread_subject' => $this->msg( 'lqt-history-thread' )->text(),
			'th_user_text' => $this->msg( 'lqt-history-user' )->text(),
			'th_change_type' => $this->msg( 'lqt-history-action' )->text(),
			'th_change_comment' => $this->msg( 'lqt-history-comment' )->text(),
			);

		return $headers;
	}

	function getQueryInfo() {
		$queryInfo = array(
			'tables' => array( 'thread_history', 'thread', 'page' ),
			'fields' => '*',
			'conds' => array( Threads::articleClause( $this->talkpage ) ),
			'options' => array( 'order by' => 'th_timestamp desc' ),
			'join_conds' => array(
				'thread' => array( 'LEFT JOIN', 'thread_id=th_thread' ),
				'page' => array( 'LEFT JOIN', 'thread_root=page_id' ),
			),
		);

		return $queryInfo;
	}

	function formatValue( $name, $value ) {
		global $wgLang, $wgContLang, $wgOut;

		$wgOut->setRobotPolicy( 'noindex, nofollow' );

		$row = $this->mCurrentRow;

		$ns = $row->page_namespace;
		$title = $row->page_title;

		if ( is_null( $ns ) ) {
			$ns = $row->thread_article_namespace;
			$title = $row->thread_article_title;
		}

		switch( $name ) {
			case 'thread_subject':
				$title = Title::makeTitleSafe(
					$ns,
					$title
				);

				$link = Linker::link(
					$title,
					htmlspecialchars( $value ),
					array(),
					array(),
					array( 'known' )
				);

				return Html::rawElement( 'div', array( 'dir' => $wgContLang->getDir() ), $link );
			case 'th_timestamp':
				$formatted = $wgLang->timeanddate( $value );
				$title = Title::makeTitleSafe(
					$ns,
					$title
				);

				return Linker::link(
					$title,
					$formatted,
					array(),
					array( 'lqt_oldid' => $row->th_id )
				);
			default:
				return parent::formatValue( $name, $value );
		}
	}
}
