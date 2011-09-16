<?php

class ApiLqtFormatter extends ApiBase {

	public function execute() {
		$params = $this->extractRequestParams();
		$result = $this->getResult();
		
		$formatter = $this->getFormatter( $params );
		
		$ctxClass = $formatter->getContextClass();
		$context = new $ctxClass;
		
		$contextParams = array(
			'base-url',
			'nesting-level',
			'single',
			'timestamp',
		);
		
		foreach( $contextParams as $param ) {
			if ( $params[$param] !== null ) {
				$context->set($param, $params[$param]);
			}
		}
		
		$object = LiquidThreadsObject::retrieve( $params['object'] );
		
		$output = array(
			'html' => $formatter->getHTML($object, $context),
		);
		
		$result->addValue( null, 'formatter', $output );
	}
	
	/**
	 * Get an array of valid forms and their corresponding classes.
	 */
	public function getFormatters() {
		return array(
			'post' => 'LiquidThreadsPostFormatter',
			'topic' => 'LiquidThreadsTopicFormatter',
		);
	}
	
	/**
	 * Creates the appropriate LiquidThreadsEditForm object
	 * @param $params Array: The parameters passed to the API module.
	 */
	public function getFormatter( $params ) {
		global $wgUser;
		
		$formName = $params['formatter'];
		
		if ( $formName == 'post' ) {
			$formatter = LiquidThreadsPostFormatter::singleton();
		} elseif ( $formName == 'topic' ) {
			$formatter = LiquidThreadsTopicFormatter::singleton();
		} else {
			$this->dieUsage( "Not yet implemented", 'not-implemented' );
		}
		
		return $formatter;
	}
	
	public function getAllowedParams() {
		return array(
			'formatter' => array(
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_TYPE => array_keys( $this->getFormatters() ),
			),
			
			## Params for post
			'base-url' => null,
			'nesting-level' => null,
			'single' => null,
			
			## Shared params
			'object' => array(
				ApiBase::PARAM_REQUIRED => true,
			),
			'timestamp' => null,
		);
	}

	public function getVersion() {
		return __CLASS__ . ': $Id$';
	}	
}
