<?php

class EchoLiquidThreadsFormatter extends EchoBasicFormatter {
	protected function processParam( $event, $param, $message, $user ) {
		$extra = $event->getExtra();
		if ( $param === 'subject' ) {
			$thread = $this->getThread( $event );
			if ( $thread ) {
				$message->params( $thread->subject() );
			} else {
				$message->params( '' );
			}
		} elseif ( $param === 'commentText' ) {
			global $wgLang; // Message::language is protected :(

			$thread = $this->getThread( $event );
			if ( $thread ) {
				$content = EchoDiscussionParser::stripHeader( $thread->root()->getContent() );
				$content = $wgLang->truncate( $content, 200 );

				$message->params( $content );
			} else {
				$message->params( '' );
			}
		} elseif ( $param === 'content-page' ) {
			if ( $event->getTitle() ) {
				$message->params( $event->getTitle()->getSubjectPage()->getPrefixedText() );
			} else {
				$message->params( '' );
			}
		} else {
			parent::processParam( $event, $param, $message, $user );
		}
	}

	protected function getThread( $event ) {
		$extra = $event->getExtra();
		if ( !$extra || !$extra['thread'] ) {
			return true;
		}

		$thread = Threads::withId( $extra['thread'] );

		return $thread;
	}
}