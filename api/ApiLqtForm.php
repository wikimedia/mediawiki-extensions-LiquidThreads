<?php

class ApiLqtForm extends ApiBase {

	public function execute() {
		$params = $this->extractRequestParams();
		$result = $this->getResult();
		
		$form = $this->getForm( $params );
		
		global $wgUser;
		if ( $params['submit'] ) {
			if ( ! $wgUser->matchEditToken( $params['token'] ) ) {
				$this->dieUsage( "Invalid edit token", 'invalid-token' );
			}
			
			$requestParams = array();
			
			$requestParams['edittoken'] = $params['token'];
			$requestParams['lqt-subject'] = $params['subject'];
			$requestParams['lqt-edit-content'] = $params['content'];
			$requestParams['lqt-signature'] = $params['signature'];
			
			$request = new FauxRequest( $requestParams );
			
			if ( ! $form->validate( $request ) ) {
				$this->dieUsage( "Invalid parameters", 'invalid-param' );
			}
			
			$formResult = $form->submit( $request );
			
			if ( $formResult ) {
				$formOutput = array( 'submit' => 'success' );
				
				$result->addValue( null, 'form', $formOutput );
			}
		} else {
			$formOutput = array(
				'html' => $form->getFormHTML(),
				'token' => $wgUser->editToken(),
			);
			
			$result->addValue( null, 'form', $formOutput );
		}
	}
	
	/**
	 * Get an array of valid forms and their corresponding classes.
	 */
	public function getForms() {
		return array(
			'new' => 'LiquidThreadsNewTopicForm',
			'reply' => 'LiquidThreadsReplyForm',
		);
	}
	
	/**
	 * Creates the appropriate LiquidThreadsEditForm object
	 * @param $params Array: The parameters passed to the API module.
	 */
	public function getForm( $params ) {
		global $wgUser;
		
		$formName = $params['form'];
		
		if ( $formName == 'new' ) {
			if ( ! $params['channel'] ) {
				$this->dieUsage( 'You must specify a channel for the new form', 'missing-param' );
			}
			
			try {
				$channel = LiquidThreadsChannel::newFromID( $params['channel'] );
			} catch ( MWException $excep ) {
				$this->dieUsage( "You must specify a valid channel", 'invalid-param' );
			}
			
			return new LiquidThreadsNewTopicForm( $wgUser, $channel );
		} elseif ( $formName == 'reply' ) {
			if ( ! $params['topic'] ) {
				$this->dieUsage( 'You must specify a topic to reply to', 'missing-param' );
			}
			
			$replyPost = null;
			
			try {
				$topic = LiquidThreadsTopic::newFromID( $params['topic'] );
			} catch ( MWException $e ) {
				$this->dieUsage( "You must specify a valid topic", 'invalid-param' );
			}
			
			if ( $params['reply-post'] ) {
				try {
					$replyPost = LiquidThreadsPost::newFromID( $params['reply-post'] );
				} catch ( MWException $e ) {
					$this->dieUsage( "Invalid reply-post", 'invalid-param' );
				}
			}
			
			return new LiquidThreadsReplyForm( $wgUser, $topic, $replyPost );
		} else {
			$this->dieUsage( "Not yet implemented", 'not-implemented' );
		}
	}
	
	public function getAllowedParams() {
		return array(
			'form' => array(
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_TYPE => array_keys( $this->getForms() ),
			),
			
			// Parameters for new form
			'channel' => NULL,
			
			// Parameters for reply form
			'topic' => NULL,
			'reply-post' => NULL,
			
			// Submission parameters
			'submit' => array(
				ApiBase::PARAM_TYPE => 'boolean',
			),
			
			'subject' => NULL,
			'content' => NULL,
			'signature' => NULL,
			
			'token' => NULL,
		);
	}

	public function getVersion() {
		return __CLASS__ . ': $Id: ApiLqtForm.php 79941 2011-01-10 17:18:57Z hartman $';
	}	
}
