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
			$requestParams['lqt-summary'] = $params['summary'];
			
			$request = new FauxRequest( $requestParams );
			
			if ( ! $form->validate( $request ) ) {
				$this->dieUsage( "Invalid parameters", 'invalid-param' );
			}
			
			$formResult = $form->submit( $request );
			
			if ( $formResult ) {
				$formOutput = array( 'submit' => 'success' );
				
				$object = $form->getModifiedObject();
				$formOutput['object'] = $object->getUniqueIdentifier();
				
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
			'edit' => 'LiquidThreadsPostEditForm',
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
				$id = $params['channel'];
				$channel = self::getObject( $id, 'LiquidThreadsChannel' );
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
				$topicID = $params['topic'];
				$topic = self::getObject( $topicID, 'LiquidThreadsTopic' );
			} catch ( MWException $e ) {
				$this->dieUsage( "You must specify a valid topic", 'invalid-param' );
			}
			
			if ( $params['reply-post'] ) {
				$replyPostID = $params['reply-post'];
				try {
					$replyPost = self::getObject( $replyPostID, 'LiquidThreadsPost' );
				} catch ( MWException $e ) {
					$this->dieUsage( "Invalid reply-post", 'invalid-param' );
				}
			}
			
			return new LiquidThreadsReplyForm( $wgUser, $topic, $replyPost );
		} elseif ( $formName == 'edit' ) {
			if ( ! $params['post'] ) {
				$this->dieUsage( 'You must specify a post to edit' );
			}
			
			try {
				$post = self::getObject( $params['post'], 'LiquidThreadsPost' );
			} catch ( MWException $e ) {
				$this->dieUsage( "Invalid post", 'invalid-param' );
			}
			
			return new LiquidThreadsPostEditForm( $wgUser, $post );
		} else {
			$this->dieUsage( "Not yet implemented", 'not-implemented' );
		}
	}
	
	/**
	 * Retrieves an object by user-supplied ID
	 * @param $id The object ID, may be either an integer ID specific
	 * to the given class or a LiquidThreads unique identifier suitable for
	 * passing to LiquidThreadsObject::retrieve()
	 * @param $class The class of object to retrieve.
	 */
	protected static function getObject( $id, $class ) {
		if ( is_numeric($id) ) {
			$object = $class::newFromId( $id );
		} else {
			$object = LiquidThreadsObject::retrieve($id);
			
			if ( ! $object instanceof $class ) {
				throw new MWException( "$id does not represent a $class" );
			}
		}
		
		return $object;
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

			// Parameters for edit form
			'post' => NULL,
			
			// Submission parameters
			'submit' => array(
				ApiBase::PARAM_TYPE => 'boolean',
			),
			
			'subject' => NULL,
			'content' => NULL,
			'signature' => NULL,
			'summary' => NULL,
			
			'token' => NULL,
		);
	}

	public function getVersion() {
		return __CLASS__ . ': $Id$';
	}	
}
