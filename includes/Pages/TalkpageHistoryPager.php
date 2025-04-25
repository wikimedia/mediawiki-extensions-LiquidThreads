<?php

use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\Article;
use MediaWiki\Title\Title;

class TalkpageHistoryPager extends ThreadHistoryPager {

	/** @var Article */
	protected $talkpage;

	public function __construct( LqtView $view, $talkpage ) {
		$this->talkpage = $talkpage;

		parent::__construct( $view, null );
	}

	public function getFieldMessages() {
		$headers = [
			'th_timestamp' => $this->msg( 'lqt-history-time' )->text(),
			'thread_subject' => $this->msg( 'lqt-history-thread' )->text(),
			'th_user_text' => $this->msg( 'lqt-history-user' )->text(),
			'th_change_type' => $this->msg( 'lqt-history-action' )->text(),
			'th_change_comment' => $this->msg( 'lqt-history-comment' )->text(),
		];

		return $headers;
	}

	public function getQueryInfo() {
		$queryInfo = [
			'tables' => [ 'thread_history', 'thread', 'page' ],
			'fields' => '*',
			'conds' => [ Threads::articleClause( $this->talkpage->getPage() ) ],
			'options' => [ 'order by' => 'th_timestamp desc' ],
			'join_conds' => [
				'thread' => [ 'LEFT JOIN', 'thread_id=th_thread' ],
				'page' => [ 'LEFT JOIN', 'thread_root=page_id' ],
			],
		];

		return $queryInfo;
	}

	public function formatValue( $name, $value ) {
		$this->getOutput()->setRobotPolicy( 'noindex, nofollow' );

		$row = $this->mCurrentRow;

		$ns = $row->page_namespace;
		$title = $row->page_title;

		if ( $ns === null ) {
			$ns = $row->thread_article_namespace;
			$title = $row->thread_article_title;
		}

		switch ( $name ) {
			case 'thread_subject':
				$title = Title::makeTitleSafe(
					$ns,
					$title
				);

				$link = $this->linkRenderer->makeKnownLink(
					$title,
					$value,
					[],
					[]
				);

				$contLang = MediaWikiServices::getInstance()->getContentLanguage();

				return Html::rawElement( 'div', [ 'dir' => $contLang->getDir() ], $link );
			case 'th_timestamp':
				$formatted = $this->getLanguage()->userTimeAndDate( $value, $this->getUser() );
				$title = Title::makeTitleSafe(
					$ns,
					$title
				);

				return $this->linkRenderer->makeLink(
					$title,
					$formatted,
					[],
					[ 'lqt_oldid' => $row->th_id ]
				);
			default:
				return parent::formatValue( $name, $value );
		}
	}
}
