<?php

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
			if ( method_exists( $pout, 'getPageProperty' ) ) {
				$setLimit = $pout->getPageProperty( 'lqt-page-limit' );
			} else {
				$setLimit = $pout->getProperty( 'lqt-page-limit' );
			}

			if ( $setLimit ) {
				return $setLimit;
			}
		}

		global $wgLiquidThreadsDefaultPageLimit;
		return $wgLiquidThreadsDefaultPageLimit;
	}

	public function getQueryInfo() {
		$queryInfo = [
			'tables' => [ 'thread' ],
			'fields' => '*',
			'conds' => [
				Threads::articleClause( $this->article->getPage() ),
				Threads::topLevelClause(),
				'thread_type != ' . $this->mDb->addQuotes( Threads::TYPE_DELETED ),
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
				throw new Exception( "Unknown sort order " . $this->orderType );
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
				throw new Exception( "Unknown sort order " . $this->orderType );
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
		global $wgExtensionAssetsPath;

		$path = "$wgExtensionAssetsPath/LiquidThreads/images";
		$labels = [
			'first' => 'table_pager_first',
			'prev' => 'table_pager_prev',
			'next' => 'table_pager_next',
			'last' => 'table_pager_last',
		];
		$images = [
			'first' => 'arrow_first_25.png',
			'prev' => 'arrow_left_25.png',
			'next' => 'arrow_right_25.png',
			'last' => 'arrow_last_25.png',
		];
		$disabledImages = [
			'first' => 'arrow_disabled_first_25.png',
			'prev' => 'arrow_disabled_left_25.png',
			'next' => 'arrow_disabled_right_25.png',
			'last' => 'arrow_disabled_last_25.png',
		];
		if ( $this->getLanguage()->isRTL() ) {
			$keys = array_keys( $labels );
			$images = array_combine( $keys, array_reverse( $images ) );
			$disabledImages = array_combine( $keys, array_reverse( $disabledImages ) );
		}

		$linkTexts = [];
		$disabledTexts = [];
		foreach ( $labels as $type => $label ) {
			$msgLabel = $this->msg( $label )->escaped();
			$linkTexts[$type] = "<img src=\"$path/{$images[$type]}\" " .
				"alt=\"$msgLabel\"/><br />$msgLabel";
			$disabledTexts[$type] = "<img src=\"$path/{$disabledImages[$type]}\" " .
				"alt=\"$msgLabel\"/><br />$msgLabel";
		}
		$links = $this->getPagingLinks( $linkTexts, $disabledTexts );

		$navClass = htmlspecialchars( $this->getNavClass() );
		$s = "<table class=\"$navClass\"><tr>\n";
		$cellAttrs = 'width: ' . 100 / count( $links ) . '%';
		foreach ( $labels as $type => $label ) {
			$s .= "<td style='$cellAttrs'>{$links[$type]}</td>\n";
		}
		$s .= "</tr></table>\n";
		return $s;
	}

	public function getNavClass() {
		return 'TalkpagePager_nav';
	}
}
