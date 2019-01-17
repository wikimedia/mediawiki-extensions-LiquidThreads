<?php

// @todo FIXME: access control
class SpecialMergeThread extends ThreadActionPage {
	public function getFormFields() {
		$splitForm = [
			'src' => [
				'type' => 'info',
				'label-message' => 'lqt-thread-merge-source',
				'default' => $this->formatThreadField( 'src', $this->mThread->id() ),
				'raw' => 1
			],
			'dest' => [
				'type' => 'info',
				'label-message' => 'lqt-thread-merge-dest',
				'default' => $this->formatThreadField( 'dest', $this->request->getVal( 'dest' ) ),
				'raw' => 1
			],
			'reason' => [
				'label-message' => 'movereason',
				'type' => 'text'
			]
		];

		return $splitForm;
	}

	protected function getRightRequirement() {
		return 'lqt-merge';
	}

	public function checkParameters( $par ) {
		if ( !parent::checkParameters( $par ) ) {
			return false;
		}

		$dest = $this->request->getVal( 'dest' );

		if ( !$dest ) {
			$this->getOutput()->addWikiMsg( 'lqt_threadrequired' );
			return false;
		}

		$thread = Threads::withId( $dest );

		if ( !$thread ) {
			$this->getOutput()->addWikiMsg( 'lqt_nosuchthread' );
			return false;
		}

		$this->mDestThread = $thread;

		return true;
	}

	public function formatThreadField( $field, $threadid ) {
		if ( !is_object( $threadid ) ) {
			$t = Threads::withId( $threadid );
		} else {
			$t = $threadid;
			$threadid = $t->id();
		}

		$out = Html::hidden( $field, $threadid );
		$out .= LqtView::permalink( $t );

		return $out;
	}

	/**
	 * @see SpecialPage::getDescription
	 * @return string
	 */
	public function getDescription() {
		return $this->msg( 'lqt_merge_thread' )->text();
	}

	public function trySubmit( $data ) {
		// Load data
		$srcThread = $this->mThread;
		$dstThread = $this->mDestThread;
		$reason = $data['reason'];

		$srcThread->moveToParent( $dstThread, $reason );

		$srcLink = LqtView::linkInContext( $srcThread );
		$dstLink = LqtView::linkInContext( $dstThread );

		$this->getOutput()->addHTML( $this->msg( 'lqt-merge-success' )
			->rawParams( $srcLink, $dstLink )->parse() );

		return true;
	}

	public function getPageName() {
		return 'MergeThread';
	}

	public function getSubmitText() {
		return $this->msg( 'lqt-merge-submit' )->text();
	}
}
