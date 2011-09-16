<?php

class LiquidThreadsChannelView extends LQTView {
	protected $channel;
	protected $mShowItems = array( 'options', 'toc' );
	protected $baseUrl;
	
	/**
	 * Default constructor
	 * @param $channel The LiquidThreadsChannel to show.
	 */
	function __construct( $channel ) {
		$this->channel = $channel;
		
		// Assume we want to go to the current title.
		global $wgTitle, $wgRequest;
		$query = array();
		$copyQueryElements = array(
				'limit',
				'offset',
				'dir',
				'order',
				'sort',
				'asc',
				'desc'
			);
			
		foreach( $copyQueryElements as $name ) {
			if ( $wgRequest->getCheck('name') ) {
				$query[$name] = $wgRequest->getVal($name);
			}
		}
		
		$this->baseUrl = $wgTitle->getFullURL( $query );
	}
	
	/** 
	 * Shows this view.
	 * @param $action Any action to take
	 */
	function show( $action = null ) {
		// Temporarily disabled
// 		// Expose feed links.
// 		global $wgFeedClasses;
// 		$apiParams = array( 'action' => 'feedthreads', 'type' => 'replies|newthreads',
// 				'talkpage' => $this->title->getPrefixedText() );
// 		$urlPrefix = wfScript( 'api' ) . '?';
// 		foreach ( $wgFeedClasses as $format => $class ) {
// 			$theseParams = $apiParams + array( 'feedformat' => $format );
// 			$url = $urlPrefix . wfArrayToCGI( $theseParams );
// 			$this->output->addFeedLink( $format, $url );
// 		}

		$html = '';

		// Set up a per-page header for new threads, search box, and sorting stuff.

		$talkpageHeader = '';

		if ( /*Thread::canUserPost( $this->user, $this->article )*/ true ) {
			$newThreadText = wfMsgExt( 'lqt_new_thread', 'parseinline' );
			$query = array(
					'lqt_action' => 'new-topic',
					'lqt_target' => $this->channel->getUniqueIdentifier()
				);
				
			$newThreadUrl = wfAppendQuery( $this->getBaseUrl(), $query );
			
			$newThreadLink = Html::rawElement(
				'a',
				array(
					'lqt_channel' => $this->channel->getID(),
					'href' => $newThreadUrl,
				),
				$newThreadText
			);

			$newThreadLink = Xml::tags(
				'strong',
				array( 'class' => 'lqt_start_discussion' ),
				$newThreadLink
			);
			
			$talkpageHeader .= $newThreadLink;
		}

		$talkpageHeader = Xml::tags(
			'div',
			array( 'class' => 'lqt-talkpage-header' ),
			$talkpageHeader
		);

 		if ( $this->shouldShow('options') ) {
 			$html .= $talkpageHeader;
 		} elseif ( $this->shouldShow('simplenew') ) {
 			$html .= $newThreadLink;
 		}

 		if ( count($action) > 0 && $action[0] == 'new-topic' ) {
 			global $wgUser, $wgRequest;
 			$form = new LiquidThreadsNewTopicForm( $wgUser, $this->channel );
 			$formOutput = $form->show( $wgRequest );
 			
			if ( $formOutput !== true ) {
				$html .= $formOutput;
			} else {
				$this->refresh();
				return false;
			}
 		}

		$pager = $this->getPager();

		$topics = array();
		foreach( $pager->getRows() as $row ) {
			$topics[$row->lqt_id] = LiquidThreadsTopic::newFromRow( $row );
		}

		if ( count( $topics ) > 0 && $this->shouldShow('toc') ) {
			$html .= $this->getTOC( $topics );
		} elseif ( count($topics) == 0 ) {
			$html .= Xml::tags( 'div', array( 'class' => 'lqt-no-threads' ),
					wfMsgExt( 'lqt-no-threads', 'parseinline' ) );
		}

		$html .= $pager->getNavigationBar();
		$html .= Xml::openElement( 'div',
			array(
				'class' => 'lqt-threads lqt-talkpage-threads',
				'id' => $this->channel->getUniqueIdentifier(),
			) );

		$formatter = LiquidThreadsTopicFormatter::singleton();
		$context = new LiquidThreadsTopicFormatterContext;
		$doneReply = false;
		$replyPost = -1;
		$context->set( 'action', $action );
		$context->set( 'base-url', $this->getBaseUrl() );
		
		foreach ( $topics as $topic ) {
			$html .= $formatter->getHTML( $topic, $context );
		}
		
		$html .= Xml::closeElement( 'div' ) . $pager->getNavigationBar();
		
		global $wgOut;
		$wgOut->addModules( 'ext.liquidThreads' );
		$wgOut->addHTML( $html );

		return false;
	}
	
	function refresh() {
		global $wgOut;
		$wgOut->redirect( $this->channel->getTitle()->getFullURL() );
	}

	function getTOC( $topics ) {
		global $wgLang;

		$html = '';

		$h2_header = Xml::tags( 'h2', null, wfMsgExt( 'lqt_contents_title', 'parseinline' ) );

		// Header row
		$headerRow = '';
		$headers = array( 'lqt_toc_thread_title',
				'lqt_toc_thread_replycount', 'lqt_toc_thread_modified' );
		foreach ( $headers as $msg ) {
			$headerRow .= Xml::tags( 'th', null, wfMsgExt( $msg, 'parseinline' ) );
		}
		$headerRow = Xml::tags( 'tr', null, $headerRow );
		$headerRow = Xml::tags( 'thead', null, $headerRow );

		// Table body
		$rows = array();
		foreach ( $topics as $topic ) {
			$row = '';
			$anchor = '#' . LiquidThreadsFormatter::getAnchor($topic);
			$subject = Xml::element( 'a', array( 'href' => $anchor ),
					$topic->getSubject() );
			$row .= Xml::tags( 'td', null, $subject );

			$replyCount = $wgLang->formatNum( $topic->getPostCount() );
			$row .= Xml::element( 'td', null, $replyCount ); // TODO

//			$timestamp = 'timestamp';
			$timestamp = $wgLang->timeanddate( $topic->getTouchedTime(), true );
			$row .= Xml::element( 'td', null, $timestamp );

			$row = Xml::tags( 'tr', null, $row );
			$rows[] = $row;
		}

		$html .= $headerRow . "\n" . Xml::tags( 'tbody', null, implode( "\n", $rows ) );
		$html = $h2_header . Xml::tags( 'table', array( 'class' => 'lqt_toc' ), $html );
		// wrap our output in a div for containment
		$html = Xml::tags( 'div', array( 'class' => 'lqt-contents-wrapper' ), $html );
		
		return $html;
	}

	function getList( $kind, $class, $id, $contents ) {
		$html = '';
		foreach ( $contents as $li ) {
			$html .= Xml::tags( 'li', null, $li );
		}
		$html = Xml::tags( $kind, array( 'class' => $class, 'id' => $id ), $html );

		return $html;
	}

	function getPager() {
		return new LiquidThreadsChannelPager( $this->channel );
	}
	
	// Hide a number of items from the view
	// Valid values: toc, options, header
	function hideItems( $items ) {
		$this->mShowItems = array_diff( $this->mShowItems, (array)$items );
	}
	
	// Show a number of items in the view
	// Valid values: toc, options, header
	function showItems( $items ) {
		$this->mShowItems = array_merge( $this->mShowItems, (array)$items );
	}
	
	// Whether or not to show an item
	function shouldShow( $item ) {
		return in_array( $item, $this->mShowItems );
	}
	
	// Set the items shown
	function setShownItems( $items ) {
		$this->mShowItems = $items;
	}
	
	/**
	 * Gets the base URL to return to this specific page.
	 */
	public function getBaseURL() {
		return $this->baseUrl;
	}
	
	/**
	 * Sets the base URL to return to this specific page.
	 */
	public function setBaseURL( $baseUrl ) {
		$this->baseUrl = $baseUrl;
	}
}

class LiquidThreadsChannelPager extends IndexPager {
	function __construct( $channel ) {
		$this->channel = $channel;

		parent::__construct();

		$this->mLimit = $this->getPageLimit();
	}

	function getPageLimit() {
		global $wgRequest;
		$requestedLimit = $wgRequest->getVal( 'limit', null );
		if ( $requestedLimit ) {
			return $requestedLimit;
		}

		global $wgLiquidThreadsDefaultPageLimit;
		return $wgLiquidThreadsDefaultPageLimit;
	}

	function getQueryInfo() {
		$queryInfo = array(
			'tables' => array( 'lqt_topic', 'lqt_topic_version' ),
			'fields' => '*',
			'conds' => array(
				'lqt_channel' => $this->channel->getID(),
			),
			'join_conds' => array(
				'lqt_topic_version' => array( 'left join',
					array( 'lqt_current_version=ltv_id' ) ),
			),
		);

		return $queryInfo;
	}

	// Adapted from getBody().
	function getRows() {
		if ( !$this->mQueryDone ) {
			$this->doQuery();
		}

		# Don't use any extra rows returned by the query
		$numRows = min( $this->mResult->numRows(), $this->mLimit );

		$rows = array();

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
		return 'lqt_touched';
	}

	function getDefaultDirections() {
		return true; // Descending
	}

	/**
	 * A navigation bar with images
	 * Stolen from TablePager because it's pretty.
	 */
	function getNavigationBar() {
		global $wgStylePath, $wgContLang;

		if ( method_exists( $this, 'isNavigationBarShown' ) &&
				!$this->isNavigationBarShown() )
			return '';

		$path = "$wgStylePath/common/images";
		$labels = array(
			'first' => 'table_pager_first',
			'prev' => 'table_pager_prev',
			'next' => 'table_pager_next',
			'last' => 'table_pager_last',
		);
		$images = array(
			'first' => $wgContLang->isRTL() ? 'arrow_last_25.png' : 'arrow_first_25.png',
			'prev' =>  $wgContLang->isRTL() ? 'arrow_right_25.png' : 'arrow_left_25.png',
			'next' =>  $wgContLang->isRTL() ? 'arrow_left_25.png' : 'arrow_right_25.png',
			'last' =>  $wgContLang->isRTL() ? 'arrow_first_25.png' : 'arrow_last_25.png',
		);
		$disabledImages = array(
			'first' => $wgContLang->isRTL() ? 'arrow_disabled_last_25.png' : 'arrow_disabled_first_25.png',
			'prev' =>  $wgContLang->isRTL() ? 'arrow_disabled_right_25.png' : 'arrow_disabled_left_25.png',
			'next' =>  $wgContLang->isRTL() ? 'arrow_disabled_left_25.png' : 'arrow_disabled_right_25.png',
			'last' =>  $wgContLang->isRTL() ? 'arrow_disabled_first_25.png' : 'arrow_disabled_last_25.png',
		);

		$linkTexts = array();
		$disabledTexts = array();
		foreach ( $labels as $type => $label ) {
			$msgLabel = wfMsgHtml( $label );
			$linkTexts[$type] = "<img src=\"$path/{$images[$type]}\" alt=\"$msgLabel\"/><br />$msgLabel";
			$disabledTexts[$type] = "<img src=\"$path/{$disabledImages[$type]}\" alt=\"$msgLabel\"/><br />$msgLabel";
		}
		$links = $this->getPagingLinks( $linkTexts, $disabledTexts );

		$navClass = htmlspecialchars( $this->getNavClass() );
		$s = "<table class=\"$navClass\" align=\"center\" cellpadding=\"3\"><tr>\n";
		$cellAttrs = 'valign="top" align="center" width="' . 100 / count( $links ) . '%"';
		foreach ( $labels as $type => $label ) {
			$s .= "<td $cellAttrs>{$links[$type]}</td>\n";
		}
		$s .= "</tr></table>\n";
		return $s;
	}

	function getNavClass() {
		return 'TalkpagePager_nav';
	}
}
