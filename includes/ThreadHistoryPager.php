<?php

use MediaWiki\MediaWikiServices;

class ThreadHistoryPager extends TablePager {
	/** @var string[] */
	public static $change_names;

	/** @var Thread|null */
	protected $thread;

	/** @var LqtView */
	protected $view;

	/** @var \MediaWiki\Linker\LinkRenderer */
	protected $linkRenderer;

	public function __construct( LqtView $view, ?Thread $thread ) {
		parent::__construct();

		$this->thread = $thread;
		$this->view = $view;
		$this->linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();

		self::$change_names =
		[
			Threads::CHANGE_EDITED_ROOT => $this->msg( 'lqt_hist_comment_edited' )->plain(),
			Threads::CHANGE_EDITED_SUMMARY => $this->msg( 'lqt_hist_summary_changed' )->plain(),
			Threads::CHANGE_REPLY_CREATED => $this->msg( 'lqt_hist_reply_created' )->plain(),
			Threads::CHANGE_NEW_THREAD => $this->msg( 'lqt_hist_thread_created' )->plain(),
			Threads::CHANGE_DELETED => $this->msg( 'lqt_hist_deleted' )->plain(),
			Threads::CHANGE_UNDELETED => $this->msg( 'lqt_hist_undeleted' )->plain(),
			Threads::CHANGE_MOVED_TALKPAGE => $this->msg( 'lqt_hist_moved_talkpage' )->plain(),
			Threads::CHANGE_EDITED_SUBJECT => $this->msg( 'lqt_hist_edited_subject' )->plain(),
			Threads::CHANGE_SPLIT => $this->msg( 'lqt_hist_split' )->plain(),
			Threads::CHANGE_MERGED_FROM => $this->msg( 'lqt_hist_merged_from' )->plain(),
			Threads::CHANGE_MERGED_TO => $this->msg( 'lqt_hist_merged_to' )->plain(),
			Threads::CHANGE_SPLIT_FROM => $this->msg( 'lqt_hist_split_from' )->plain(),
			Threads::CHANGE_ROOT_BLANKED => $this->msg( 'lqt_hist_root_blanked' )->plain(),
			Threads::CHANGE_ADJUSTED_SORTKEY => $this->msg( 'lqt_hist_adjusted_sortkey' )->plain()
		];
	}

	public function getQueryInfo() {
		$queryInfo = [
			'tables' => [ 'thread_history' ],
			'fields' => '*',
			'conds' => [ 'th_thread' => $this->thread->id() ],
			'options' => [ 'order by' => 'th_timestamp desc' ],
		];

		return $queryInfo;
	}

	public function getFieldMessages() {
		$headers = [
			'th_timestamp' => $this->msg( 'lqt-history-time' )->text(),
			'th_user_text' => $this->msg( 'lqt-history-user' )->text(),
			'th_change_type' => $this->msg( 'lqt-history-action' )->text(),
			'th_change_comment' => $this->msg( 'lqt-history-comment' )->text(),
		];

		return $headers;
	}

	public function getFieldNames() {
		static $headers = null;

		if ( !empty( $headers ) ) {
			return $headers;
		}

		return $this->getFieldMessages();
	}

	public function formatValue( $name, $value ) {
		$row = $this->mCurrentRow;

		switch ( $name ) {
			case 'th_timestamp':
				$formatted = $this->getLanguage()->userTimeAndDate( $value, $this->getUser() );
				return $this->linkRenderer->makeLink(
					$this->getTitle(),
					$formatted,
					[],
					[ 'lqt_oldid' => $row->th_id ]
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
				return MediaWikiServices::getInstance()->getCommentFormatter()->formatBlock( $value );
			default:
				return "Unable to format $name";
		}
	}

	public function getActionDescription( $type ) {
		$args = [];
		$revision = ThreadRevision::loadFromRow( $this->mCurrentRow );
		$changeObject = $revision->getChangeObject();

		if ( $revision && $revision->prev() ) {
			$lastChangeObject = $revision->prev()->getChangeObject();
		} else {
			$lastChangeObject = null;
		}

		if ( $changeObject && $changeObject->title() ) {
			$args[] = $changeObject->title()->getPrefixedText();
		} else {
			$args[] = '';
		}

		$msg = self::$change_names[$type];

		switch ( $type ) {
			case Threads::CHANGE_EDITED_SUBJECT:
				if ( $changeObject && $lastChangeObject ) {
					$args[] = $lastChangeObject->subject();
					$args[] = $changeObject->subject();
				} else {
					$msg = $this->msg( 'lqt_hist_edited_subject_corrupt' )->parse();
				}
				break;
			case Threads::CHANGE_EDITED_ROOT:
			case Threads::CHANGE_ROOT_BLANKED:
				$view = $this->view;

				if ( $changeObject && $revision && $changeObject->title() ) {
					$diffLink = $view->diffPermalinkURL( $changeObject, $revision );
					$args[] = $diffLink;
				} else {
					$args[] = '';
					if ( $type == Threads::CHANGE_EDITED_ROOT ) {
						$msg = $this->msg( 'lqt_hist_comment_edited_deleted' )->parse();
					}
				}
				break;
			case Threads::CHANGE_REPLY_CREATED:
				if ( !$changeObject || !$changeObject->title() ) {
					$msg = $this->msg( 'lqt_hist_reply_created_deleted' )->parse();
				}
				break;
		}

		$content = wfMsgReplaceArgs( $msg, $args );
		return Html::rawElement(
			'span', [ 'class' => 'plainlinks' ], $this->getOutput()->parseInlineAsInterface( $content )
		);
	}

	public function getIndexField() {
		return 'th_timestamp';
	}

	public function getDefaultSort() {
		return 'th_timestamp';
	}

	public function isFieldSortable( $name ) {
		$sortable_fields = [ 'th_timestamp', 'th_user_text', 'th_change_type' ];
		return in_array( $name, $sortable_fields );
	}

	public function getDefaultDirections() {
		return true; /* descending */
	}
}
