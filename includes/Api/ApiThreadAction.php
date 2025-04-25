<?php

namespace MediaWiki\Extension\LiquidThreads\Api;

use LqtDispatch;
use LqtView;
use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiEditPage;
use MediaWiki\Api\ApiMain;
use MediaWiki\Extension\LiquidThreads\Hooks;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\Article;
use MediaWiki\Request\DerivativeRequest;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use NewMessages;
use Thread;
use Threads;

class ApiThreadAction extends ApiEditPage {
	public function execute() {
		$params = $this->extractRequestParams();

		$allowedAllActions = [ 'markread' ];
		$actionsAllowedOnNonLqtPage = [ 'markread', 'markunread' ];
		$action = $params['threadaction'];

		// Pull the threads from the parameters
		$threads = [];
		if ( !empty( $params['thread'] ) ) {
			$wikiPageFactory = MediaWikiServices::getInstance()->getWikiPageFactory();
			foreach ( $params['thread'] as $thread ) {
				$threadObj = null;
				if ( is_numeric( $thread ) ) {
					$threadObj = Threads::withId( $thread );
				} elseif ( $thread == 'all' &&
						in_array( $action, $allowedAllActions ) ) {
					$threads = [ 'all' ];
				} else {
					$threadObj = Threads::withRoot(
						$wikiPageFactory->newFromTitle(
							Title::newFromText( $thread )
						)
					);
				}

				if ( $threadObj instanceof Thread ) {
					$threads[] = $threadObj;

					if ( !in_array( $action, $actionsAllowedOnNonLqtPage )
						&& !LqtDispatch::isLqtPage( $threadObj->getTitle() )
					) {
						$articleTitleDBKey = $threadObj->getTitle()->getDBkey();
						$this->dieWithError( [
							'lqt-not-a-liquidthreads-page',
							wfEscapeWikiText( $articleTitleDBKey )
						] );
					}
				}
			}
		}

		// HACK: Somewhere $wgOut->parse() is called, which breaks
		// if a Title isn't set. So set one. See bug 71081.
		// phpcs:ignore MediaWiki.Usage.DeprecatedGlobalVariables.Deprecated$wgTitle
		global $wgTitle;
		if ( !$wgTitle instanceof Title ) {
			$wgTitle = Title::newFromText( 'LiquidThreads has a bug' );
		}

		// Find the appropriate module
		$actions = $this->getActions();

		$method = $actions[$action];

		$this->$method( $threads, $params );
	}

	/**
	 * @param (Thread|string)[] $threads
	 * @param array $params
	 */
	public function actionMarkRead( array $threads, $params ) {
		$user = $this->getUser();

		$result = [];

		if ( in_array( 'all', $threads ) ) {
			NewMessages::markAllReadByUser( $user );
			$result[] = [
				'result' => 'Success',
				'action' => 'markread',
				'threads' => 'all',
				'unreadlink' => [
					'href' => SpecialPage::getTitleFor( 'NewMessages' )->getLocalURL(),
					'text' => $this->msg( 'lqt-newmessages-n' )->numParams( 0 )->text(),
					'active' => false,
				]
			];
		} else {
			foreach ( $threads as $t ) {
				NewMessages::markThreadAsReadByUser( $t, $user );
				$result[] = [
					'result' => 'Success',
					'action' => 'markread',
					'id' => $t->id(),
					'title' => $t->title()->getPrefixedText()
				];
			}
			$newMessagesCount = NewMessages::newMessageCount( $user, DB_PRIMARY );
			$msgNewMessages = 'lqt-newmessages-n';
			// Only bother to put this on the last threadaction
			$result[count( $result ) - 1]['unreadlink'] = [
				'href' => SpecialPage::getTitleFor( 'NewMessages' )->getLocalURL(),
				'text' => $this->msg( $msgNewMessages )->numParams( $newMessagesCount )->text(),
				'active' => $newMessagesCount > 0,
			];
		}

		$this->getResult()->setIndexedTagName( $result, 'thread' );
		$this->getResult()->addValue( null, 'threadactions', $result );
	}

	/**
	 * @param Thread[] $threads
	 * @param array $params
	 */
	public function actionMarkUnread( array $threads, $params ) {
		$result = [];

		$user = $this->getUser();
		foreach ( $threads as $t ) {
			NewMessages::markThreadAsUnreadByUser( $t, $user );

			$result[] = [
				'result' => 'Success',
				'action' => 'markunread',
				'id' => $t->id(),
				'title' => $t->title()->getPrefixedText()
			];
		}

		$this->getResult()->setIndexedTagName( $result, 'thread' );
		$this->getResult()->addValue( null, 'threadaction', $result );
	}

	/**
	 * @param Thread[] $threads
	 * @param array $params
	 */
	public function actionSplit( array $threads, $params ) {
		if ( count( $threads ) > 1 ) {
			$this->dieWithError( 'apierror-liquidthreads-onlyone', 'too-many-threads' );
		} elseif ( count( $threads ) < 1 ) {
			$this->dieWithError(
				'apierror-liquidthreads-threadneeded', 'no-specified-threads' );
		}

		$thread = array_pop( $threads );

		$status = $this->getPermissionManager()
			->getPermissionStatus( 'lqt-split', $this->getUser(), $thread->title() );
		if ( !$status->isGood() ) {
			$this->dieStatus( $status );
		}

		if ( $thread->isTopmostThread() ) {
			$this->dieWithError( 'apierror-liquidthreads-alreadytop', 'already-top-level' );
		}

		$title = null;
		$article = $thread->article();
		if ( empty( $params['subject'] ) ||
			!Thread::validateSubject(
				$params['subject'],
				$this->getUser(),
				$title,
				null,
				$article
			)
		) {
			$this->dieWithError( 'apierror-liquidthreads-nosubject', 'no-valid-subject' );
		}

		$subject = $params['subject'];

		// Pull a reason, if applicable.
		$reason = '';
		if ( !empty( $params['reason'] ) ) {
			$reason = $params['reason'];
		}

		// Check if they specified a sortkey
		$sortkey = null;
		if ( !empty( $params['sortkey'] ) ) {
			$ts = $params['sortkey'];
			$ts = wfTimestamp( TS_MW, $ts );

			$sortkey = $ts;
		}

		// Do the split
		$thread->split( $subject, $reason, $sortkey );

		$result = [];
		$result[] = [
			'result' => 'Success',
			'action' => 'split',
			'id' => $thread->id(),
			'title' => $thread->title()->getPrefixedText(),
			'newsubject' => $subject,
		];

		$this->getResult()->setIndexedTagName( $result, 'thread' );
		$this->getResult()->addValue( null, 'threadaction', $result );
	}

	/**
	 * @param Thread[] $threads
	 * @param array $params
	 */
	public function actionMerge( array $threads, $params ) {
		if ( count( $threads ) < 1 ) {
			$this->dieWithError( 'apihelp-liquidthreads-threadneeded', 'no-specified-threads' );
		}

		if ( empty( $params['newparent'] ) ) {
			$this->dieWithError( 'apierror-liquidthreads-noparent', 'no-parent-thread' );
		}

		$newParent = $params['newparent'];
		if ( is_numeric( $newParent ) ) {
			$newParent = Threads::withId( $newParent );
		} else {
			$newParent = Threads::withRoot(
				MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle(
					Title::newFromText( $newParent )
				)
			);
		}

		if ( !$newParent ) {
			$this->dieWithError( 'apierror-liquidthreads-badparent', 'invalid-parent-thread' );
		}

		$status = $this->getPermissionManager()
			->getPermissionStatus( 'lqt-merge', $this->getUser(), $newParent->title() );
		if ( !$status->isGood() ) {
			$this->dieStatus( $status );
		}

		// Pull a reason, if applicable.
		$reason = '';
		if ( !empty( $params['reason'] ) ) {
			$reason = $params['reason'];
		}

		$result = [];

		foreach ( $threads as $thread ) {
			$thread->moveToParent( $newParent, $reason );
			$result[] = [
				'result' => 'Success',
				'action' => 'merge',
				'id' => $thread->id(),
				'title' => $thread->title()->getPrefixedText(),
				'new-parent-id' => $newParent->id(),
				'new-parent-title' => $newParent->title()->getPrefixedText(),
				'new-ancestor-id' => $newParent->topmostThread()->id(),
				'new-ancestor-title' => $newParent->topmostThread()->title()->getPrefixedText(),
			];
		}

		$this->getResult()->setIndexedTagName( $result, 'thread' );
		$this->getResult()->addValue( null, 'threadaction', $result );
	}

	/**
	 * @param Thread[] $threads
	 * @param array $params
	 */
	public function actionNewThread( $threads, $params ) {
		// T206901: Validate talkpage parameter
		if ( $params['talkpage'] === null ) {
			$this->dieWithError( [ 'apierror-missingparam', 'talkpage' ] );
		}

		$talkpageTitle = Title::newFromText( $params['talkpage'] );

		if ( !$talkpageTitle || !LqtDispatch::isLqtPage( $talkpageTitle ) ) {
			$this->dieWithError( 'apierror-liquidthreads-invalidtalkpage', 'invalid-talkpage' );
		}
		$talkpage = new Article( $talkpageTitle, 0 );

		// Check if we can post.
		$user = $this->getUser();
		if ( Thread::canUserPost( $user, $talkpage ) !== true ) {
			$this->dieWithError(
				'apierror-liquidthreads-talkpageprotected', 'talkpage-protected' );
		}

		// Validate subject, generate a title
		if ( empty( $params['subject'] ) ) {
			$this->dieWithError( [ 'apierror-missingparam', 'subject' ] );
		}

		$subject = $params['subject'];
		$title = null;
		$subjectOk = Thread::validateSubject( $subject, $user, $title, null, $talkpage );

		if ( !$subjectOk ) {
			$this->dieWithError( 'apierror-liquidthreads-badsubject', 'invalid-subject' );
		}
		// @phan-suppress-next-line PhanTypeMismatchArgumentNullable T240141
		$article = new Article( $title, 0 );

		// Check for text
		if ( empty( $params['text'] ) ) {
			$this->dieWithError( 'apierror-liquidthreads-notext', 'no-text' );
		}
		$text = $params['text'];

		// Generate or pull summary
		$summary = $this->msg( 'lqt-newpost-summary', $subject )->inContentLanguage()->text();
		if ( !empty( $params['reason'] ) ) {
			$summary = $params['reason'];
		}

		$signature = null;
		if ( isset( $params['signature'] ) ) {
			$signature = $params['signature'];
		}

		// Inform hooks what we're doing
		Hooks::$editTalkpage = $talkpage;
		Hooks::$editArticle = $article;
		Hooks::$editThread = null;
		Hooks::$editType = 'new';
		Hooks::$editAppliesTo = null;

		$token = $params['token'];

		// All seems in order. Construct an API edit request
		$requestData = [
			'action' => 'edit',
			'title' => $title->getPrefixedText(),
			'text' => $text,
			'summary' => $summary,
			'token' => $token,
			'basetimestamp' => wfTimestampNow(),
			'minor' => 0,
			'format' => 'json',
		];

		if ( $user->isAllowed( 'bot' ) ) {
			$requestData['bot'] = true;
		}
		$editReq = new DerivativeRequest( $this->getRequest(), $requestData, true );
		$internalApi = new ApiMain( $editReq, true );
		$internalApi->execute();

		$editResult = $internalApi->getResult()->getResultData();

		if ( $editResult['edit']['result'] != 'Success' ) {
			$result = [ 'result' => 'EditFailure', 'details' => $editResult ];
			$this->getResult()->addValue( null, $this->getModuleName(), $result );
			return;
		}

		$articleId = $editResult['edit']['pageid'];

		$article->getTitle()->resetArticleID( $articleId );
		$title->resetArticleID( $articleId );

		$thread = LqtView::newPostMetadataUpdates(
			$user,
			[
				'root' => $article,
				'talkpage' => $talkpage,
				'subject' => $subject,
				'signature' => $signature,
				'summary' => $summary,
				'text' => $text,
			] );

		$result = [
			'result' => 'Success',
			'thread-id' => $thread->id(),
			'thread-title' => $title->getPrefixedText(),
			'modified' => $thread->modified(),
		];

		if ( !empty( $params['render'] ) ) {
			$result['html'] = $this->renderThreadPostAction( $thread );
		}

		$result = [ 'thread' => $result ];

		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}

	/**
	 * @param Thread[] $threads
	 * @param array $params
	 */
	public function actionEdit( array $threads, $params ) {
		if ( count( $threads ) > 1 ) {
			$this->dieWithError( 'apierror-liquidthreads-onlyone', 'too-many-threads' );
		} elseif ( count( $threads ) < 1 ) {
			$this->dieWithError(
				'apierror-liquidthreads-threadneeded', 'no-specified-threads' );
		}

		$thread = array_pop( $threads );
		$talkpage = $thread->article();

		$bump = $params['bump'] ?? null;

		// Validate subject
		$subjectOk = true;
		if ( !empty( $params['subject'] ) ) {
			$subject = $params['subject'];
			$title = null;
			$subjectOk = Thread::validateSubject(
				$subject,
				$this->getUser(),
				$title,
				null,
				$talkpage
			);
		} else {
			$subject = $thread->subject();
		}

		if ( !$subjectOk ) {
			$this->dieWithError( 'apierror-liquidthreads-badsubject', 'invalid-subject' );
		}

		// Check for text
		if ( empty( $params['text'] ) ) {
			$this->dieWithError( 'apierror-liquidthreads-notext', 'no-text' );
		}
		$text = $params['text'];

		$summary = '';
		if ( !empty( $params['reason'] ) ) {
			$summary = $params['reason'];
		}

		$article = $thread->root();
		$title = $article->getTitle();

		$signature = null;
		if ( isset( $params['signature'] ) ) {
			$signature = $params['signature'];
		}

		// Inform hooks what we're doing
		Hooks::$editTalkpage = $talkpage;
		Hooks::$editArticle = $article;
		Hooks::$editThread = $thread;
		Hooks::$editType = 'edit';
		Hooks::$editAppliesTo = null;

		$token = $params['token'];

		// All seems in order. Construct an API edit request
		$requestData = [
			'action' => 'edit',
			'title' => $title->getPrefixedText(),
			'text' => $text,
			'summary' => $summary,
			'token' => $token,
			'minor' => 0,
			'basetimestamp' => wfTimestampNow(),
			'format' => 'json',
		];

		if ( $this->getUser()->isAllowed( 'bot' ) ) {
			$requestData['bot'] = true;
		}

		$editReq = new DerivativeRequest( $this->getRequest(), $requestData, true );
		$internalApi = new ApiMain( $editReq, true );
		$internalApi->execute();

		$editResult = $internalApi->getResult()->getResultData();

		if ( $editResult['edit']['result'] != 'Success' ) {
			$result = [ 'result' => 'EditFailure', 'details' => $editResult ];
			$this->getResult()->addValue( null, $this->getModuleName(), $result );
			return;
		}

		$thread = LqtView::editMetadataUpdates(
			[
				'root' => $article,
				'thread' => $thread,
				'subject' => $subject,
				'signature' => $signature,
				'summary' => $summary,
				'text' => $text,
				'bump' => $bump,
			] );

		$result = [
			'result' => 'Success',
			'thread-id' => $thread->id(),
			'thread-title' => $title->getPrefixedText(),
			'modified' => $thread->modified(),
		];

		if ( !empty( $params['render'] ) ) {
			$result['html'] = $this->renderThreadPostAction( $thread );
		}

		$result = [ 'thread' => $result ];

		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}

	/**
	 * @param Thread[] $threads
	 * @param array $params
	 */
	public function actionReply( array $threads, $params ) {
		// Validate thread parameter
		if ( count( $threads ) > 1 ) {
			$this->dieWithError( 'apierror-liquidthreads-onlyone', 'too-many-threads' );
		} elseif ( count( $threads ) < 1 ) {
			$this->dieWithError(
				'apierror-liquidthreads-threadneeded', 'no-specified-threads' );
		}
		$replyTo = array_pop( $threads );

		// Check if we can reply to that thread.
		$user = $this->getUser();
		$perm_result = $replyTo->canUserReply( $user );
		if ( $perm_result !== true ) {
			// Messages: apierror-liquidthreads-noreplies-talkpage,
			// apierror-liquidthreads-noreplies-thread
			$this->dieWithError(
				"apierror-liquidthreads-noreplies-{$perm_result}", "{$perm_result}-protected"
			);
		}

		// Validate text parameter
		if ( empty( $params['text'] ) ) {
			$this->dieWithError( 'apierror-liquidthreads-notext', 'no-text' );
		}

		$text = $params['text'];

		$bump = $params['bump'] ?? null;

		// Generate/pull summary
		$summary = $this->msg( 'lqt-reply-summary', $replyTo->subject(),
				$replyTo->title()->getPrefixedText() )->inContentLanguage()->text();

		if ( !empty( $params['reason'] ) ) {
			$summary = $params['reason'];
		}

		$signature = null;
		if ( isset( $params['signature'] ) ) {
			$signature = $params['signature'];
		}

		// Grab data from parent
		$talkpage = $replyTo->article();

		// Generate a reply title.
		$title = Threads::newReplyTitle( $replyTo, $user );
		$article = new Article( $title, 0 );

		// Inform hooks what we're doing
		Hooks::$editTalkpage = $talkpage;
		Hooks::$editArticle = $article;
		Hooks::$editThread = null;
		Hooks::$editType = 'reply';
		Hooks::$editAppliesTo = $replyTo;

		// Pull token in
		$token = $params['token'];

		// All seems in order. Construct an API edit request
		$requestData = [
			'action' => 'edit',
			'title' => $title->getPrefixedText(),
			'text' => $text,
			'summary' => $summary,
			'token' => $token,
			'basetimestamp' => wfTimestampNow(),
			'minor' => 0,
			'format' => 'json',
		];

		if ( $user->isAllowed( 'bot' ) ) {
			$requestData['bot'] = true;
		}

		$editReq = new DerivativeRequest( $this->getRequest(), $requestData, true );
		$internalApi = new ApiMain( $editReq, true );
		$internalApi->execute();

		$editResult = $internalApi->getResult()->getResultData();

		if ( $editResult['edit']['result'] != 'Success' ) {
			$result = [ 'result' => 'EditFailure', 'details' => $editResult ];
			$this->getResult()->addValue( null, $this->getModuleName(), $result );
			return;
		}

		$articleId = $editResult['edit']['pageid'];
		$article->getTitle()->resetArticleID( $articleId );
		$title->resetArticleID( $articleId );

		$thread = LqtView::replyMetadataUpdates(
			$user,
			[
				'root' => $article,
				'replyTo' => $replyTo,
				'signature' => $signature,
				'summary' => $summary,
				'text' => $text,
				'bump' => $bump,
			] );

		$result = [
			'action' => 'reply',
			'result' => 'Success',
			'thread-id' => $thread->id(),
			'thread-title' => $title->getPrefixedText(),
			'parent-id' => $replyTo->id(),
			'parent-title' => $replyTo->title()->getPrefixedText(),
			'ancestor-id' => $replyTo->topmostThread()->id(),
			'ancestor-title' => $replyTo->topmostThread()->title()->getPrefixedText(),
			'modified' => $thread->modified(),
		];

		if ( !empty( $params['render'] ) ) {
			$result['html'] = $this->renderThreadPostAction( $thread );
		}

		$result = [ 'thread' => $result ];

		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}

	/**
	 * @param Thread $thread
	 * @return string
	 */
	protected function renderThreadPostAction( Thread $thread ) {
		$thread = $thread->topmostThread();

		// Set up OutputPage
		$out = $this->getOutput();
		$oldOutputText = $out->getHTML();
		$out->clearHTML();

		// Setup
		$article = $thread->root();
		$title = $article->getTitle();
		$user = $this->getUser();
		$request = $this->getRequest();
		$view = new LqtView( $out, $article, $title, $user, $request );

		$view->showThread( $thread );

		$result = $out->getHTML();
		$out->clearHTML();
		$out->addHTML( $oldOutputText );

		return $result;
	}

	/**
	 * @param Thread[] $threads
	 * @param array $params
	 */
	public function actionSetSubject( array $threads, $params ) {
		// Validate thread parameter
		if ( count( $threads ) > 1 ) {
			$this->dieWithError( 'apierror-liquidthreads-onlyone', 'too-many-threads' );
		} elseif ( count( $threads ) < 1 ) {
			$this->dieWithError(
				'apierror-liquidthreads-threadneeded', 'no-specified-threads' );
		}
		$thread = array_pop( $threads );

		$status = $this->getPermissionManager()
			->getPermissionStatus( 'edit', $this->getUser(), $thread->title() );
		if ( !$status->isGood() ) {
			$this->dieStatus( $status );
		}

		// Validate subject
		if ( empty( $params['subject'] ) ) {
			$this->dieWithError( [ 'apierror-missingparam', 'subject' ] );
		}

		$talkpage = $thread->article();

		$subject = $params['subject'];
		$title = null;
		$subjectOk = Thread::validateSubject(
			$subject,
			$this->getUser(),
			$title,
			null,
			$talkpage
		);

		if ( !$subjectOk ) {
			$this->dieWithError( 'apierror-liquidthreads-badsubject', 'invalid-subject' );
		}

		$reason = null;

		if ( isset( $params['reason'] ) ) {
			$reason = $params['reason'];
		}

		if ( $thread->dbVersion->subject() !== $subject ) {
			$thread->setSubject( $subject );
			$thread->commitRevision(
				Threads::CHANGE_EDITED_SUBJECT,
				$this->getUser(),
				$thread,
				$reason
			);
		}

		$result = [
			'action' => 'setsubject',
			'result' => 'success',
			'thread-id' => $thread->id(),
			'thread-title' => $thread->title()->getPrefixedText(),
			'new-subject' => $subject,
		];

		$result = [ 'thread' => $result ];

		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}

	/**
	 * @param Thread[] $threads
	 * @param array $params
	 */
	public function actionSetSortkey( array $threads, $params ) {
		// First check for threads
		if ( !count( $threads ) ) {
			$this->dieWithError( 'apihelp-liquidthreads-threadneeded', 'no-specified-threads' );
		}

		// Validate timestamp
		if ( empty( $params['sortkey'] ) ) {
			$this->dieWithError( 'apierror-liquidthreads-badsortkey', 'invalid-sortkey' );
		}

		$ts = $params['sortkey'];

		if ( $ts == 'now' ) {
			$ts = wfTimestampNow();
		}

		$ts = wfTimestamp( TS_MW, $ts );

		if ( !$ts ) {
			$this->dieWithError( 'apierror-liquidthreads-badsortkey', 'invalid-sortkey' );
		}

		$reason = null;

		if ( isset( $params['reason'] ) ) {
			$reason = $params['reason'];
		}

		$thread = array_pop( $threads );

		$status = $this->getPermissionManager()
			->getPermissionStatus( 'edit', $this->getUser(), $thread->title() );
		if ( !$status->isGood() ) {
			$this->dieStatus( $status );
		}

		$thread->setSortkey( $ts );
		$thread->commitRevision(
			Threads::CHANGE_ADJUSTED_SORTKEY,
			$this->getUser(),
			null,
			$reason
		);

		$result = [
			'action' => 'setsortkey',
			'result' => 'success',
			'thread-id' => $thread->id(),
			'thread-title' => $thread->title()->getPrefixedText(),
			'new-sortkey' => $ts,
		];

		$result = [ 'thread' => $result ];

		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}

	/**
	 * @param Thread[] $threads
	 * @param array $params
	 */
	public function actionAddReaction( array $threads, $params ) {
		if ( !count( $threads ) ) {
			$this->dieWithError( 'apihelp-liquidthreads-threadneeded', 'no-specified-threads' );
		}

		$this->checkUserRightsAny( 'lqt-react' );

		$required = [ 'type', 'value' ];

		if ( count( array_diff( $required, array_keys( $params ) ) ) ) {
			$this->dieWithError( 'apierror-liquidthreads-badreaction', 'missing-parameter' );
		}

		$result = [];

		foreach ( $threads as $thread ) {
			$thread->addReaction( $this->getUser(), $params['type'], $params['value'] );

			$result[] = [
				'result' => 'Success',
				'action' => 'addreaction',
				'id' => $thread->id(),
			];
		}

		$this->getResult()->setIndexedTagName( $result, 'thread' );
		$this->getResult()->addValue( null, 'threadaction', $result );
	}

	/**
	 * @param Thread[] $threads
	 * @param array $params
	 */
	public function actionDeleteReaction( array $threads, $params ) {
		if ( !count( $threads ) ) {
			$this->dieWithError( 'apihelp-liquidthreads-threadneeded', 'no-specified-threads' );
		}

		$user = $this->getUser();
		$this->checkUserRightsAny( 'lqt-react' );

		$required = [ 'type', 'value' ];

		if ( count( array_diff( $required, array_keys( $params ) ) ) ) {
			$this->dieWithError( 'apierror-liquidthreads-badreaction', 'missing-parameter' );
		}

		$result = [];

		foreach ( $threads as $thread ) {
			$thread->deleteReaction( $user, $params['type'] );

			$result[] = [
				'result' => 'Success',
				'action' => 'deletereaction',
				'id' => $thread->id(),
			];
		}

		$this->getResult()->setIndexedTagName( $result, 'thread' );
		$this->getResult()->addValue( null, 'threadaction', $result );
	}

	/**
	 * @param Thread[] $threads
	 * @param array $params
	 */
	public function actionInlineEditForm( array $threads, $params ) {
		$method = $talkpage = $operand = null;

		if ( isset( $params['method'] ) ) {
			$method = $params['method'];
		}

		if ( isset( $params['talkpage'] ) ) {
			$talkpage = $params['talkpage'];
		}

		if ( $talkpage ) {
			$talkpage = new Article( Title::newFromText( $talkpage ), 0 );
		} else {
			$talkpage = null;
		}

		if ( count( $threads ) ) {
			$operand = $threads[0];
			$operand = $operand->id();
		}

		$output = LqtView::getInlineEditForm( $talkpage, $method, $operand, $this->getUser() );

		$result = [ 'inlineeditform' => [ 'html' => $output ] ];

		/* FIXME
		$result['resources'] = LqtView::getJSandCSS();
		$result['resources']['messages'] = LqtView::exportJSLocalisation();
		*/

		$this->getResult()->addValue( null, 'threadaction', $result );
	}

	public function getActions() {
		return [
			'markread' => 'actionMarkRead',
			'markunread' => 'actionMarkUnread',
			'split' => 'actionSplit',
			'merge' => 'actionMerge',
			'reply' => 'actionReply',
			'newthread' => 'actionNewThread',
			'setsubject' => 'actionSetSubject',
			'setsortkey' => 'actionSetSortkey',
			'edit' => 'actionEdit',
			'addreaction' => 'actionAddReaction',
			'deletereaction' => 'actionDeleteReaction',
			'inlineeditform' => 'actionInlineEditForm',
		];
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 * @return array
	 */
	protected function getExamplesMessages() {
		return [
		];
	}

	public function needsToken() {
		return 'csrf';
	}

	public function getAllowedParams() {
		return [
			'thread' => [
				ApiBase::PARAM_ISMULTI => true,
			],
			'talkpage' => null,
			'threadaction' => [
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_TYPE => array_keys( $this->getActions() ),
			],
			'token' => null,
			'subject' => null,
			'reason' => null,
			'newparent' => null,
			'text' => null,
			'render' => null,
			'bump' => null,
			'sortkey' => null,
			'signature' => null,
			'type' => null,
			'value' => null,
			'method' => null,
			'operand' => null,
		];
	}

	public function mustBePosted() {
		return true;
	}

	public function isWriteMode() {
		return true;
	}

	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Special:MyLanguage/API:Threadaction';
	}
}
