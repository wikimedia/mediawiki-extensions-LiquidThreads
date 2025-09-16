<?php

use MediaWiki\Html\Html;
use MediaWiki\Message\Message;

class SpecialMergeThread extends ThreadActionPage {

	/** @var Thread */
	protected $mDestThread;

	public function getFormFields() {
		$splitForm = [
			'src' => [
				'type' => 'info',
				'label-message' => 'lqt-thread-merge-source',
				'default' => $this->formatThreadField( 'src', $this->mThread->id() ),
				'raw' => true
			],
			'dest' => [
				'type' => 'info',
				'label-message' => 'lqt-thread-merge-dest',
				'default' => $this->formatThreadField( 'dest', $this->request->getInt( 'dest' ) ),
				'raw' => true
			],
			'reason' => [
				'label-message' => 'movereason',
				'type' => 'text'
			]
		];

		return $splitForm;
	}

	/** @inheritDoc */
	protected function getRightRequirement() {
		return 'lqt-merge';
	}

	public function checkParameters( $par ) {
		if ( !parent::checkParameters( $par ) ) {
			return false;
		}

		$dest = $this->request->getInt( 'dest' );

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

	/**
	 * @param string $field
	 * @param int $threadid
	 * @return string
	 */
	public function formatThreadField( $field, $threadid ) {
		$t = Threads::withId( $threadid );

		$out = Html::hidden( $field, $threadid );
		$out .= LqtView::permalink( $t );

		return $out;
	}

	/**
	 * @see SpecialPage::getDescription
	 * @return Message
	 */
	public function getDescription() {
		return $this->msg( 'lqt_merge_thread' );
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
