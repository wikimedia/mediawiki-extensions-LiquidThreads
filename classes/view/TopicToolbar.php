<?php

class LiquidThreadsTopicToolbar extends LiquidThreadsToolbar {
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
	 * Returns the commands to be shown in this toolbar.
	 * @param $object The object that the toolbar is for.
	 * @param $context A context object, usually for a related formatter.
	 * @return An array suitable for LiquidThreadsToolbar::formatCommands
	 */
	function getCommands( $topic, $context = null ) {
		$commands = array();

		$commands['history'] = array(
			'label' => wfMsg( 'history_short' ),
			'href' => SpecialPage::getTitleFor('TopicHistory', $topic->getID())->getFullURL(),
			'enabled' => true
		);

		if ( $context->get('user')->isAllowed( 'move' ) ) {
			$move_href = SpecialPage::getTitleFor( 'MoveTopic', $topic->getID() )->getFullURL();
			$commands['move'] = array(
				'label' => wfMsg( 'lqt-movethread' ),
				'href' => $move_href,
				'enabled' => true
			);
		}

// 		if ( $this->user->isAllowed( 'protect' ) ) {
// 			$protect_href = $thread->title()->getLocalURL( 'action=protect' );
// 
// 			// Check if it's already protected
// 			if ( !$thread->title()->isProtected() ) {
// 				$label = wfMsg( 'protect' );
// 			} else {
// 				$label = wfMsg( 'unprotect' );
// 			}
// 
// 			$commands['protect'] = array(
// 				'label' => $label,
// 				'href' => $protect_href,
// 				'enabled' => true
// 			);
// 		}

// 		if ( !$this->user->isAnon() && !$thread->title()->userIsWatching() ) {
// 			$commands['watch'] = array(
// 				'label' => wfMsg( 'watch' ),
// 				'href' => self::permalinkUrlWithQuery( $thread, 'action=watch' ),
// 				'enabled' => true
// 			);
// 		} else if ( !$this->user->isAnon() ) {
// 			$commands['unwatch'] = array(
// 				'label' => wfMsg( 'unwatch' ),
// 				'href' => self::permalinkUrlWithQuery( $thread, 'action=unwatch' ),
// 				'enabled' => true
// 			);
// 		}

		$summarizeUrl = SpecialPage::getTitleFor('SummarizeTopic', $topic->getID())->getFullURL();
		$commands['summarize'] = array(
			'label' => wfMsgExt( 'lqt_summarize_link', 'parseinline' ),
			'href' => $summarizeUrl,
			'enabled' => true,
		);

		wfRunHooks( 'LiquidThreadsTopicCommands', array( $topic, $context, &$commands ) );

		return $commands;
	}
}
