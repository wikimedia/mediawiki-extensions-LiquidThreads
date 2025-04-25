<?php

use MediaWiki\Logging\LogFormatter;
use MediaWiki\Message\Message;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;

// Contains formatter functions for all log entry types.
class LqtLogFormatter extends LogFormatter {
	protected function getActionMessage() {
		$action = $this->entry->getSubtype();
		$title = $this->entry->getTarget();
		$parameters = $this->entry->getParameters();

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
					$parameters[] = Message::rawParam( $this->getLinkRenderer()->makeLink(
						SpecialPage::getTitleFor( 'MoveThread', $title ),
						wfMessage( 'revertmove' )->text(),
						[],
						[ 'dest' => $parameters[0] ]
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

		if ( $this->plaintext ) {
			$html = StringUtils::delimiterReplace( '<', '>', '', $html->inContentLanguage()->parse() );
		} else {
			$html = $html->parse();
		}

		if ( !$this->irctext ) {
			$performer = $this->getPerformerElement();
			$sep = $this->msg( 'word-separator' );
			$sep = $this->plaintext ? $sep->text() : $sep->escaped();
			$html = $performer . $sep . $html;
		}

		return $html;
	}
}
