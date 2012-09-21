<?php

// Contains formatter functions for all log entry types.
class LqtLogFormatter {
	static function formatLogEntry( $type, $action, $title, $sk, $parameters ) {
		switch( $action ) {
			case 'merge':
				if ( $parameters[0] ) {
					$msg = 'lqt-log-action-merge-across';
				} else {
					$msg = 'lqt-log-action-merge-down';
				}
				break;
			default:
				$msg = "lqt-log-action-$action";
				break;
		}

		array_unshift( $parameters, $title->getPrefixedText() );
		$html = wfMessage( $msg, $parameters );
		$forIRC = $sk === null;

		if ( $forIRC ) {
			$html = $html->inContentLanguage()->parse();
			$html = StringUtils::delimiterReplace( '<', '>', '', $html );
		} else {
			$html = $html->parse();
		}

		return $html;
	}
}
