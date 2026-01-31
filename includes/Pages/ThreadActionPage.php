<?php

use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use MediaWiki\Request\WebRequest;
use MediaWiki\SpecialPage\UnlistedSpecialPage;
use MediaWiki\Title\Title;
use MediaWiki\User\User;

abstract class ThreadActionPage extends UnlistedSpecialPage {
	/** @var User */
	protected $user;
	/** @var OutputPage */
	protected $output;
	/** @var WebRequest */
	protected $request;
	/** @var Title|null */
	protected $title;
	/** @var Thread|null */
	protected $mThread;
	/** @var string|null */
	protected $mTarget;

	public function __construct() {
		parent::__construct( $this->getPageName(), $this->getRightRequirement() );

		$this->output = $this->getOutput();
		$this->user = $this->getUser();
		$this->request = $this->getRequest();
	}

	abstract public function getPageName();

	abstract public function getFormFields();

	/**
	 * @return string
	 */
	protected function getRightRequirement() {
		return '';
	}

	public function execute( $par ) {
		if ( !$this->userCanExecute( $this->getUser() ) ) {
			$this->displayRestrictionError();
		}

		// Page title
		$this->getOutput()->setPageTitleMsg( $this->getDescription() );

		if ( !$this->checkParameters( $par ) ) {
			return;
		}

		$form = $this->buildForm();
		$form->show();
	}

	/**
	 * Loads stuff like the thread and so on
	 *
	 * @param string|null $par
	 * @return bool
	 */
	public function checkParameters( $par ) {
		// Handle parameter
		$this->mTarget = $par;
		if ( $par === null || $par === "" ) {
			$this->output->addHTML( $this->msg( 'lqt_threadrequired' )->escaped() );
			return false;
		}

		$thread = Threads::withRoot(
			MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle(
				Title::newFromText( $par )
			)
		);
		if ( !$thread ) {
			$this->output->addHTML( $this->msg( 'lqt_nosuchthread' )->escaped() );
			return false;
		}

		$this->mThread = $thread;

		return true;
	}

	abstract public function getSubmitText();

	public function buildForm() {
		$form = new HTMLForm(
			$this->getFormFields(),
			$this->getContext(),
			'lqt-' . $this->getPageName()
		);

		$form->setSubmitText( $this->getSubmitText() );
		$form->setSubmitCallback( [ $this, 'trySubmit' ] );

		return $form;
	}

	abstract public function trySubmit( $data );
}
