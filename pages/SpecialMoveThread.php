<?php

use MediaWiki\MediaWikiServices;

class SpecialMoveThread extends ThreadActionPage {
	/**
	 * @see SpecialPage::getDescription
	 * @return string
	 */
	public function getDescription() {
		return $this->msg( 'lqt_movethread' )->text();
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

		$form->setIntro( $intro );

		return $form;
	}

	public function checkUserRights( $oldTitle, $newTitle ) {
		$user = $this->getUser();
		$permManager = MediaWikiServices::getInstance()->getPermissionManager();
		$oldErrors = $permManager->getPermissionErrors( 'move', $user, $oldTitle );
		$newErrors = $permManager->getPermissionErrors( 'move', $user, $newTitle );

		// Custom merge/unique function because we don't have the second parameter to
		// array_unique on Wikimedia.
		$mergedErrors = [];
		foreach ( array_merge( $oldErrors, $newErrors ) as $key => $value ) {
			if ( !is_numeric( $key ) ) {
				$mergedErrors[$key] = $value;
			} elseif ( !in_array( $value, $mergedErrors ) ) {
				$mergedErrors[] = $value;
			}
		}

		if ( count( $mergedErrors ) > 0 ) {
			$out = $this->getOutput();
			return $out->parseAsInterface(
				$out->formatPermissionsErrorMessage( $mergedErrors, 'move' )
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
