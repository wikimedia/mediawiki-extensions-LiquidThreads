<?php

use MediaWiki\Message\Message;

class SpecialSplitThread extends ThreadActionPage {
	public function getFormFields() {
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
	 * @return Message
	 */
	public function getDescription() {
		return $this->msg( 'lqt_split_thread' );
	}

	/** @inheritDoc */
	protected function getRightRequirement() {
		return 'lqt-split';
	}

	public function trySubmit( $data ) {
		// Load data
		$newSubject = $data['subject'];
		$reason = $data['reason'];

		$this->mThread->split( $newSubject, $reason );

		$link = LqtView::linkInContext( $this->mThread );

		$this->getOutput()->addWikiMsg( 'lqt-split-success', Message::rawParam( $link ) );

		return true;
	}

	public function validateSubject( $target ) {
		if ( !$target ) {
			return $this->msg( 'lqt_split_nosubject' )->parse();
		}

		$title = null;
		$article = $this->mThread->article();

		$ok = Thread::validateSubject( $target, $this->getUser(), $title, null, $article );

		if ( !$ok ) {
			return $this->msg( 'lqt_split_badsubject' )->parse();
		}

		return true;
	}

	public function getPageName() {
		return 'SplitThread';
	}

	public function getSubmitText() {
		return $this->msg( 'lqt-split-submit' )->text();
	}
}
