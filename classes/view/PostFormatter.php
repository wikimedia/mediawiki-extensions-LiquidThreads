<?php

class LiquidThreadsPostFormatter extends LiquidThreadsFormatter {
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
		
		$timestamp = $context->get('timestamp');
		if ( !is_null($timestamp) ) {
			$version = PostVersion::newPointInTime( $post, $timestamp );
		} else {
			$version = $object->getCurrentVersion();
		}
		
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
				'id' => "lqt-post-id-".$object->getID(),
			) );
		$html .= Xml::element( 'a', array('name' => $this->getAnchor($object) ) );
		
		$html .= $this->formatSingleComment( $object, $version, $context );
		
		if ( count($children) ) {
			$html .= Xml::openElement( 'div', array( 'class' => 'lqt-replies' ) );
			foreach( $children as $child ) {
				$html .= $this->formatSingleComment( $child );
			}
			$html .= Xml::closeElement( 'div' );
		}
		
		$context->decrement('nesting-level');
		
		return $html;
	}
	
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
			
			$text = $version->getContentHTML();
						
			$html .= $text;

			$html .= Xml::closeElement( 'div' );

			$html .= $this->getToolbar( $object, $version, $context );
			$html .= $this->getPostSignature( $object, $version, $context );
			$html .= Xml::closeElement( 'div' );
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
	protected function getToolbar( $post, $version, $context ) {
		$html = '';

		$headerParts = array();

		foreach ( $this->getMajorCommands( $post, $version, $context ) as $key => $cmd ) {
			$content = $this->formatCommand( $cmd, false /* No icon divs */ );
			$headerParts[] = Xml::tags( 'li',
						array( 'class' => "lqt-command lqt-command-$key" ),
						$content );
		}

		// Drop-down menu
		$commands = $this->getMinorCommands( $post, $version, $context );
		$menuHTML = Xml::tags( 'ul', array( 'class' => 'lqt-thread-toolbar-command-list' ),
					$this->formatCommands( $commands ) );

		$triggerText =	Xml::tags( 'a', array( 'class' => 'lqt-thread-actions-icon',
							'href' => '#' ),
					wfMsgHTML( 'lqt-menu-trigger' ) );
		$dropDownTrigger = Xml::tags( 'div',
				array( 'class' => 'lqt-thread-actions-trigger ' .
					'lqt-command-icon', 'style' => 'display: none;' ),
				$triggerText );

		if ( count( $commands ) ) {
			$headerParts[] = Xml::tags( 'li',
						array( 'class' => 'lqt-thread-toolbar-menu' ),
						$dropDownTrigger );
		}

		$html .= implode( ' ', $headerParts );

		$html = Xml::tags( 'ul', array( 'class' => 'lqt-thread-toolbar-commands' ), $html );

		$html = Xml::tags( 'div', array( 'class' => 'lqt-thread-toolbar' ), $html ) .
				$menuHTML;

		return $html;
	}

	/**
	 * Formats a list of toolbar commands.
	 * @param $commands Associative array of commands.
	 * @return HTML
	 * @see LiquidThreadsPostFormatter::formatCommand
	 */
	function formatCommands( $commands ) {
		$result = array();
		foreach ( $commands as $key => $command ) {
			$thisCommand = $this->formatCommand( $command );

			$thisCommand = Xml::tags(
				'li',
				array( 'class' => 'lqt-command lqt-command-' . $key ),
				$thisCommand
			);

			$result[] = $thisCommand;
		}
		return join( ' ', $result );
	}

	/**
	 * Formats a toolbar command
	 * @param $command Associative array describing this command
	 *     Valid keys:
	 *         label: The text to show for this command.
	 *         href: The URL to link to.
	 *         enabled: Whether or not this command is enabled.
	 *         tooltip: If specified, the tooltip to show for this command.
	 *         icon: If specified, an icon is shown.
	 *         showlabel: Whether or not to show the label. Default: on.
	 * @param $icon_divs Boolean: If false, do not insert <divs> to style with an icon.
	 * @return HTML: Command formatted in a <div>
	 */
	function formatCommand( $command, $icon_divs = true ) {
		$label = $command['label'];
		$href = $command['href'];
		$enabled = $command['enabled'];
		$tooltip = isset( $command['tooltip'] ) ? $command['tooltip'] : '';

		if ( isset( $command['icon'] ) ) {
			$icon = Xml::tags( 'div', array( 'title' => $label,
					'class' => 'lqt-command-icon' ), '&#160;' );
			if ( $icon_divs ) {
				if ( !empty( $command['showlabel'] ) ) {
					$label = $icon . '&#160;' . $label;
				} else {
					$label = $icon;
				}
			} else {
				if ( empty( $command['showlabel'] ) ) {
					$label = '';
				}
			}
		}

		if ( $enabled ) {
			$thisCommand = Xml::tags( 'a', array( 'href' => $href, 'title' => $tooltip ),
					$label );
		} else {
			$thisCommand = Xml::tags( 'span', array( 'class' => 'lqt_command_disabled',
						'title' => $tooltip ), $label );
		}

		return $thisCommand;
	}
	
	/**
	 * Gets the commands to show in the dropdown of a post.
	 * @param $post The LiquidThreadsPost to show a dropdown for.
	 * @param $version The LiquidThreadsPostVersion to show the dropdown for.
	 * @param $context A LiquidThreadsPostFormatterContext object.
	 * @return An associative array of arguments suitable for
	 *     LiquidThreadsPostFormatter::formatCommand
	 * @see LiquidThreadsPostFormatter::formatCommand
	 */
	function getMinorCommands( $post, $version, $context ) {
		$commands = array();

		// TODO make this link operate properly
		$history_url = SpecialPage::getTitleFor( 'PostHistory', $post->getID() );
		$commands['history'] = array(
			'label' => wfMsgExt( 'history_short', 'parseinline' ),
			'href' => $history_url,
			'enabled' => true,
		);
		
		// TODO permissions checking
		$edit_url = SpecialPage::getTitleFor( 'EditPost', $post->getID() );
		$commands['edit'] = array(
			'label' => wfMsgExt( 'edit', 'parseinline' ),
			'href' => $edit_url,
			'enabled' => true
		);

		if ( $context->get('user')->isAllowed( 'lqt-split' ) ) {
			$splitUrl = SpecialPage::getTitleFor( 'SplitThread', $post->getID() )->getFullURL();
			$commands['split'] = array(
				'label' => wfMsgExt( 'lqt-thread-split', 'parseinline' ),
				'href' => $splitUrl,
				'enabled' => true
			);
		}

		// TODO implement merging
// 		if ( $context->get('user')->isAllowed( 'lqt-merge' ) ) {
// 			$mergeParams = $_GET;
// 			$mergeParams['lqt_merge_from'] = $thread->id();
// 
// 			unset( $mergeParams['title'] );
// 
// 			$mergeUrl = $this->title->getLocalURL( wfArrayToCGI( $mergeParams ) );
// 			$label = wfMsgExt( 'lqt-thread-merge', 'parseinline' );
// 
// 			$commands['merge'] = array(
// 				'label' => $label,
// 				'href' => $mergeUrl,
// 				'enabled' => true
// 			);
// 		}

		$commands['link'] = array(
			'label' => wfMsgExt( 'lqt_permalink', 'parseinline' ),
			'href' => SpecialPage::getTitleFor( 'Post', $post->getID() ),
			'enabled' => true,
			'showlabel' => true,
			'tooltip' => wfMsgExt( 'lqt_permalink', 'parseinline' )
		);

		wfRunHooks( 'LiquidThreadsPostMinorCommands',
				array( $post, $version, $context, &$commands ) );

		return $commands;
	}

	/**
	 * Gets the main commands in the main (non-dropdown) part of the toolbar.
	 * @param $post The LiquidThreadsPost to show a toolbar for.
	 * @param $version The LiquidThreadsPostVersion to show the toolbar for.
	 * @param $context A LiquidThreadsPostFormatterContext object.
	 * @return An associative array of arguments suitable for
	 *     LiquidThreadsPostFormatter::formatCommand
	 * @see LiquidThreadsPostFormatter::formatCommand
	 */
	function getMajorCommands( $post, $version, $context ) {
		$commands = array();

// 		if ( $this->user->isAllowed( 'lqt-merge' ) &&
// 				$this->request->getCheck( 'lqt_merge_from' ) ) {
// 			$srcThread = Threads::withId( $this->request->getVal( 'lqt_merge_from' ) );
// 			$par = $srcThread->title()->getPrefixedText();
// 			$mergeTitle = SpecialPage::getTitleFor( 'MergeThread', $par );
// 			$mergeUrl = $mergeTitle->getLocalURL( 'dest=' . $thread->id() );
// 			$label = wfMsgExt( 'lqt-thread-merge-to', 'parseinline' );
// 
// 			$commands['merge-to'] = array(
// 				'label' => $label, 'href' => $mergeUrl,
// 				'enabled' => true,
// 				'tooltip' => $label
// 			);
// 		}

		// TODO permissions checking, proper URL
		$commands['reply'] = array(
			'label' => wfMsgExt( 'lqt_reply', 'parseinline' ),
			 'href' => SpecialPage::getTitleFor('Reply', $post->getID() )->getFullURL(),
			 'enabled' => true,
			 'showlabel' => 1,
			 'tooltip' => wfMsg( 'lqt_reply' ),
			 'icon' => 'reply.png',
		);

		// Parent post link
		if ( $version->getParentID() ) {
			$parentID = $version->getParentID();

			$commands['parent'] = array(
				'label' => wfMsgExt( 'lqt-parent', 'parseinline' ),
				'href' => '#' . $this->getAnchor($parentID),
				'enabled' => true,
				'showlabel' => 1,
			);
		}

		wfRunHooks( 'LiquidThreadsPostMajorCommands',
				array( $post, $version, $context, &$commands ) );

		return $commands;
	}
	
	function getPostSignature( $post, $version, $context ) {
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
	
	static function parseSignature( $sig ) {
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
	);
}
