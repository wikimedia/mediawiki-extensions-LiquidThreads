<?php

use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use MediaWiki\SpecialPage\IncludableSpecialPage;

class SpecialNewMessages extends IncludableSpecialPage {
	public function __construct() {
		parent::__construct( 'NewMessages' );
	}

	public function doesWrites() {
		return true;
	}

	/**
	 * @see SpecialPage::getDescription
	 * @return Message
	 */
	public function getDescription() {
		return $this->msg( 'lqt_newmessages-title' );
	}

	public function execute( $par ) {
		$user = $this->getUser();
		$output = $this->getOutput();
		$request = $this->getRequest();

		$this->setHeaders();

		$title = $this->getPageTitle();

		// Clear newtalk
		DeferredUpdates::addCallableUpdate( static function () use ( $user ) {
			MediaWikiServices::getInstance()
				->getTalkPageNotificationManager()->removeUserHasNewMessages( $user );
		} );

		$view = new NewUserMessagesView( $output, null,
			$title, $user, $request );

		if ( $request->getBool( 'lqt_inline' ) ) {
			$view->doInlineEditForm();
			return;
		}

		$view->showOnce(); // handles POST etc.

		$view->show();
	}

	/** @inheritDoc */
	protected function getGroupName() {
		return 'wiki';
	}
}
