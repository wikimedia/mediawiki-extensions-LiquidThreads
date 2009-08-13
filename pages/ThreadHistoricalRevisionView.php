<?php

if ( !defined( 'MEDIAWIKI' ) ) die;

class ThreadHistoricalRevisionView extends ThreadPermalinkView {

	public $mDisplayRevision = null;

	/* TOOD: customize tabs so that History is highlighted. */

	function postDivClass( $thread ) {
		$changedObject = $this->mDisplayRevision->getChangeObject();
		$is_changed_thread =  $changedObject &&
								( $changedObject->id() == $thread->id() );
		
		$class = parent::postDivClass( $thread );
		
		if ( $is_changed_thread ) {
			return "$class lqt_post_changed_by_history";
		} else {
			return $class;
		}
	}

	function showHistoryInfo() {
		global $wgLang;
		wfLoadExtensionMessages( 'LiquidThreads' );

		$html = '';
		$html .= wfMsgExt( 'lqt_revision_as_of', 'parseinline',
							array(
								$wgLang->timeanddate( $this->mDisplayRevision->getTimestamp() ),
								$wgLang->date( $this->mDisplayRevision->getTimestamp() ),
								$wgLang->time( $this->mDisplayRevision->getTimestamp() )
							)
						);
		
		$html .= '<br/>';

		$ct = $this->mDisplayRevision->getChangeType();
		if ( $ct == Threads::CHANGE_NEW_THREAD ) {
			$msg = wfMsgExt( 'lqt_change_new_thread', 'parseinline' );
		} else if ( $ct == Threads::CHANGE_REPLY_CREATED ) {
			$msg = wfMsgExt( 'lqt_change_reply_created', 'parseinline' );
		} else if ( $ct == Threads::CHANGE_EDITED_ROOT ) {
			$diff_link = $this->diffPermalink( $this->thread,
												wfMsgExt( 'diff', 'parseinline' ),
												$this->mDisplayRevision );
			$msg = wfMsgExt( 'lqt_change_edited_root', 'parseinline' ) .
					" [$diff_link]";
		}
		$html .=  $msg;
		
		$html = Xml::tags( 'div', array( 'class' => 'lqt_history_info' ), $html );
		
		$this->output->addHTML( $html );
	}

	function show() {
		if ( ! $this->thread ) {
			$this->showMissingThreadPage();
			return false;
		}
		
		$oldid = $this->request->getInt( 'lqt_oldid' );
		$this->mDisplayRevision = ThreadRevision::loadFromId( $oldid );

		$this->thread = $this->mDisplayRevision->getThreadObj();
		
		$this->showHistoryInfo();
		parent::show();
		return false;
	}
}
