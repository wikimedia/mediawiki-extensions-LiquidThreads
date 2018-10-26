<?php
// TODO access control
class SpecialSplitThread extends ThreadActionPage {
	function getFormFields() {
		$splitForm = [
			'src' => [
				'type' => 'info',
				'label-message' => 'lqt-thread-split-thread',
				'default' => LqtView::permalink( $this->mThread ),
				'raw' => 1,
			],
			'subject' => [
				'type' => 'text',
				'label-message' => 'lqt-thread-split-subject',
			],
			'reason' => [
				'label-message' => 'movereason',
				'type' => 'text',
			],
		];

		return $splitForm;
	}

	/**
	 * @see SpecialPage::getDescription
	 * @return string
	 */
	function getDescription() {
		return $this->msg( 'lqt_split_thread' )->text();
	}

	protected function getRightRequirement() {
		return 'lqt-split';
	}

	function trySubmit( $data ) {
		// Load data
		$newSubject = $data['subject'];
		$reason = $data['reason'];

		$this->mThread->split( $newSubject, $reason );

		$link = LqtView::linkInContext( $this->mThread );

		$this->getOutput()->addWikiMsg( 'lqt-split-success', Message::rawParam( $link ) );

		return true;
	}

	function validateSubject( $target ) {
		if ( !$target ) {
			return $this->msg( 'lqt_split_nosubject' )->parse();
		}

		$title = null;
		$article = $this->mThread->article();

		$ok = Thread::validateSubject( $target, $title, null, $article );

		if ( !$ok ) {
			return $this->msg( 'lqt_split_badsubject' )->parse();
		}

		return true;
	}

	function getPageName() {
		return 'SplitThread';
	}

	function getSubmitText() {
		return $this->msg( 'lqt-split-submit' )->text();
	}
}
