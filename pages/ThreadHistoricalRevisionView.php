<?php

if ( !defined( 'MEDIAWIKI' ) ) die;

class ThreadHistoricalRevisionView extends ThreadPermalinkView {

	/* TOOD: customize tabs so that History is highlighted. */

	function postDivClass( $thread ) {
		$is_changed_thread = $thread->changeObject() &&
								( $thread->changeObject()->id() == $thread->id() );
		if ( $is_changed_thread ) {
			return 'lqt_post_changed_by_history';
		} else {
			return 'lqt_post';
		}
	}

	function showHistoryInfo() {
		global $wgLang;
		wfLoadExtensionMessages( 'LiquidThreads' );

		$html = '';
		$html .= wfMsgExt( 'lqt_revision_as_of', 'parseinline',
							array(
								$wgLang->timeanddate( $this->thread->modified() ),
								$wgLang->date( $this->thread->modified() ),
								$wgLang->time( $this->thread->modified() )
							)
						);
		
		$html .= '<br/>';

		$ct = $this->thread->changeType();
		if ( $ct == Threads::CHANGE_NEW_THREAD ) {
			$msg = wfMsgExt( 'lqt_change_new_thread', 'parseinline' );
		} else if ( $ct == Threads::CHANGE_REPLY_CREATED ) {
			$msg = wfMsgExt( 'lqt_change_reply_created', 'parseinline' );
		} else if ( $ct == Threads::CHANGE_EDITED_ROOT ) {
			$diff_link = $this->diffPermalink( $this->thread,
												wfMsgExt( 'diff', 'parseinline' ) );
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
		$this->showHistoryInfo();
		parent::show();
		return false;
	}
}
