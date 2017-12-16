<?php

class LqtDiscussionPager extends IndexPager {
	function __construct( $article, $orderType ) {
		$this->article = $article;
		$this->orderType = $orderType;

		parent::__construct();

		$this->setLimit( min( 50, $this->getPageLimit() ) );
	}

	function getPageLimit() {
		$article = $this->article;

		global $wgRequest;
		$requestedLimit = $wgRequest->getInt( 'limit', null );
		if ( $requestedLimit ) {
			return $requestedLimit;
		}

		if ( $article->exists() ) {
			$pout = $article->getParserOutput();
			$setLimit = $pout->getProperty( 'lqt-page-limit' );
			if ( $setLimit ) {
				return $setLimit;
			}
		}

		global $wgLiquidThreadsDefaultPageLimit;
		return $wgLiquidThreadsDefaultPageLimit;
	}

	function getQueryInfo() {
		$queryInfo = [
			'tables' => [ 'thread' ],
			'fields' => '*',
			'conds' => [
				Threads::articleClause( $this->article ),
				Threads::topLevelClause(),
				'thread_type != ' . $this->mDb->addQuotes( Threads::TYPE_DELETED ),
			],
		];

		return $queryInfo;
	}

	// Adapted from getBody().
	function getRows() {
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

	function formatRow( $row ) {
		// No-op, we get the list of rows from getRows()
	}

	function getIndexField() {
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

	function getDefaultDirections() {
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
	function getNavigationBar() {
		if ( method_exists( $this, 'isNavigationBarShown' ) &&
				!$this->isNavigationBarShown() ) {
			return '';
		}
		global $wgExtensionAssetsPath, $wgLang;

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
		if ( $wgLang->isRTL() ) {
			$keys = array_keys( $labels );
			$images = array_combine( $keys, array_reverse( $images ) );
			$disabledImages = array_combine( $keys, array_reverse( $disabledImages ) );
		}

		$linkTexts = [];
		$disabledTexts = [];
		foreach ( $labels as $type => $label ) {
			$msgLabel = wfMessage( $label )->escaped();
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

	function getNavClass() {
		return 'TalkpagePager_nav';
	}
}
