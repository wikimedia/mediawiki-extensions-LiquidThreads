<?php

class ApiThreadAction extends ApiEditPage {
	public function execute() {
		$params = $this->extractRequestParams();

		$allowedAllActions = [ 'markread' ];
		$actionsAllowedOnNonLqtPage = [ 'markread', 'markunread' ];
		$action = $params['threadaction'];

		// Pull the threads from the parameters
		$threads = [];
		if ( !empty( $params['thread'] ) ) {
			foreach ( $params['thread'] as $thread ) {
				$threadObj = null;
				if ( is_numeric( $thread ) ) {
					$threadObj = Threads::withId( $thread );
				} elseif ( $thread == 'all' &&
						in_array( $action, $allowedAllActions ) ) {
					$threads = [ 'all' ];
				} else {
					$title = Title::newFromText( $thread );
					$article = new Article( $title, 0 );
					$threadObj = Threads::withRoot( $article );
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
		global $wgTitle;
		if ( !$wgTitle instanceof Title ) {
			$wgTitle = Title::newFromText( 'LiquidThreads has a bug' );
		}

		// Find the appropriate module
		$actions = $this->getActions();

		$method = $actions[$action];

		call_user_func_array( [ $this, $method ], [ $threads, $params ] );
	}

	public function actionMarkRead( $threads, $params ) {
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
					'text' => wfMessage( 'lqt-newmessages-n' )->numParams( 0 )->text(),
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
			$newMessagesCount = NewMessages::newMessageCount( $user, DB_MASTER );
			$msgNewMessages = 'lqt-newmessages-n';
			// Only bother to put this on the last threadaction
			$result[count( $result ) - 1]['unreadlink'] = [
				'href' => SpecialPage::getTitleFor( 'NewMessages' )->getLocalURL(),
				'text' => wfMessage( $msgNewMessages )->numParams( $newMessagesCount )->text(),
				'active' => $newMessagesCount > 0,
			];
		}

		$this->getResult()->setIndexedTagName( $result, 'thread' );
		$this->getResult()->addValue( null, 'threadactions', $result );
	}

	public function actionMarkUnread( $threads, $params ) {
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

	public function actionSplit( $threads, $params ) {
		if ( count( $threads ) > 1 ) {
			$this->dieWithError( 'apierror-liquidthreads-onlyone', 'too-many-threads' );
		} elseif ( count( $threads ) < 1 ) {
			$this->dieWithError(
				'apierror-liquidthreads-threadneeded', 'no-specified-threads' );
		}

		$thread = array_pop( $threads );

		$errors = $thread->title()->getUserPermissionsErrors( 'lqt-split', $this->getUser() );
		if ( $errors ) {
			$this->dieStatus( $this->errorArrayToStatus( $errors ) );
		}

		if ( $thread->isTopmostThread() ) {
			$this->dieWithError( 'apierror-liquidthreads-alreadytop', 'already-top-level' );
		}

		$title = null;
		$article = $thread->article();
		if ( empty( $params['subject'] ) ||
			!Thread::validateSubject( $params['subject'], $title, null, $article )
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

	public function actionMerge( $threads, $params ) {
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
			$title = Title::newFromText( $newParent );
			$article = new Article( $title, 0 );
			$newParent = Threads::withRoot( $article );
		}

		$errors = $newParent->title()->getUserPermissionsErrors( 'lqt-merge', $this->getUser() );
		if ( $errors ) {
			$this->dieStatus( $this->errorArrayToStatus( $errors ) );
		}

		if ( !$newParent ) {
			$this->dieWithError( 'apierror-liquidthreads-badparent', 'invalid-parent-thread' );
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

	public function actionNewThread( $threads, $params ) {
		// Validate talkpage parameters
		if ( !count( $params['talkpage'] ) ) {
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
		$subjectOk = Thread::validateSubject( $subject, $title, null, $talkpage );

		if ( !$subjectOk ) {
			$this->dieWithError( 'apierror-liquidthreads-badsubject', 'invalid-subject' );
		}
		$article = new Article( $title, 0 );

		// Check for text
		if ( empty( $params['text'] ) ) {
			$this->dieWithError( 'apierror-liquidthreads-notext', 'no-text' );
		}
		$text = $params['text'];

		// Generate or pull summary
		$summary = wfMessage( 'lqt-newpost-summary', $subject )->inContentLanguage()->text();
		if ( !empty( $params['reason'] ) ) {
			$summary = $params['reason'];
		}

		$signature = null;
		if ( isset( $params['signature'] ) ) {
			$signature = $params['signature'];
		}

		// Inform hooks what we're doing
		LqtHooks::$editTalkpage = $talkpage;
		LqtHooks::$editArticle = $article;
		LqtHooks::$editThread = null;
		LqtHooks::$editType = 'new';
		LqtHooks::$editAppliesTo = null;

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

	public function actionEdit( $threads, $params ) {
		if ( count( $threads ) > 1 ) {
			$this->dieWithError( 'apierror-liquidthreads-onlyone', 'too-many-threads' );
		} elseif ( count( $threads ) < 1 ) {
			$this->dieWithError(
				'apierror-liquidthreads-threadneeded', 'no-specified-threads' );
		}

		$thread = array_pop( $threads );
		$talkpage = $thread->article();

		$bump = isset( $params['bump'] ) ? $params['bump'] : null;

		// Validate subject
		$subjectOk = true;
		if ( !empty( $params['subject'] ) ) {
			$subject = $params['subject'];
			$title = null;
			$subjectOk = empty( $subject ) ||
				Thread::validateSubject( $subject, $title, null, $talkpage );
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
		LqtHooks::$editTalkpage = $talkpage;
		LqtHooks::$editArticle = $article;
		LqtHooks::$editThread = $thread;
		LqtHooks::$editType = 'edit';
		LqtHooks::$editAppliesTo = null;

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

	public function actionReply( $threads, $params ) {
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

		$bump = isset( $params['bump'] ) ? $params['bump'] : null;

		// Generate/pull summary
		$summary = wfMessage( 'lqt-reply-summary', $replyTo->subject(),
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
		LqtHooks::$editTalkpage = $talkpage;
		LqtHooks::$editArticle = $article;
		LqtHooks::$editThread = null;
		LqtHooks::$editType = 'reply';
		LqtHooks::$editAppliesTo = $replyTo;

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
	 * @suppress SecurityCheck-XSS Due to $oldOutputText
	 * @param Thread $thread
	 * @return string
	 */
	protected function renderThreadPostAction( $thread ) {
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

	public function actionSetSubject( $threads, $params ) {
		// Validate thread parameter
		if ( count( $threads ) > 1 ) {
			$this->dieWithError( 'apierror-liquidthreads-onlyone', 'too-many-threads' );
		} elseif ( count( $threads ) < 1 ) {
			$this->dieWithError(
				'apierror-liquidthreads-threadneeded', 'no-specified-threads' );
		}
		$thread = array_pop( $threads );

		$errors = $thread->title()->getUserPermissionsErrors( 'edit', $this->getUser() );
		if ( $errors ) {
			$this->dieStatus( $this->errorArrayToStatus( $errors ) );
		}

		// Validate subject
		if ( empty( $params['subject'] ) ) {
			$this->dieWithError( [ 'apierror-missingparam', 'subject' ] );
		}

		$talkpage = $thread->article();

		$subject = $params['subject'];
		$title = null;
		$subjectOk = Thread::validateSubject( $subject, $title, null, $talkpage );

		if ( !$subjectOk ) {
			$this->dieWithError( 'apierror-liquidthreads-badsubject', 'invalid-subject' );
		}

		$reason = null;

		if ( isset( $params['reason'] ) ) {
			$reason = $params['reason'];
		}

		if ( $thread->dbVersion->subject() !== $subject ) {
			$thread->setSubject( $subject );
			$thread->commitRevision( Threads::CHANGE_EDITED_SUBJECT, $thread, $reason );
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

	public function actionSetSortkey( $threads, $params ) {
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

		$errors = $thread->title()->getUserPermissionsErrors( 'edit', $this->getUser() );
		if ( $errors ) {
			$this->dieStatus( $this->errorArrayToStatus( $errors ) );
		}

		$thread->setSortkey( $ts );
		$thread->commitRevision( Threads::CHANGE_ADJUSTED_SORTKEY, null, $reason );

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

	public function actionAddReaction( $threads, $params ) {
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

	public function actionDeleteReaction( $threads, $params ) {
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

	public function actionInlineEditForm( $threads, $params ) {
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

		$output = LqtView::getInlineEditForm( $talkpage, $method, $operand );

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
}
