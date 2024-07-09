<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use MediaWiki\Permissions\PermissionStatus;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;

class SpecialMoveThread extends ThreadActionPage {
	/**
	 * @see SpecialPage::getDescription
	 * @return Message
	 */
	public function getDescription() {
		return $this->msg( 'lqt_movethread' );
	}

	public function getFormFields() {
		$destAdditions = [];
		$reasonAdditions = [];
		if ( $this->getRequest()->getCheck( 'dest' ) ) {
			$destAdditions['default'] = $this->getRequest()->getText( 'dest' );
			$reasonAdditions['autofocus'] = true;
		} else {
			$destAdditions['autofocus'] = true;
		}

		return [
			'dest-title' => [
				'label-message' => 'lqt_move_destinationtitle',
				'type' => 'text',
				'validation-callback' => [ $this, 'validateTarget' ],
			] + $destAdditions,
			'reason' => [
				'label-message' => 'movereason',
				'type' => 'text',
			] + $reasonAdditions,
		];
	}

	public function getPageName() {
		return 'MoveThread';
	}

	public function getSubmitText() {
		return $this->msg( 'lqt_move_move' )->text();
	}

	public function buildForm() {
		$form = parent::buildForm();

		// Generate introduction
		$intro = '';

		$page = $this->mThread->getTitle()->getPrefixedText();

		$edit_text = new HtmlArmor( $this->msg( 'lqt_move_torename_edit' )->parse() );
		$edit_link = MediaWikiServices::getInstance()->getLinkRenderer()->makeLink(
			$this->mThread->title(),
			$edit_text,
			[],
			[
				'lqt_method' => 'edit',
				'lqt_operand' => $this->mThread->id()
			]
		);

		$intro .= $this->msg(
			'lqt_move_movingthread',
			[ '[[' . $this->mTarget . ']]', '[[' . $page . ']]' ]
		)->parseAsBlock();
		$intro .= $this->msg( 'lqt_move_torename' )->rawParams( $edit_link )->parseAsBlock();

		$form->setPreHtml( $intro );

		return $form;
	}

	public function checkUserRights( $oldTitle, $newTitle ) {
		$user = $this->getUser();
		$status = new PermissionStatus();
		$permManager = MediaWikiServices::getInstance()->getPermissionManager();
		$status->merge( $permManager->getPermissionStatus( 'move', $user, $oldTitle ) );
		$status->merge( $permManager->getPermissionStatus( 'move', $user, $newTitle ) );

		if ( !$status->isGood() ) {
			$out = $this->getOutput();
			return $out->parseAsInterface(
				$out->formatPermissionStatus( $status, 'move' )
			);
		}

		return true;
	}

	public function trySubmit( $data ) {
		// Load data
		$tmp = $data['dest-title'];
		$oldtitle = $this->mThread->getTitle();
		$newtitle = Title::newFromText( $tmp );
		$reason = $data['reason'];

		$rightsResult = $this->checkUserRights( $this->mThread->title(), $newtitle );

		if ( $rightsResult !== true ) {
			return $rightsResult;
		}

		// @todo No status code from this method.
		$this->mThread->moveToPage( $newtitle, $reason, true, $this->getUser() );
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();

		$this->getOutput()->addHTML( $this->msg( 'lqt_move_success' )->rawParams(
			$linkRenderer->makeLink( $newtitle ),
			$linkRenderer->makeLink(
				SpecialPage::getTitleFor( 'MoveThread', $this->mThread->root()->getTitle() ),
				$this->msg( 'revertmove' )->text(),
				[],
				[ 'dest' => $oldtitle ]
			)
		)->parseAsBlock() );

		return true;
	}

	public function validateTarget( $target ) {
		if ( !$target ) {
			return $this->msg( 'lqt_move_nodestination' )->parse();
		}

		$title = Title::newFromText( $target );

		if ( !$title || !LqtDispatch::isLqtPage( $title ) ) {
			return $this->msg( 'lqt_move_thread_bad_destination' )->parse();
		}

		if ( $title->equals( $this->mThread->getTitle() ) ) {
			return $this->msg( 'lqt_move_samedestination' )->parse();
		}

		return true;
	}
}
