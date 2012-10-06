<?php

class SpecialNewMessages extends SpecialPage {
	function __construct() {
		parent::__construct( 'NewMessages' );
		$this->mIncludable = true;
	}

	/**
	 * @see SpecialPage::getDescription
	 */
	function getDescription() {
		return $this->msg( 'lqt_newmessages-title' )->text();
	}

	function execute( $par ) {
		$user = $this->getUser();
		$output = $this->getOutput();
		$request = $this->getRequest();

		$this->setHeaders();

		$article = new Article( $this->getTitle(), 0 );
		$title = $this->getTitle();

		// Clear newtalk
		$user->setNewtalk( false );

		$view = new NewUserMessagesView( $output, $article,
			$title, $user, $request );

		if ( $request->getBool( 'lqt_inline' ) ) {
			$view->doInlineEditForm();
			return;
		}

		$view->showOnce(); // handles POST etc.

		$view->show();
	}
}
