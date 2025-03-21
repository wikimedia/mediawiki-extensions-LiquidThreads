<?php

use MediaWiki\Xml\Xml;

class ThreadHistoricalRevisionView extends ThreadPermalinkView {
	/** @var ThreadRevision|null */
	public $mDisplayRevision = null;

	public function postDivClass( Thread $thread ) {
		$changedObject = $this->mDisplayRevision->getChangeObject();
		$is_changed_thread = $changedObject && ( $changedObject->id() == $thread->id() );

		$class = parent::postDivClass( $thread );

		if ( $is_changed_thread ) {
			return "$class lqt_post_changed_by_history";
		} else {
			return $class;
		}
	}

	public function getMessageForChangeType( $ct ) {
		static $messages = [
			Threads::CHANGE_NEW_THREAD => 'lqt_change_new_thread',
			Threads::CHANGE_REPLY_CREATED => 'lqt_change_reply_created',
			Threads::CHANGE_DELETED => 'lqt_change_deleted',
			Threads::CHANGE_UNDELETED => 'lqt_change_undeleted',
			Threads::CHANGE_MOVED_TALKPAGE => 'lqt_change_moved',
			Threads::CHANGE_SPLIT => 'lqt_change_split',
			Threads::CHANGE_EDITED_SUBJECT => 'lqt_change_edited_subject',
			Threads::CHANGE_MERGED_FROM => 'lqt_change_merged_from',
			Threads::CHANGE_MERGED_TO => 'lqt_change_merged_to',
			Threads::CHANGE_SPLIT_FROM => 'lqt_change_split_from',
			Threads::CHANGE_EDITED_SUMMARY => 'lqt_change_edited_summary',
			Threads::CHANGE_ROOT_BLANKED => 'lqt_change_root_blanked',
			Threads::CHANGE_EDITED_ROOT => 'lqt_change_edited_root',
		];

		if ( isset( $messages[$ct] ) ) {
			return $messages[$ct];
		}

		return '';
	}

	private function showHistoryInfo() {
		global $wgLang;

		$html = wfMessage(
			'lqt_revision_as_of',
			$wgLang->timeanddate( $this->mDisplayRevision->getTimestamp() ),
			$wgLang->date( $this->mDisplayRevision->getTimestamp() ),
			$wgLang->time( $this->mDisplayRevision->getTimestamp() )
		)->parse();

		$html .= '<br />';
		$html .= $this->getChangeDescription();

		$html = Xml::tags( 'div', [ 'class' => 'lqt_history_info' ], $html );

		$this->output->addHTML( $html );
	}

	private function getChangeDescription() {
		$args = [];

		$revision = $this->mDisplayRevision;
		$change_type = $revision->getChangeType();

		$post = $revision->getChangeObject();
		if ( $post ) {
			$args[] = LqtView::linkInContextFullURL( $post );
		} else {
			wfDebug( '[LQT] Unable to find a moved reply - change description is broken' );
			return '';
		}

		$msg = $this->getMessageForChangeType( $change_type );

		switch ( $change_type ) {
			case Threads::CHANGE_EDITED_SUBJECT:
				$args[] = $revision->prev()->getChangeObject()->subject();
				$args[] = $revision->getChangeObject()->subject();
				break;
		}

		$html = wfMessage( $msg, $args )->parse();

		if (
			( $change_type == Threads::CHANGE_ROOT_BLANKED || $change_type == Threads::CHANGE_EDITED_ROOT ) &&
			$post->root()
		) {
			$diff_link = self::diffPermalink(
				$post,
				wfMessage( 'diff' )->text(),
				$this->mDisplayRevision
			);

			// @todo FIXME: Hard coded brackets.
			$html .= " [$diff_link]";
		}

		return $html;
	}

	public function show() {
		if ( !$this->thread ) {
			$this->showMissingThreadPage();
			return false;
		}

		$oldid = $this->request->getInt( 'lqt_oldid' );
		$this->mDisplayRevision = ThreadRevision::loadFromId( $oldid );
		if ( !$this->mDisplayRevision ) {
			$this->showMissingThreadPage();
			return false;
		}

		$this->thread = $this->mDisplayRevision->getThreadObj();
		if ( !$this->thread ) {
			$this->output->addWikiMsg( 'lqt-historicalrevision-error' );
			return false;
		}

		$this->showHistoryInfo();

		$this->output->addModules( 'ext.liquidThreads' );
		$this->output->setSubtitle( $this->getSubtitle() );

		$changedObject = $this->mDisplayRevision->getChangeObject();

		$this->showThread(
			$this->thread,
			1,
			1,
			[
				'maxDepth' => -1,
				'maxCount' => -1,
				'mustShowThreads' => $changedObject ? [ $changedObject->id() ] : []
			]
		);

		$this->output->setPageTitle( $this->thread->subject() );
		return false;
	}
}
