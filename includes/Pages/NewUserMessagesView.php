<?php

use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Xml\Xml;

class NewUserMessagesView extends LqtView {
	/** @var int[] */
	protected $highlightThreads;
	/** @var array[] */
	protected $messagesInfo;

	private function htmlForReadButton( $label, $title, $class, $ids ) {
		$ids_s = implode( ',', $ids );
		$html = '';
		$html .= Html::hidden( 'lqt_method', 'mark_as_read' );
		$html .= Html::hidden( 'lqt_operand', $ids_s );
		$html .= Html::submitButton(
			$label,
			[
				'name' => 'lqt_read_button',
				'title' => $title,
				'class' => 'lqt-read-button'
			]
		);
		$html = Xml::tags( 'form', [ 'method' => 'post', 'class' => $class ], $html );

		return $html;
	}

	public function getReadAllButton() {
		return $this->htmlForReadButton(
			wfMessage( 'lqt-read-all' )->text(),
			wfMessage( 'lqt-read-all-tooltip' )->text(),
			'lqt_newmessages_read_all_button',
			[ 'all' ]
		);
	}

	public function getUndoButton( $ids ) {
		if ( count( $ids ) == 1 ) {
			$t = Threads::withId( (int)$ids[0] );
			if ( !$t ) {
				return; // empty or just bogus operand.
			}
			$msg = wfMessage( 'lqt-marked-read', LqtView::formatSubject( $t->subject() ) )->parse();
		} else {
			$msg = wfMessage( 'lqt-count-marked-read' )->numParams( count( $ids ) )->parse();
		}
		$operand = implode( ',', $ids );

		$html = '';
		$html .= $msg;
		$html .= Html::hidden( 'lqt_method', 'mark_as_unread' );
		$html .= Html::hidden( 'lqt_operand', $operand );
		$html .= ' ' . Html::submitButton(
			wfMessage( 'lqt-email-undo' )->text(),
			[
				'name' => 'lqt_read_button',
				'title' => wfMessage( 'lqt-email-info-undo' )->text()
			]
		);

		$html = Xml::tags(
			'form',
			[ 'method' => 'post', 'class' => 'lqt_undo_mark_as_read' ],
			$html
		);

		return $html;
	}

	public function postDivClass( Thread $thread ) {
		$origClass = parent::postDivClass( $thread );

		if ( in_array( $thread->id(), $this->highlightThreads ) ) {
			return "$origClass lqt_post_new_message";
		}

		return $origClass;
	}

	public function showOnce() {
		NewMessages::recacheMessageCount( $this->user->getId() );

		$user = $this->user;
		DeferredUpdates::addCallableUpdate( static function () use ( $user ) {
			MediaWikiServices::getInstance()
				->getTalkPageNotificationManager()->removeUserHasNewMessages( $user );
		} );

		if ( $this->methodApplies( 'mark_as_unread' ) ) {
			$ids = explode( ',', $this->request->getVal( 'lqt_operand', '' ) );

			foreach ( $ids as $id ) {
				$tmp_thread = Threads::withId( (int)$id );
				if ( $tmp_thread ) {
					NewMessages::markThreadAsUnreadByUser( $tmp_thread, $this->user );
				}
			}
			$this->output->redirect( $this->title->getLocalURL() );
		} elseif ( $this->methodApplies( 'mark_as_read' ) ) {
			$ids = explode( ',', $this->request->getVal( 'lqt_operand' ) );
			foreach ( $ids as $id ) {
				if ( $id == 'all' ) {
					NewMessages::markAllReadByUser( $this->user );
				} else {
					$tmp_thread = Threads::withId( (int)$id );
					if ( $tmp_thread ) {
						NewMessages::markThreadAsReadByUser( $tmp_thread, $this->user );
					}
				}
			}
			$query = 'lqt_method=undo_mark_as_read&lqt_operand=' . implode( ',', $ids );
			$this->output->redirect( $this->title->getLocalURL( $query ) );
		} elseif ( $this->methodApplies( 'undo_mark_as_read' ) ) {
			$ids = explode( ',', $this->request->getVal( 'lqt_operand', '' ) );
			$this->output->addHTML( $this->getUndoButton( $ids ) );
		}
	}

	/**
	 * @return bool
	 */
	public function show() {
		$pager = new LqtNewMessagesPager( $this->user );
		$this->messagesInfo = $pager->getThreads();

		if ( !$this->messagesInfo ) {
			$this->output->addWikiMsg( 'lqt-no-new-messages' );
			return false;
		}

		$this->output->addModuleStyles( $pager->getModuleStyles() );

		$this->output->addHTML( $this->getReadAllButton() );
		$this->output->addHTML( $pager->getNavigationBar() );

		$this->output->addHTML( '<table class="lqt-new-messages"><tbody>' );

		foreach ( $this->messagesInfo as $info ) {
			// It turns out that with lqtviews composed of threads from various talkpages,
			// each thread is going to have a different article... this is pretty ugly.
			$thread = $info['top'];
			$this->highlightThreads = $info['posts'];
			$this->article = $thread->article();

			$this->showWrappedThread( $thread );
		}

		$this->output->addHTML( '</tbody></table>' );

		$this->output->addHTML( $pager->getNavigationBar() );

		return false;
	}

	public function showWrappedThread( $t ) {
		$read_button = $this->htmlForReadButton(
			wfMessage( 'lqt-read-message' )->text(),
			wfMessage( 'lqt-read-message-tooltip' )->text(),
			'lqt_newmessages_read_button',
			$this->highlightThreads );

		// Left-hand column read button and context link to the full thread.
		$topmostThread = $t->topmostThread();
		$title = clone $topmostThread->getTitle();
		$title->setFragment( '#' . $t->getAnchorName() );

		// Make sure it points to the right page. The Pager seems to use the DB
		// representation of a timestamp for its offset field, odd.
		$dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();
		$offset = (int)wfTimestamp( TS_UNIX, $topmostThread->modified() ) + 1;
		$offset = $dbr->timestamp( $offset );
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();

		$contextLink = $linkRenderer->makeKnownLink(
			$title,
			new HtmlArmor( wfMessage( 'lqt-newmessages-context' )->parse() ),
			[],
			[ 'offset' => $offset ]
		);

		$talkpageLink = $linkRenderer->makeLink( $topmostThread->getTitle() );
		$talkpageInfo = wfMessage( 'lqt-newmessages-from' )
			->rawParams( $talkpageLink )->parseAsBlock();

		$leftColumn = Xml::tags( 'p', null, $read_button ) .
						Xml::tags( 'p', null, $contextLink ) .
						$talkpageInfo;
		$leftColumn = Xml::tags( 'td', [ 'class' => 'lqt-newmessages-left' ],
									$leftColumn );
		$html = "<tr>$leftColumn<td class='lqt-newmessages-right'>";
		$this->output->addHTML( $html );

		$mustShowThreads = $this->highlightThreads;

		$this->showThread( $t, 1, 1, [ 'mustShowThreads' => $mustShowThreads ] );
		$this->output->addModules( 'ext.liquidThreads.newMessages' );
		$this->output->addHTML( "</td></tr>" );
	}
}
