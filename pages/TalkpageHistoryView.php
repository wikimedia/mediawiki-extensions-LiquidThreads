<?php

if ( !defined( 'MEDIAWIKI' ) ) die;

class TalkpageHistoryView extends TalkpageView {
	function show() {
		global $wgHooks, $wgUser;
		$wgHooks['SkinTemplateTabs'][] = array( $this, 'customizeTabs' );

		self::addJSandCSS();
		wfLoadExtensionMessages( 'LiquidThreads' );
		
		$sk = $wgUser->getSkin();
		
		$talkpageTitle = $this->article->getTitle();
		$talkpageLink = $sk->link( $talkpageTitle );

		$this->output->setPageTitle( wfMsg( 'lqt-talkpage-history-title' ) );
		$this->output->setSubtitle(
			wfMsgExt( 'lqt-talkpage-history-subtitle',
					array( 'replaceafter', 'parseinline' ),
					$talkpageLink )
		);
		
		$pager = new TalkpageHistoryPager( $this, $this->article );
		
		$html = $pager->getNavigationBar() .
				$pager->getBody() .
				$pager->getNavigationBar();
				
		$this->output->addHTML( $html );
		
		return false;
	}
}

class TalkpageHistoryPager extends ThreadHistoryPager {
	function __construct( $view, $talkpage ) {
		$this->talkpage = $talkpage;
		
		parent::__construct( $view, null );
	}
	
	function getFieldMessages() {
		$headers = array(
			'th_timestamp' => 'lqt-history-time',
			'thread_subject' => 'lqt-history-thread',
			'th_user_text' => 'lqt-history-user',
			'th_change_type' => 'lqt-history-action',
			'th_change_comment' => 'lqt-history-comment',
			);
		
		return $headers;
	}
	
	function getQueryInfo() {
		$queryInfo =
			array(
				'tables' => array( 'thread_history', 'thread', 'page' ),
				'fields' => '*',
				'conds' => Threads::articleClause( $this->talkpage ),
				'options' => array( 'order by' => 'th_timestamp desc' ),
				'join_conds' => array(
					'thread' => array( 'LEFT JOIN', 'thread_id=th_thread' ),
					'page' => array( 'LEFT JOIN', 'thread_root=page_id' ),
				),
			);
			
		return $queryInfo;
	}
	
	function formatValue( $name, $value ) {
		global $wgOut, $wgLang, $wgTitle;

		static $sk = null;

		if ( empty( $sk ) ) {
			global $wgUser;
			$sk = $wgUser->getSkin();
		}

		$row = $this->mCurrentRow;
		
		switch( $name ) {
			case 'thread_subject':
				$title = Title::makeTitleSafe( $row->page_namespace,
					$row->page_title );
				
				$link = $sk->link( $title, $value, array(), array(),
						array( 'known' ) );
				
				return $link;
			default:
				return parent::formatValue( $name, $value );
		}
	}
}
