<?php

use MediaWiki\MediaWikiServices;

class SpecialNewMessages extends SpecialPage {
	public function __construct() {
		parent::__construct( 'NewMessages' );
		$this->mIncludable = true;
	}

	public function doesWrites() {
		return true;
	}

	/**
	 * @see SpecialPage::getDescription
	 * @return string
	 */
	public function getDescription() {
		return $this->msg( 'lqt_newmessages-title' )->text();
	}

	public function execute( $par ) {
		$user = $this->getUser();
		$output = $this->getOutput();
		$request = $this->getRequest();

		$this->setHeaders();

		$article = new Article( $this->getPageTitle(), 0 );
		$title = $this->getPageTitle();

		// Clear newtalk
		DeferredUpdates::addCallableUpdate( function () use ( $user ) {
			MediaWikiServices::getInstance()
				->getTalkPageNotificationManager()->removeUserHasNewMessages( $user );
		} );

		$view = new NewUserMessagesView( $output, $article,
			$title, $user, $request );

		if ( $request->getBool( 'lqt_inline' ) ) {
			$view->doInlineEditForm();
			return;
		}

		$view->showOnce(); // handles POST etc.

		$view->show();
	}

	protected function getGroupName() {
		return 'wiki';
	}
}
