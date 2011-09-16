<?php

class LiquidThreadsPostToolbar extends LiquidThreadsToolbar {

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
	 * Generates a toolbar for a post.
	 * @param $post The LiquidThreadsPost being shown.
	 * @param $version The LiquidThreadsPostVersion to show the dropdown for.
	 * @param $context A LiquidThreadsPostFormatterContext object.
	 * @return HTML
	 */
	public function getHTML( $post, $context = null ) {
		$this->checkArguments( $post, $context );
		
		$html = '';

		$version = $context->get('version');
		// Retrieve the appropriate version if necessary
		if ( is_null($version) || $version->getPostID() != $post->getID() ) {
			$timestamp = $context->get('timestamp');
			$version = LiquidThreadsPostVersion::newPointInTime( $post, $timestamp );
			$context->set('version', $version);
		}

		$headerParts = array();

		foreach ( $this->getCommands( $post, $context ) as $key => $cmd ) {
			$content = $this->formatCommand( $cmd, false /* No icon divs */ );
			$headerParts[] = Xml::tags( 'li',
						array( 'class' => "lqt-command lqt-command-$key" ),
						$content );
		}

		// Drop-down menu
		$commands = $this->getMinorCommands( $post, $context );
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
	 * Gets the main commands in the main (non-dropdown) part of the toolbar.
	 * @param $post The LiquidThreadsPost to show a toolbar for.
	 * @param $version The LiquidThreadsPostVersion to show the toolbar for.
	 * @param $context A LiquidThreadsPostFormatterContext object.
	 * @return An associative array of arguments suitable for
	 *     LiquidThreadsToolbar::formatCommand
	 * @see LiquidThreadsToolbar::formatCommand
	 */
	protected function getCommands( $post, $context = null ) {
		$commands = array();
		
		// Will have been set in getToolbar()
		$version = $context->get('version');

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

		$replyQuery = array(
			'lqt_action' => 'reply',
			'lqt_target' => $post->getUniqueIdentifier(),
		);

		$replyLink = wfAppendQuery( $context->get('base-url'), $replyQuery );

		$commands['reply'] = array(
			'label' => wfMsgExt( 'lqt_reply', 'parseinline' ),
			 'href' => $replyLink,
			 'enabled' => true,
			 'showlabel' => 1,
			 'tooltip' => wfMsg( 'lqt_reply' ),
			 'icon' => 'reply.png',
		);

		// Parent post link
		if ( $version->getParentID() ) {
			$parent = LiquidThreadsPost::newFromID( $version->getParentID() );

			$commands['parent'] = array(
				'label' => wfMsgExt( 'lqt-parent', 'parseinline' ),
				'href' => '#' . $this->getAnchor($parent),
				'enabled' => true,
				'showlabel' => 1,
			);
		}

		wfRunHooks( 'LiquidThreadsPostMajorCommands',
				array( $post, $version, $context, &$commands ) );

		return $commands;
	}


	/**
	 * Gets the commands to show in the dropdown of a post.
	 * @param $post The LiquidThreadsPost to show a dropdown for.
	 * @param $version The LiquidThreadsPostVersion to show the dropdown for.
	 * @param $context A LiquidThreadsPostFormatterContext object.
	 * @return An associative array of arguments suitable for
	 *     LiquidThreadsToolbar::formatCommand
	 * @see LiquidThreadsToolbar::formatCommand
	 */
	protected function getMinorCommands( $post, $context ) {
		$commands = array();
		
		// Will have been set in getToolbar()
		$version = $context->get('version');

		// TODO make this link operate properly
		$history = SpecialPage::getTitleFor( 'PostHistory', $post->getID() );
		$commands['history'] = array(
			'label' => wfMsgExt( 'history_short', 'parseinline' ),
			'href' => $history->getLocalURL(),
			'enabled' => true,
		);
		
		// TODO permissions checking
		$editQuery = array(
			'lqt_action' => 'edit',
			'lqt_target' => $post->getUniqueIdentifier(),
		);
		
		$edit_url = wfAppendQuery( $context->get('base-url'), $editQuery );
		
		$commands['edit'] = array(
			'label' => wfMsgExt( 'edit', 'parseinline' ),
			'href' => $edit_url,
			'enabled' => true
		);

		if ( $context->get('user')->isAllowed( 'lqt-split' ) ) {
			$splitUrl = SpecialPage::getTitleFor( 'SplitThread', $post->getID() )->getFullURL();
			$commands['split'] = array(
				'label' => wfMsgExt( 'lqt-thread-split', 'parseinline' ),
				'href' => $splitUrl->getLocalURL(),
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

		$linkTarget = SpecialPage::getTitleFor( 'Post', $post->getID() );

		$commands['link'] = array(
			'label' => wfMsgExt( 'lqt_permalink', 'parseinline' ),
			'href' => $linkTarget->getLocalURL(),
			'enabled' => true,
			'showlabel' => true,
			'tooltip' => wfMsgExt( 'lqt_permalink', 'parseinline' )
		);

		wfRunHooks( 'LiquidThreadsPostMinorCommands',
				array( $post, $version, $context, &$commands ) );

		return $commands;
	}
}
