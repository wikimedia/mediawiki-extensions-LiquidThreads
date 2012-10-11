<?php

class ThreadHistoryPager extends TablePager {
	static $change_names;

	function __construct( $view, $thread ) {
		parent::__construct();

		$this->thread = $thread;
		$this->view = $view;

		self::$change_names =
		array(
			Threads::CHANGE_EDITED_ROOT => wfMessage( 'lqt_hist_comment_edited' )->plain(),
			Threads::CHANGE_EDITED_SUMMARY => wfMessage( 'lqt_hist_summary_changed' )->plain(),
			Threads::CHANGE_REPLY_CREATED => wfMessage( 'lqt_hist_reply_created' )->plain(),
			Threads::CHANGE_NEW_THREAD => wfMessage( 'lqt_hist_thread_created' )->plain(),
			Threads::CHANGE_DELETED => wfMessage( 'lqt_hist_deleted' )->plain(),
			Threads::CHANGE_UNDELETED => wfMessage( 'lqt_hist_undeleted' )->plain(),
			Threads::CHANGE_MOVED_TALKPAGE => wfMessage( 'lqt_hist_moved_talkpage' )->plain(),
			Threads::CHANGE_EDITED_SUBJECT => wfMessage( 'lqt_hist_edited_subject' )->plain(),
			Threads::CHANGE_SPLIT => wfMessage( 'lqt_hist_split' )->plain(),
			Threads::CHANGE_MERGED_FROM => wfMessage( 'lqt_hist_merged_from' )->plain(),
			Threads::CHANGE_MERGED_TO => wfMessage( 'lqt_hist_merged_to' )->plain(),
			Threads::CHANGE_SPLIT_FROM => wfMessage( 'lqt_hist_split_from' )->plain(),
			Threads::CHANGE_ROOT_BLANKED => wfMessage( 'lqt_hist_root_blanked' )->plain(),
			Threads::CHANGE_ADJUSTED_SORTKEY => wfMessage( 'lqt_hist_adjusted_sortkey' )->plain()
		);
	}

	function getQueryInfo() {
		$queryInfo = array(
			'tables' => array( 'thread_history' ),
			'fields' => '*',
			'conds' => array( 'th_thread' => $this->thread->id() ),
			'options' => array( 'order by' => 'th_timestamp desc' ),
		);

		return $queryInfo;
	}

	function getFieldMessages() {
		$headers = array(
			'th_timestamp' => $this->msg( 'lqt-history-time' )->text(),
			'th_user_text' => $this->msg( 'lqt-history-user' )->text(),
			'th_change_type' => $this->msg( 'lqt-history-action' )->text(),
			'th_change_comment' => $this->msg( 'lqt-history-comment' )->text(),
		);

		return $headers;
	}

	function getFieldNames() {
		static $headers = null;

		if ( !empty( $headers ) ) {
			return $headers;
		}

		return $this->getFieldMessages();
	}

	function formatValue( $name, $value ) {
		global $wgLang, $wgTitle;

		$row = $this->mCurrentRow;

		switch( $name ) {
			case 'th_timestamp':
				$formatted = $wgLang->timeanddate( $value );
				return Linker::link(
					$wgTitle,
					$formatted,
					array(),
					array( 'lqt_oldid' => $row->th_id )
				);
			case 'th_user_text':
				return Linker::userLink(
						$row->th_user,
						$row->th_user_text
					) .
					' ' . Linker::userToolLinks( $row->th_user, $row->th_user_text );
			case 'th_change_type':
				return $this->getActionDescription( $value );
			case 'th_change_comment':
				return Linker::commentBlock( $value );
			default:
				return "Unable to format $name";
				break;
		}
	}

	function getActionDescription( $type ) {
		global $wgOut;

		$args = array();
		$revision = ThreadRevision::loadFromRow( $this->mCurrentRow );
		$changeObject = $revision->getChangeObject();

		if ( $revision && $revision->prev() ) {
			$lastChangeObject = $revision->prev()->getChangeObject();
		}

		if ( $changeObject && $changeObject->title() ) {
			$args[] = $changeObject->title()->getPrefixedText();
		} else {
			$args[] = '';
		}

		$msg = self::$change_names[$type];

		switch( $type ) {
			case Threads::CHANGE_EDITED_SUBJECT:
				if ( $changeObject && $lastChangeObject ) {
					$args[] = $lastChangeObject->subject();
					$args[] = $changeObject->subject();
				} else {
					$msg = wfMessage( 'lqt_hist_edited_subject_corrupt' )->parse();
				}
				break;
			case Threads::CHANGE_EDITED_ROOT:
			case Threads::CHANGE_ROOT_BLANKED:
				$view = $this->view;

				if ( $changeObject && $changeObject->title() ) {
					$diffLink = $view->diffPermalinkURL( $changeObject, $revision );
					$args[] = $diffLink;
				} else {
					$args[] = '';
				}
				break;
		}

		$content = wfMsgReplaceArgs( $msg, $args );
		return $wgOut->parseInline( $content );
	}

	function getIndexField() {
		return 'th_timestamp';
	}

	function getDefaultSort() {
		return 'th_timestamp';
	}

	function isFieldSortable( $name ) {
		$sortable_fields = array( 'th_timestamp', 'th_user_text', 'th_change_type' );
		return in_array( $name, $sortable_fields );
	}

	function getDefaultDirections() { return true; /* descending */ }
}
