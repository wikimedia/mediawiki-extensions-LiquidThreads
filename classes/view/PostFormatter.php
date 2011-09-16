<?php

class LiquidThreadsPostFormatter extends LiquidThreadsFormatter {

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
		return 'LiquidThreadsPostFormatterContext';
	}
	
	/**
	 * Returns the class that can be formatted by this object.
	 */
	public function getObjectClass() {
		return 'LiquidThreadsPost';
	}

	/**
	 * Convert a LiquidThreadsPost to HTML.
	 * @param $object The LiquidThreadsPost to convert.
	 * @param $context LiquidThreadsPostFormatterContext object. 
	 */
	public function getHTML( $object, $context = null )
	{
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
		
		if ( ! $context->get('nesting-level') ) {
			$context->set('nesting-level', 0);
		}
		
		if ( ! $context->get('base-url') ) {
			global $wgTitle;
			$context->set( 'base-url', $wgTitle->getFullURL() );
		}
		
		$timestamp = $context->get('timestamp');
		// NULL means current version
		$version = LiquidThreadsPostVersion::newPointInTime( $object, $timestamp );
		$context->set('version', $version);
		
		if ( $context->get('single') ) {
			return $this->formatSingleComment( $object, $version, $context );
		} else {
			return $this->formatCommentTree( $object, $version, $context );
		}
	}

	/**
	 * Get the HTML for a full tree of comments.
	 * @param $object The LiquidThreadsPost to show.
	 * @param $version The LiquidThreadsPostVersion at which to show $object
	 * @param $context The LiquidThreadsPostFormatterContext to use for context.
	 * @return String: HTML result.
	 */	
	protected function formatCommentTree( $object, $version, $context ) {
		$context->increment('nesting-level');
		
		$wrapperClasses = array('lqt-post-tree-wrapper');
		
		// FIXME shows children at all times, should only show appropriate children for the time.
		$children = $object->getReplies();
		
		if ( count($children) ) {
			$wrapperClasses[] = 'lqt-post-with-replies';
		} else {
			$wrapperClasses[] = 'lqt-post-without-replies';
		}
		
		$html = Xml::openElement( 'div',
			array(
				'class' => implode(' ', $wrapperClasses ),
				'id' => LiquidThreadsFormatter::getAnchor($object),
			) );
		
		$html .= $this->formatSingleComment( $object, $version, $context );
		
		$replyForm = false;
		
		$myAction = $context->getActionFor( $object );
		
		if ( count($children) || $myAction == 'reply' ) {
			$html .= Xml::openElement( 'div', array( 'class' => 'lqt-replies' ) );
			foreach( $children as $child ) {
				$childVersion = LiquidThreadsPostVersion::newPointInTime( $child, $context->get('timestamp') );
				$html .= $this->formatCommentTree( $child, $childVersion, $context );
			}
			
			if ( $myAction == 'reply' ) {
				$form = new LiquidThreadsReplyForm( $context->get('user'), $object->getTopic(), $object );
				$formResult = $form->show();
				
				if ( $formResult !== true ) {
					$html .= $formResult;
				} else {
					// TODO hack
					global $wgOut;
					$wgOut->redirect( $context->get('base-url') );
				}
			}
			
			$html .= Xml::closeElement( 'div' );
		}
		
		$html .= Xml::closeElement( 'div' );
		
		$context->decrement('nesting-level');
		
		return $html;
	}
	
	/**
	 * Get the action for a given object.
	 * @param $context LiquidThreadsFormatterContext object
	
	/**
	 * Get the HTML for a *single comment*.
	 * This is basically the guts of this formatter, without tree handling etc.
	 * @param $object The LiquidThreadsPost to show.
	 * @param $version The LiquidThreadsPostVersion at which to show $object
	 * @param $context The LiquidThreadsPostFormatterContext to use for context.
	 * @return String: HTML result.
	 */
	protected function formatSingleComment( $object, $version, $context ) {
		
		$divClass = $this->getDivClass( $object, $context );
		
		$html = '';
		
		$showAnything = wfRunHooks( 'LiquidThreadsShowPostBody',
					array( $object, $context, &$html ) );
		
		if ( $showAnything && $this->runCallback( $object, $context ) ) {
			$html .= Xml::openElement( 'div', array( 'class' => $divClass ) );
			
			if ( $context->getActionFor( $object ) == 'edit' ) {
				$form = new LiquidThreadsPostEditForm(
						$context->get('user'),
						$object
					);
				$formResult = $form->show();
				
				if ( $formResult === true ) {
					$object = $form->getModifiedObject();
					$version = $object->getCurrentVersion();
					$text .= $version->getContentHTML();
				} else {
					$text = $formResult;
				}
			} else {
				$text = $version->getContentHTML();
			}
						
			$html .= $text;

			$html .= Xml::closeElement( 'div' );

			$html .= $this->getToolbar( $object, $context );
			$html .= $this->getPostSignature( $object, $version, $context );
		}
		
		$html = Xml::tags( 'div', array( 'class' => 'lqt-post-wrapper' ), $html );
		
		return $html;
	}
	
	/**
	 * Get the class for the div surrounding the comment *text*.
	 * @param $object The LiquidThreadsPost object
	 * @param $context The LiquidThreadsPostFormatterContext object.
	 * @return String: Space-separated list of classes.
	 */
	protected function getDivClass( $object, $context ) {
		$classes = array('lqt-post-content');

		$nesting_level = $context->get('nesting-level');		
		if ( !is_null( $nesting_level ) ) {
			$classes[] = 'lqt-post-nest-' . $nesting_level;
			$alternatingType = ( $nesting_level % 2 ) ? 'odd' : 'even';
			$classes[] = "lqt-post-$alternatingType";
		}

		return implode( ' ', $classes );
	}
	
	/**
	 * Generates a toolbar for a post.
	 * @param $post The LiquidThreadsPost being shown.
	 * @param $version The LiquidThreadsPostVersion to show the dropdown for.
	 * @param $context A LiquidThreadsPostFormatterContext object.
	 * @return HTML
	 */
	protected function getToolbar( $post, $context ) {
		$toolbar = LiquidThreadsPostToolbar::singleton();
		
		return $toolbar->getHTML( $post, $context );
	}
	
	
	/**
	 * Gets the signature that applies to a post.
	 * @param $post The LiquidThreadsPost to check.
	 * @param $version The LiquidThreadsPostVersion of the post to get the signature from.
	 * @param $context A LiquidThreadsPostFormatterContext object
	 * @return String: The post's signature
	 */
	protected function getPostSignature( $post, $version, $context ) {
		$lang = $context->get('language');

		$signature = $version->getSignature();
		$signature = self::parseSignature( $signature );

		$signature = Xml::tags( 'span',
					array( 'class' => 'lqt-thread-user-signature' ),
					$signature );

		$timestamp = $lang->timeanddate( $version->getPostTime(), true );
		$signature .= Xml::element( 'span',
					array( 'class' => 'lqt-thread-toolbar-timestamp' ),
					$timestamp );

		wfRunHooks( 'LiquidThreadsPostSignature', array( $post, $version, &$signature ) );

		$signature = Xml::tags( 'div', array( 'class' => 'lqt-thread-signature' ),
					$signature );

		return $signature;
	}
	
	/**
	 * Parses a post's signature into HTML.
	 * @param $sig String: The wikitext signature.
	 * @return String: The signature as HTML.
	 */
	public static function parseSignature( $sig ) {
		global $wgParser, $wgOut;

		static $parseCache = array();
		$sigKey = md5( $sig );

		if ( isset( $parseCache[$sigKey] ) ) {
			return $parseCache[$sigKey];
		}

		$sig = $wgOut->parseInline( $sig );

		$parseCache[$sigKey] = $sig;

		return $sig;
	}
}

/**
 * This class provides context for formatting a LiquidThreadsPost object.
 * Valid fields are:
 *     timestamp: The point in time at which to show this LiquidThreadsPost.
 *     user: The User object for which to show this LiquidThreadsPost.
 *     single: If this is set, we will only show that comment and nothing else.
 *     language: Override the Language object that the thread is shown in.
 *     nesting-level: If set, specifies the nesting level. Outermost post should be 0.
 *     post-callbacks: If set, an associative array of callbacks used to replace display of
 *                      individual posts. Key is the post ID, value is a callback. Used for
 *                      edit forms and the like.
 */
class LiquidThreadsPostFormatterContext extends LiquidThreadsFormatterContext {
	protected $validFields = array(
		'timestamp',
		'user',
		'single',
		'language',
		'nesting-level',
		'post-callbacks',
		'version',
		'parent-context',
		'base-url',
	);
}
