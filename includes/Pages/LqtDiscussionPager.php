<?php

use MediaWiki\Page\Article;
use MediaWiki\Pager\IndexPager;

class LqtDiscussionPager extends IndexPager {

	/** @var Article|false */
	protected $article;

	/** @var string|false */
	protected $orderType;

	public function __construct( $article, $orderType ) {
		$this->article = $article;
		$this->orderType = $orderType;

		parent::__construct();

		$this->setLimit( min( 50, $this->getPageLimit() ) );
	}

	public function getPageLimit() {
		$article = $this->article;

		$requestedLimit = $this->getRequest()->getIntOrNull( 'limit' );
		if ( $requestedLimit ) {
			return $requestedLimit;
		}

		if ( $article->getPage()->exists() ) {
			$pout = $article->getParserOutput();
			$setLimit = $pout->getPageProperty( 'lqt-page-limit' );

			if ( $setLimit ) {
				return $setLimit;
			}
		}

		return $this->getConfig()->get( 'LiquidThreadsDefaultPageLimit' );
	}

	public function getQueryInfo() {
		$queryInfo = [
			'tables' => [ 'thread' ],
			'fields' => '*',
			'conds' => [
				Threads::articleClause( $this->article->getPage() ),
				Threads::topLevelClause(),
				$this->mDb->expr( 'thread_type', '!=', Threads::TYPE_DELETED ),
			],
		];

		return $queryInfo;
	}

	public function getRows() {
		if ( !$this->mQueryDone ) {
			$this->doQuery();
		}

		# Don't use any extra rows returned by the query
		$numRows = min( $this->mResult->numRows(), $this->mLimit );

		$rows = [];

		if ( $numRows ) {
			if ( $this->mIsBackwards ) {
				for ( $i = $numRows - 1; $i >= 0; $i-- ) {
					$this->mResult->seek( $i );
					$row = $this->mResult->fetchObject();
					$rows[] = $row;
				}
			} else {
				$this->mResult->seek( 0 );
				for ( $i = 0; $i < $numRows; $i++ ) {
					$row = $this->mResult->fetchObject();
					$rows[] = $row;
				}
			}
		}

		return $rows;
	}

	public function formatRow( $row ) {
		// No-op, we get the list of rows from getRows()
		// Return a string to make the function signature happy
		return '';
	}

	public function getIndexField() {
		switch ( $this->orderType ) {
			case TalkpageView::LQT_NEWEST_CHANGES:
				return 'thread_sortkey';
			case TalkpageView::LQT_OLDEST_THREADS:
			case TalkpageView::LQT_NEWEST_THREADS:
				return 'thread_created';
			default:
				throw new LogicException( "Unknown sort order " . $this->orderType );
		}
	}

	public function getDefaultDirections() {
		switch ( $this->orderType ) {
			case TalkpageView::LQT_NEWEST_CHANGES:
			case TalkpageView::LQT_NEWEST_THREADS:
				return true; // Descending
			case TalkpageView::LQT_OLDEST_THREADS:
				return false; // Ascending
			default:
				throw new LogicException( "Unknown sort order " . $this->orderType );
		}
	}

	/**
	 * A navigation bar with images
	 * Stolen from TablePager because it's pretty.
	 * @return string
	 */
	public function getNavigationBar() {
		if ( !$this->isNavigationBarShown() ) {
			return '';
		}

		$this->getOutput()->enableOOUI();

		$types = [ 'first', 'prev', 'next', 'last' ];

		$queries = $this->getPagingQueries();

		$buttons = [];

		$title = $this->getTitle();

		foreach ( $types as $type ) {
			$buttons[] = new \OOUI\ButtonWidget( [
				// Messages used here:
				// * table_pager_first
				// * table_pager_prev
				// * table_pager_next
				// * table_pager_last
				'classes' => [ 'TablePager-button-' . $type ],
				'flags' => [ 'progressive' ],
				'framed' => false,
				'label' => $this->msg( 'table_pager_' . $type )->text(),
				'href' => $queries[ $type ] ?
					$title->getLinkURL( $queries[ $type ] + $this->getDefaultQuery() ) :
					null,
				'icon' => $type === 'prev' ? 'previous' : $type,
				'disabled' => $queries[ $type ] === false
			] );
		}
		return new \OOUI\ButtonGroupWidget( [
			'classes' => [ $this->getNavClass() ],
			'items' => $buttons,
		] );
	}

	/**
	 * @inheritDoc
	 */
	public function getModuleStyles() {
		return array_merge(
			parent::getModuleStyles(), [ 'oojs-ui.styles.icons-movement' ]
		);
	}

	public function getNavClass() {
		return 'TablePager_nav';
	}
}
