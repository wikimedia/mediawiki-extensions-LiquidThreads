<?php

// Contains formatter functions for all log entry types.
class LqtLogFormatter {
	static function formatLogEntry( $type, $action, $title, $sk, $parameters ) {
		$msg = "lqt-log-action-$action";

		switch ( $action ) {
			case 'merge':
				if ( $parameters[0] ) {
					$msg = 'lqt-log-action-merge-across';
				} else {
					$msg = 'lqt-log-action-merge-down';
				}
				break;
			case 'move':
				$smt = new SpecialMoveThread;
				$rightsCheck = $smt->checkUserRights(
					$parameters[1] instanceof Title ? $parameters[1] : Title::newFromText( $parameters[1] ),
					$parameters[0] instanceof Title ? $parameters[0] : Title::newFromText( $parameters[0] )
				);

				if ( $rightsCheck === true ) {
					$parameters[] = Message::rawParam( Linker::link(
						SpecialPage::getTitleFor( 'MoveThread', $title ),
						wfMessage( 'revertmove' )->text(),
						array(),
						array( 'dest' => $parameters[0] )
					) );
				} else {
					$parameters[] = '';
				}
				break;
			default:
				// Give grep a chance to find the usages:
				// lqt-log-action-move, lqt-log-action-split, lqt-log-action-subjectedit,
				// lqt-log-action-resort, lqt-log-action-signatureedit
				$msg = "lqt-log-action-$action";
				break;
		}

		array_unshift( $parameters, $title->getPrefixedText() );
		$html = wfMessage( $msg, $parameters );

		if ( $sk === null ) {
			return StringUtils::delimiterReplace( '<', '>', '', $html->inContentLanguage()->parse() );
		}

		return $html->parse();
	}
}
