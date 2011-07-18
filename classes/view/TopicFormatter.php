<?php

class LiquidThreadsTopicFormatter extends LiquidThreadsFormatter {

	/**
	 * Get a shared instance of this class.
	 */
	public static function singleton() {
		static $singleton = null;
		
		if ( !$singleton ) {
			$singleton = new self;
		}
		
		return $singleton;
	}

	/**
	 * Returns the correct class for the context object
	 */
	public function getContextClass() {
		return 'LiquidThreadsTopicFormatterContext';
	}
	
	/**
	 * Returns the class that can be formatted by this object.
	 */
	public function getObjectClass() {
		return 'LiquidThreadsTopic';
	}

	/**
	 * Convert a LiquidThreadsPost to HTML.
	 * @param $object The LiquidThreadsPost to convert.
	 * @param $context LiquidThreadsPostFormatterContext object. 
	 * @return HTML result
	 */
	public function getHTML( $object, $context = null ) {
		// Error checking
		$this->checkArguments( $object, $context );
		
		if ( ! $context->get('language') ) {
			global $wgLang;
			$context->set( 'language', $wgLang );
		}
		
		if ( ! $context->get('user') ) {
			global $wgUser;
			$context->set('user', $wgUser );
		}
	
		$html = Xml::openElement( 'div',
			array( 'class' => 'lqt-topic',
				'id' => LiquidThreadsFormatter::getAnchor($object) ) );
				
		// Show topic heading
		$html .= $this->getHeading( $object, $context );
		
		// Now show the comments
		
		// TODO Show only comments as they appeared at $timestamp
		$directResponses = $object->getDirectResponses();
		
		$postContext = $context->get('post-context');
		
		if ( is_null($postContext) ) {
			$postContext = new LiquidThreadsPostFormatterContext();
			
			$postContext->set( 'timestamp', $context->get('timestamp') );
			$postContext->set( 'user', $context->get('user') );
			$postContext->set( 'language', $context->get('language') );
			$postContext->set( 'parent-context', $context );
			$postContext->set( 'base-url', $context->get('base-url') );
		}
		
		// Set up formatter
		$postFormatter = LiquidThreadsPostFormatter::singleton();
		
		foreach( $directResponses as $reply ) {
			$html .= $postFormatter->getHTML( $reply, $postContext );
		}
		
		$html .= Xml::closeElement( 'div' );
		
		return $html;
	}
	
	/**
	 * Gets the heading and its included topic-level toolbar.
	 * @param $object The LiquidThreadsTopic for which to get a heading.
	 * @param $context The context object.
	 * @return HTML result.
	 */
	protected function getHeading( $object, $context ) {
		$version = $context->get('version');
		
		if ( !($version instanceof LiquidThreadsTopicVersion) ||
			$version->getTopicID() != $object->getID() )
		{
			$version = LiquidThreadsTopicVersion::newPointInTime( $object,
					$context->get('timestamp') );
		}
		
		$toolbar = LiquidThreadsTopicToolbar::singleton();
		$toolbarHTML = $toolbar->getHTML( $object, $context );
		
		$subject = $version->getSubject();
		
		$formattedSubject = $this->formatSubject( $subject );
		
		$formattedSubject = Xml::tags( 'span', array( 'class' => 'mw-headline' ),
					$formattedSubject );
					
		$formattedSubject = Xml::tags( 'h2',
					array(
						'class' => 'lqt-header',
						'id' => 'lqt-header-id-'.$object->getID(),
					), $formattedSubject );
		
		$html = $formattedSubject . "\n" . $toolbarHTML;
		
		$html = Xml::tags( 'div', array( 'class' => 'lqt-heading' ), $html );
		
		return $html;
	}
	
	/**
	 * Formats a topic subject.
	 * @param $subject String: The subject source to format.
	 * @return The subject, lightly formatted, as HTML.
	 */
	protected function formatSubject( $subject ) {
		wfProfileIn( __METHOD__ );
		global $wgUser;
		$sk = $wgUser->getSkin();

		# Sanitize text a bit:
		$subject = str_replace( "\n", " ", $subject );
		# Allow HTML entities
		$subject = Sanitizer::escapeHtmlAllowEntities( $subject );

		# Render links:
		$subject = $sk->formatLinksInComment( $subject, null, false );

		wfProfileOut( __METHOD__ );
		return $subject;
	}
	
}

/**
 * This class provides context for formatting a LiquidThreadsTopic object.
 * Valid fields are:
 *     timestamp: The point in time at which to show this LiquidThreadsTopic.
 *     user: The User object for which to show this LiquidThreadsTopic.
 *     language: Override the Language object that the topic is shown in.
 *     post-callbacks: If set, an associative array of callbacks used to replace display of
 *                      individual posts. Key is the post ID, value is a callback. Used for
 *                      edit forms and the like.
 */
class LiquidThreadsTopicFormatterContext extends LiquidThreadsFormatterContext {
	protected $validFields = array(
		'timestamp',
		'user',
		'language',
		'parent-context',
		'version',
		'action',
		'post-context',
		'base-url',
	);
}
