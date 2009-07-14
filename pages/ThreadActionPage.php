<?php

abstract class ThreadActionPage extends UnlistedSpecialPage {
	protected $user, $output, $request, $title, $mThread;

	function __construct() {
		parent::__construct( $this->getPageName() );
		$this->includable( false );
		
		global $wgOut, $wgUser, $wgRequest;
		$this->output = $wgOut;
		$this->user = $wgUser;
		$this->request = $wgRequest;
	}
	
	abstract function getPageName();
	
	abstract function getFormFields();

	function execute( $par ) {
		wfLoadExtensionMessages( 'LiquidThreads' );
		
		global $wgOut;
		
		// Page title
		$wgOut->setPageTitle( $this->getDescription() );
		
		// Handle parameter
		$this->mTarget = $par;
		if ( $par === null || $par === "" ) {
			wfLoadExtensionMessages( 'LiquidThreads' );
			$this->output->addHTML( wfMsg( 'lqt_threadrequired' ) );
			return;
		}
		$thread = Threads::withRoot( new Article( Title::newFromURL( $par ) ) );
		if ( !$thread ) {
			$this->output->addHTML( wfMsg( 'lqt_nosuchthread' ) );
			return;
		}
		$this->mThread = $thread;
		
		$form = $this->buildForm();
		$form->show();
	}
	
	abstract function getSubmitText();
	
	function buildForm() {
		$form = new HTMLForm( $this->getFormFields(), 'lqt-'.$this->getPageName() );
		
		$par = $this->mThread->title()->getPrefixedText();
		
		$form->setSubmitText( $this->getSubmitText() );
		$form->setTitle( SpecialPage::getTitleFor( $this->getPageName(), $par ) );
		$form->setSubmitCallback( array( $this, 'trySubmit' ) );
		
		return $form;
	}
	
	abstract function trySubmit( $data );

}
