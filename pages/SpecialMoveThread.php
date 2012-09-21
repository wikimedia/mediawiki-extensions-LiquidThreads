<?php

class SpecialMoveThread extends ThreadActionPage {
	/**
	 * @see SpecialPage::getDescription
	 */
	function getDescription() {
		return $this->msg( 'lqt_movethread' )->text();
	}

	function getFormFields() {
		return array(
			'dest-title' => array(
				'label-message' => 'lqt_move_destinationtitle',
				'type' => 'text',
				'validation-callback' => array( $this, 'validateTarget' ),
			),
			'reason' => array(
				'label-message' => 'movereason',
				'type' => 'text',
			)
		);
	}

	function getPageName() { return 'MoveThread'; }

	function getSubmitText() {
		return $this->msg( 'lqt_move_move' )->text();
	}

	function buildForm() {
		$form = parent::buildForm();

		// Generate introduction
		$intro = '';

		$page = $this->mThread->getTitle()->getPrefixedText();

		$edit_text = $this->msg( 'lqt_move_torename_edit' )->parse();
		$edit_link = Linker::link(
			$this->mThread->title(),
			$edit_text,
			array(),
			array(
				'lqt_method' => 'edit',
				'lqt_operand' => $this->mThread->id()
			)
		);

		$intro .= $this->msg(
			'lqt_move_movingthread',
			array( '[[' . $this->mTarget . ']]', '[[' . $page . ']]' )
		)->parseAsBlock();
		$intro .= $this->msg( 'lqt_move_torename' )->rawParams( $edit_link )->parseAsBlock();

		$form->setIntro( $intro );

		return $form;
	}

	function checkUserRights( $oldTitle, $newTitle ) {
		$user = $this->getUser();
		$oldErrors = $oldTitle->getUserPermissionsErrors( 'move', $user );
		$newErrors = $newTitle->getUserPermissionsErrors( 'move', $user );

		// Custom merge/unique function because we don't have the second parameter to
		// array_unique on Wikimedia.
		$mergedErrors = array();
		foreach ( array_merge( $oldErrors, $newErrors ) as $key => $value ) {
			if ( !is_numeric( $key ) ) {
				$mergedErrors[$key] = $value;
			} elseif ( !in_array( $value, $mergedErrors ) ) {
				$mergedErrors[] = $value;
			}
		}

		if ( count( $mergedErrors ) > 0 ) {
			$out = $this->getOutput();
			return $out->parse(
				$out->formatPermissionsErrorMessage( $mergedErrors, 'move' )
			);
		}

		return true;
	}

	function trySubmit( $data ) {
		// Load data
		$tmp = $data['dest-title'];
		$newtitle = Title::newFromText( $tmp );
		$reason = $data['reason'];

		$rightsResult = $this->checkUserRights( $this->mThread->title(), $newtitle );

		if ( $rightsResult !== true )
			return $rightsResult;

		// @todo No status code from this method.
		$this->mThread->moveToPage( $newtitle, $reason, true );

		$this->getOutput()->addHTML( $this->msg( 'lqt_move_success' )
			->rawParams( Linker::link( $newtitle ) )->parseAsBlock() );

		return true;
	}

	function validateTarget( $target ) {
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
