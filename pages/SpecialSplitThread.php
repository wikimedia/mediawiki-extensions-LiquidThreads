<?php

// TODO access control
class SpecialSplitThread extends ThreadActionPage {

	function getFormFields() {
		$splitForm = array(
			'subject' =>
				array(
					'type' => 'text',
					'label-message' => 'lqt-thread-split-subject',
				),
			'reason' =>
				array(
					'label-message' => 'movereason',
					'type' => 'text',
				),
		);
		
		return $splitForm;
	}

	/**
	* @see SpecialPage::getDescription
	*/
	function getDescription() {
		wfLoadExtensionMessages( 'LiquidThreads' );
		return wfMsg( 'lqt_split_thread' );
	}
	
	function trySubmit( $data ) {
		// Load data
		$newSubject = $data['subject'];
		$reason = $data['reason'];
			
		$this->recursiveSet( $this->mThread, $newSubject, $this->mThread, $reason, 'first' );
		
		$title = clone $this->mThread->article()->getTitle();
		$title->setFragment( '#'.$this->mThread->getAnchorName() );
		
		$link = $this->user->getSkin()->link( $title, $this->mThread->subject() );
		
		global $wgOut;
		$wgOut->addHTML( wfMsgExt( 'lqt-split-success', array( 'parseinline', 'replaceafter' ),
							 $link ) );
		
		return true;
	}
	
	function recursiveSet( $thread, $subject, $ancestor, $reason, $first = false ) {
		$thread->setSubject( $subject );
		$thread->setAncestor( $ancestor->id() );
		
		if ($first) {
			$thread->setSuperThread( null );
		}
		
		$thread->commitRevision( Threads::CHANGE_SPLIT, null, $reason );
		
		foreach( $thread->replies() as $subThread ) {
			$this->recursiveSet( $subThread, $subject, $ancestor, $reason );
		}
	}
	
	function validateSubject( $target ) {
		if (!$target) {
			return wfMsgExt( 'lqt_split_nosubject', 'parseinline' );
		}
			
		$title = Title::newFromText( $target );
		
		if ( !$title ) {
			return wfMsgExt( 'lqt_split_badsubject', 'parseinline' );
		}
			
		return true;
	}
	
	function getPageName() {
		return 'SplitThread';
	}
	
	function getSubmitText() {
		wfLoadExtensionMessages( 'LiquidThreads' );
		return wfMsg( 'lqt-split-submit' );
	}
}
