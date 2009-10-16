<?php

class ApiThreadAction extends ApiBase {
	
	public function getDescription() {
		return 'Allows actions to be taken on threads and posts in threaded discussions.';
	}
	
	public function getActions() {
		return array(
			'markread' => 'actionMarkRead',
			'markunread' => 'actionMarkUnread',
//			'reply', // Not implemented
//			'newtopic', // Not implemented
		);
	}
	
	protected function getParamDescription() {
		return array(
			'thread' => 'A list (pipe-separated) of thread IDs or titles to act on',
			'threadaction' => 'The action to take',
			'token' => 'An edit token (from ?action=query&prop=info&intoken=edit)',
			'talkpage' => 'The talkpage to act on (if applicable)',
		);
	}
	
	public function getExamples() {
		return array(
		
		);
	}
	
	public function getAllowedParams() {
		return array(
			'thread' => array(
					ApiBase::PARAM_ISMULTI => true,
				),
			'talkpage' => null,
			'threadaction' => array(
					ApiBase::PARAM_TYPE => array_keys( $this->getActions() ),
				),
			'token' => null,
		);
	}
	
	public function mustBePosted() { /*return true;*/ }

	public function isWriteMode() {
		return true;
	}
	
	public function execute() {
		$params = $this->extractRequestParams();
		
		global $wgUser;
		
		if ( empty( $params['token'] ) ||
				!$wgUser->matchEditToken( $params['token'] ) ) {
			$this->dieUsage( 'sessionfailure' );
			return;
		}
		
		if ( empty( $params['threadaction'] ) ) {
			$this->dieUsage( 'missing-param', 'action' );
			return;
		}
		
		// Pull the threads from the parameters
		$threads = array();
		foreach( $params['thread'] as $thread ) {
			if ( is_numeric( $thread ) ) {
				$threads[] = Threads::withId( $thread );
			} else {
				$title = Title::newFromText( $thread );
				$article = new Article( $title );
				$threads[] = Threads::withRoot( $article );
			}
		}
		
		// Find the appropriate module
		$action = $params['threadaction'];
		$actions = $this->getActions();
		
		$method = $actions[$action];
		
		call_user_func_array( array( $this, $method ), array( $threads, $params ) );
	}
	
	public function actionMarkRead( $threads, $params ) {
		global $wgUser;
		
		foreach( $threads as $t ) {
			NewMessages::markThreadAsReadByUser( $t, $wgUser );
		}
		
		$result = array( 'result' => 'Success', 'action' => 'markread' );
		
		$this->getResult()->addValue( null, 'threadaction', $result );
	}
	
	public function actionMarkUnread( $threads, $params ) {
		global $wgUser;
		
		foreach( $threads as $t ) {
			NewMessages::markThreadAsUnreadByUser( $t, $wgUser );
		}
		
		$result = array( 'result' => 'Success', 'action' => 'markunread' );
		
		$this->getResult()->addValue( null, 'threadaction', $result );
	}
	
	public function getVersion() {
		return __CLASS__ . ': $Id: $';
	}
}
