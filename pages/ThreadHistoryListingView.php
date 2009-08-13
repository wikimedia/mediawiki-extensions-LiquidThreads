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

class ThreadHistoryPager extends TablePager {
	static $change_names;
			

	function __construct( $view, $thread ) {
		parent::__construct();
		
		$this->thread = $thread;
		$this->view = $view;
		
		self::$change_names =
			array(
				Threads::CHANGE_EDITED_ROOT => wfMsgNoTrans( 'lqt_hist_comment_edited' ),
				Threads::CHANGE_EDITED_SUMMARY => wfMsgNoTrans( 'lqt_hist_summary_changed' ),
				Threads::CHANGE_REPLY_CREATED => wfMsgNoTrans( 'lqt_hist_reply_created' ),
				Threads::CHANGE_NEW_THREAD => wfMsgNoTrans( 'lqt_hist_thread_created' ),
				Threads::CHANGE_DELETED => wfMsgNoTrans( 'lqt_hist_deleted' ),
				Threads::CHANGE_UNDELETED => wfMsgNoTrans( 'lqt_hist_undeleted' ),
				Threads::CHANGE_MOVED_TALKPAGE => wfMsgNoTrans( 'lqt_hist_moved_talkpage' ),
				Threads::CHANGE_EDITED_SUBJECT => wfMsgNoTrans( 'lqt_hist_edited_subject' ),
				Threads::CHANGE_SPLIT => wfMsgNoTrans( 'lqt_hist_split' ),
			);
	}
	
	function getQueryInfo() {
		$queryInfo =
			array(
				'tables' => array( 'thread_history' ),
				'fields' => '*',
				'conds' => array( 'th_thread' => $this->thread->id() ),
				'options' => array( 'order by' => 'th_timestamp desc' ),
			);
			
		return $queryInfo;
	}
	
	function getFieldNames() {
		static $headers = null;

		if (!empty($headers)) {
			return $headers;
		}

		$headers = array( 
			'th_timestamp' => 'lqt-history-time', 
			'th_user_text' => 'lqt-history-user', 
			'th_change_type' => 'lqt-history-action',
			'th_change_comment' => 'lqt-history-comment', 
			);

		$headers = array_map( 'wfMsg', $headers );

		return $headers;
	}
	
	function formatValue( $name, $value ) {
		global $wgOut,$wgLang, $wgTitle;

		static $sk=null;

		if (empty($sk)) {
			global $wgUser;
			$sk = $wgUser->getSkin();
		}

		$row = $this->mCurrentRow;

		$formatted = '';

		switch($name) {
			case 'th_timestamp':
				$formatted = $wgLang->timeanddate( $value );
				return $sk->link( $wgTitle, $formatted, array(),
									array( 'lqt_oldid' => $row->th_id ) );
			case 'th_user_text':
				return $sk->userLink( $row->th_user, $row->th_user_text ) . ' ' .
						$sk->userToolLinks( $row->th_user, $row->th_user_text );
			case 'th_change_type':
				return self::$change_names[$value];
			case 'th_change_comment':
				return $sk->commentBlock( $value );
			default:
				return "Unable to format $name";
				break;
		}
	}
	
	function getIndexField() {
		return 'th_timestamp';
	}
	
	function getDefaultSort() {
		return 'th_timestamp';
	}

	function isFieldSortable($name) {
		$sortable_fields = array( 'th_timestamp', 'th_user_text', 'th_change_type' );
		return in_array( $name, $sortable_fields );
	}
	
	function getDefaultDirections() { return true; /* descending */ }
}

