<?php
/**
 * @file
 * @ingroup LiquidThreads
 * @author David McCabe <davemccabe@gmail.com>
 * @license GPL-2.0-or-later
 */

use MediaWiki\Content\TextContent;
use MediaWiki\Context\RequestContext;
use MediaWiki\EditPage\EditPage;
use MediaWiki\Extension\LiquidThreads\Hooks;
use MediaWiki\Html\Html;
use MediaWiki\Linker\Linker;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Parser\Sanitizer;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Request\WebRequest;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\Xml\Xml;
use Wikimedia\IPUtils;

class LqtView {
	/**
	 * @var Article|null
	 */
	public $article;

	/**
	 * @var OutputPage
	 */
	public $output;

	/**
	 * @var User
	 */
	public $user;

	/**
	 * @var Title|null
	 */
	public $title;

	/**
	 * @var WebRequest
	 */
	public $request;

	/** @var int h1, h2, h3, etc. */
	protected $headerLevel = 2;
	/** @var array */
	protected $user_colors;
	/** @var int */
	protected $user_color_index;

	/** @var int */
	public $threadNestingLevel = 0;

	public function __construct( $output, $article, $title, $user, $request ) {
		$this->article = $article;
		$this->output = $output;
		$this->user = $user;
		$this->title = $title;
		$this->request = $request;
		$this->user_colors = [];
		$this->user_color_index = 1;
	}

	/*************************
	 * (1) linking to liquidthreads pages and
	 * (2) figuring out what page you're on and what you need to do.
	 */

	/**
	 * @param string $method
	 * @param Thread $thread
	 * @return bool
	 */
	public function methodAppliesToThread( $method, Thread $thread ) {
		return $this->request->getVal( 'lqt_method' ) == $method &&
			$this->request->getVal( 'lqt_operand' ) == $thread->id();
	}

	/**
	 * @param string $method
	 * @return bool
	 */
	public function methodApplies( $method ) {
		return $this->request->getVal( 'lqt_method' ) == $method;
	}

	public static function permalinkUrl(
		Thread $thread,
		$method = null,
		$operand = null,
		array $uquery = [],
		$relative = true
	) {
		[ $title, $query ] = self::permalinkData( $thread, $method, $operand );

		$query = array_merge( $query, $uquery );

		$queryString = wfArrayToCgi( $query );

		if ( $relative ) {
			return $title->getLocalUrl( $queryString );
		} else {
			return $title->getCanonicalUrl( $queryString );
		}
	}

	/**
	 * Gets an array of (title, query-parameters) for a permalink
	 * @param Thread $thread
	 * @param string|null $method
	 * @param string|null $operand
	 * @return array
	 */
	public static function permalinkData( Thread $thread, $method = null, $operand = null ) {
		$query = [];

		if ( $method ) {
			$query['lqt_method'] = $method;
		}
		if ( $operand ) {
			$query['lqt_operand'] = $operand;
		}

		$root = $thread->root();
		if ( !$root ) {
			// XXX Perhaps this should be replaced with a checked exception
			throw new RuntimeException( "No root in " . __METHOD__ );
		}

		return [ $root->getTitle(), $query ];
	}

	/**
	 * This is used for action=history so that the history tab works, which is
	 * why we break the lqt_method paradigm.
	 *
	 * @param Thread $thread
	 * @param string[] $query
	 * @param bool $relative
	 * @return string
	 */
	public static function permalinkUrlWithQuery( Thread $thread, array $query, $relative = true ) {
		return self::permalinkUrl( $thread, null, null, $query, $relative );
	}

	public static function permalink( Thread $thread, $text = null, $method = null, $operand = null,
					$linker = null, $attribs = [], $uquery = [] ) {
		[ $title, $query ] = self::permalinkData( $thread, $method, $operand );

		$query = array_merge( $query, $uquery );

		return MediaWikiServices::getInstance()->getLinkRenderer()->makeLink(
			$title,
			$text,
			$attribs,
			$query
		);
	}

	/**
	 * @param Thread $thread
	 * @param string $contextType
	 * @throws Exception
	 * @return array
	 */
	public static function linkInContextData( Thread $thread, $contextType = 'page' ) {
		$query = [];

		if ( $contextType == 'page' ) {
			$title = clone $thread->getTitle();

			$dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();
			$offset = $thread->topmostThread()->sortkey();
			$offset = (int)wfTimestamp( TS_UNIX, $offset ) + 1;
			$offset = $dbr->timestamp( $offset );
			$query['offset'] = $offset;
		} else {
			$title = clone $thread->title();
		}

		$query['lqt_mustshow'] = $thread->id();

		$title->setFragment( '#' . $thread->getAnchorName() );

		return [ $title, $query ];
	}

	/**
	 * @param thread $thread
	 * @param string $contextType
	 * @param string|null $text
	 * @return mixed
	 */
	public static function linkInContext( Thread $thread, $contextType = 'page', $text = null ) {
		[ $title, $query ] = self::linkInContextData( $thread, $contextType );

		return MediaWikiServices::getInstance()->getLinkRenderer()->makeLink(
			$title,
			$text ?? Threads::stripHTML( $thread->formattedSubject() ),
			[],
			$query
		);
	}

	public static function linkInContextFullURL( Thread $thread, $contextType = 'page' ) {
		[ $title, $query ] = self::linkInContextData( $thread, $contextType );

		return $title->getFullURL( $query );
	}

	public static function linkInContextCanonicalURL( Thread $thread, $contextType = 'page' ) {
		[ $title, $query ] = self::linkInContextData( $thread, $contextType );

		return $title->getCanonicalURL( $query );
	}

	/**
	 * @param Thread $thread
	 * @param ThreadRevision $revision
	 * @return array
	 */
	public static function diffQuery( Thread $thread, ThreadRevision $revision ) {
		$changed_thread = $revision->getChangeObject();
		$curr_rev_id = $changed_thread->rootRevision();

		$revLookup = MediaWikiServices::getInstance()->getRevisionLookup();
		$curr_rev_record = $revLookup->getRevisionById( $curr_rev_id );

		$oldid = '';
		if ( $curr_rev_record ) {
			$prev_rev_record = $revLookup->getPreviousRevision( $curr_rev_record );
			$oldid = $prev_rev_record ? $prev_rev_record->getId() : '';
		}

		$query = [
			'lqt_method' => 'diff',
			'diff' => $curr_rev_id,
			'oldid' => $oldid
		];

		return $query;
	}

	public static function diffPermalinkURL( Thread $thread, ThreadRevision $revision ) {
		$services = MediaWikiServices::getInstance();

		$query = self::diffQuery( $thread, $revision );
		return $services->getUrlUtils()->expand( self::permalinkUrl( $thread, null, null, $query ), PROTO_RELATIVE );
	}

	public static function diffPermalink( Thread $thread, $text, ThreadRevision $revision ) {
		$query = self::diffQuery( $thread, $revision );
		return self::permalink( $thread, $text, null, null, null, [], $query );
	}

	public static function talkpageLink( $title, $text = null, $method = null, $operand = null,
		$includeFragment = true, $attribs = [],
		$options = [], $perpetuateOffset = true
	) {
		[ $title, $query ] = self::talkpageLinkData(
			$title, $method, $operand,
			$includeFragment,
			$perpetuateOffset
		);

		$linkRenderer = MediaWikiServices::getInstance()
			->getLinkRendererFactory()
			->createFromLegacyOptions( $options );
		return $linkRenderer->makeLink( $title, $text, $attribs, $query );
	}

	/**
	 * @param Title $title
	 * @param string|null $method
	 * @param Thread|null $operand
	 * @param bool $includeFragment
	 * @param bool|WebRequest $perpetuateOffset
	 * @return array
	 */
	public static function talkpageLinkData( $title, $method = null, $operand = null,
		$includeFragment = true, $perpetuateOffset = true
	) {
		global $wgRequest;
		$query = [];

		if ( $method ) {
			$query['lqt_method'] = $method;
		}

		if ( $operand ) {
			$query['lqt_operand'] = $operand->id();
		}

		$oldid = $wgRequest->getVal( 'oldid', null );

		if ( $oldid !== null ) {
			// this is an immensely ugly hack to make editing old revisions work.
			$query['oldid'] = $oldid;
		}

		$request = $perpetuateOffset;
		if ( $request === true ) {
			global $wgRequest;
			$request = $wgRequest;
		}

		if ( $perpetuateOffset ) {
			$offset = $request->getVal( 'offset' );

			if ( $offset ) {
				$query['offset'] = $offset;
			}
		}

		// Add fragment if appropriate.
		if ( $operand && $includeFragment ) {
			$title->setFragment( $operand->getAnchorName() );
		}

		return [ $title, $query ];
	}

	/**
	 * If you want $perpetuateOffset to perpetuate from a specific request,
	 * pass that instead of true
	 * @param Title $title
	 * @param string|null $method
	 * @param Thread|null $operand
	 * @param bool $includeFragment
	 * @param bool|WebRequest $perpetuateOffset
	 * @return string
	 */
	public static function talkpageUrl( $title, $method = null, $operand = null,
		$includeFragment = true, $perpetuateOffset = true
	) {
		[ $title, $query ] =
			self::talkpageLinkData( $title, $method, $operand, $includeFragment,
						$perpetuateOffset );

		return $title->getLinkUrl( $query );
	}

	/*************************************************************
	 * Editing methods (here be dragons)						  *
	 * Forget dragons: This section distorts the rest of the code *
	 * like a star bending spacetime around itself.				  *
	 */

	/**
	 * Return an HTML form element whose value is gotten from the request.
	 * @todo Figure out a clean way to expand this to other forms.
	 * @param string $name
	 * @param string $as
	 * @return string
	 */
	public function perpetuate( $name, $as = 'hidden' ) {
		$value = $this->request->getVal( $name, '' );
		if ( $as == 'hidden' ) {
			return Html::hidden( $name, $value );
		}

		return '';
	}

	/**
	 * @param Thread $thread
	 */
	public function showReplyProtectedNotice( Thread $thread ) {
		$log_url = SpecialPage::getTitleFor( 'Log' )->getLocalURL(
			"type=protect&user=&page={$thread->title()->getPrefixedURL()}" );
		$link = '<a href="' . $log_url . '">' .
			wfMessage( 'lqt_protectedfromreply_link' )->escaped() . '</a>';
		$this->output->addHTML( '<p>' . wfMessage( 'lqt_protectedfromreply' )
			->rawParams( $link )->escaped() );
	}

	public function doInlineEditForm() {
		$method = $this->request->getVal( 'lqt_method' );
		$operand = $this->request->getVal( 'lqt_operand' );

		$thread = Threads::withId( intval( $operand ) );

		// Yuck.
		// phpcs:ignore MediaWiki.Usage.DeprecatedGlobalVariables.Deprecated$wgTitle
		global $wgOut, $wgRequest, $wgTitle;
		$oldOut = $wgOut;
		$oldRequest = $wgRequest;
		$oldTitle = $wgTitle;
		// And override the main context too... (T143889)
		$context = RequestContext::getMain();
		$oldCOut = $context->getOutput();
		$oldCRequest = $context->getRequest();
		$oldCTitle = $context->getTitle();
		$context->setOutput( $this->output );
		$context->setRequest( $this->request );
		$context->setTitle( $this->title );
		$wgOut = $this->output;
		$wgRequest = $this->request;
		$wgTitle = $this->title;

		$hookResult = MediaWikiServices::getInstance()->getHookContainer()->run( 'LiquidThreadsDoInlineEditForm',
					[
						$thread,
						$this->request,
						&$this->output
					] );

		if ( !$hookResult ) {
			// Handled by a hook.
		} elseif ( $method == 'reply' ) {
			$this->showReplyForm( $thread );
		} elseif ( $method == 'talkpage_new_thread' ) {
			$this->showNewThreadForm( $this->article );
		} elseif ( $method == 'edit' ) {
			$this->showPostEditingForm( $thread );
		} else {
			throw new LogicException( "Invalid thread method $method" );
		}

		$wgOut = $oldOut;
		$wgRequest = $oldRequest;
		$wgTitle = $oldTitle;
		$context->setOutput( $oldCOut );
		$context->setRequest( $oldCRequest );
		$context->setTitle( $oldCTitle );

		$this->output->setArticleBodyOnly( true );
	}

	/**
	 * Workaround for bug 27887 caused by r82686
	 * @param FauxRequest $request FauxRequest object to have session data injected into.
	 */
	public static function fixFauxRequestSession( $request ) {
		// This is sometimes called before session_start (bug 28826).
		if ( !isset( $_SESSION ) ) {
			return;
		}

		foreach ( $_SESSION as $k => $v ) {
			$request->setSessionData( $k, $v );
		}
	}

	/**
	 * @param Article|null $talkpage
	 * @param string $method
	 * @param int|null $operand
	 * @param User $user
	 * @return string
	 * @throws Exception
	 */
	public static function getInlineEditForm( $talkpage, $method, $operand, User $user ) {
		$req = new RequestContext;
		$output = $req->getOutput();
		$request = new FauxRequest( [] );

		// Workaround for loss of session data when using FauxRequest
		global $wgRequest;
		self::fixFauxRequestSession( $request );

		$title = null;

		if ( $talkpage ) {
			$title = $talkpage->getTitle();
		} elseif ( $operand ) {
			$thread = Threads::withId( $operand );
			if ( $thread ) {
				$talkpage = $thread->article();
				$title = $talkpage->getTitle();
			} else {
				throw new LogicException( "Cannot get title" );
			}
		}

		// @phan-suppress-next-line PhanTypeMismatchArgumentNullable
		$output->setTitle( $title );
		$request->setVal( 'lqt_method', $method );
		$request->setVal( 'lqt_operand', $operand );

		$view = new LqtView( $output, $talkpage, $title, $user, $request );

		$view->doInlineEditForm();

		foreach ( $request->getSessionArray() ?? [] as $k => $v ) {
			$wgRequest->setSessionData( $k, $v );
		}

		return $output->getHTML();
	}

	/**
	 * @param Article $talkpage
	 */
	public function showNewThreadForm( $talkpage ) {
		$submitted_nonce = $this->request->getVal( 'lqt_nonce' );
		if ( $this->request->wasPosted() && !$this->checkNonce( $submitted_nonce ) ) {
			return;
		}

		if ( Thread::canUserPost( $this->user, $this->article ) !== true ) {
			$this->output->addWikiMsg( 'lqt-protected-newthread' );
			return;
		}
		$subject = $this->request->getVal( 'lqt_subject_field' ) ?? false;

		$t = null;

		$subjectOk = Thread::validateSubject(
			$subject,
			$this->user,
			$t,
			null,
			$this->article
		);
		if ( !$subjectOk ) {
			try {
				$t = $this->newThreadTitle( $subject );
			} catch ( Exception $excep ) {
				$t = $this->scratchTitle();
			}
		}

		$html = Xml::openElement( 'div',
			[ 'class' => 'lqt-edit-form lqt-new-thread' ] );
		$this->output->addHTML( $html );

		// @phan-suppress-next-line PhanTypeMismatchArgumentNullable
		$article = new Article( $t, 0 );

		Hooks::$editTalkpage = $talkpage;
		Hooks::$editArticle = $article;
		Hooks::$editThread = null;
		Hooks::$editType = 'new';
		Hooks::$editAppliesTo = null;

		$e = new EditPage( $article );
		$e->setContextTitle( $article->getTitle() );
		$hookContainer = MediaWikiServices::getInstance()->getHookContainer();
		$hookContainer->run( 'LiquidThreadsShowNewThreadForm', [ &$e, $talkpage ] );

		global $wgRequest;
		// Quietly force a preview if no subject has been specified.
		if ( !$subjectOk ) {
			// Dirty hack to prevent saving from going ahead
			$wgRequest->setVal( 'wpPreview', true );

			if ( $this->request->wasPosted() ) {
				if ( !$subject ) {
					$msg = 'lqt_empty_subject';
				} else {
					$msg = 'lqt_invalid_subject';
				}

				$e->editFormPageTop .=
					Xml::tags( 'div', [ 'class' => 'error' ],
						wfMessage( $msg )->parseAsBlock() );
			}
		}

		$e->suppressIntro = true;
		$e->editFormTextBeforeContent .=
			$this->perpetuate( 'lqt_method', 'hidden' ) .
			$this->perpetuate( 'lqt_operand', 'hidden' ) .
			Html::hidden( 'lqt_nonce', MWCryptRand::generateHex( 32 ) );

		$e->mShowSummaryField = false;

		$summary = wfMessage( 'lqt-newpost-summary', $subject )->inContentLanguage()->text();
		$wgRequest->setVal( 'wpSummary', $summary );

		[ $signatureEditor, $signatureHTML ] = $this->getSignatureEditor( $this->user );

		$e->editFormTextAfterContent .=
			$signatureEditor;
		$e->previewTextAfterContent .=
			Xml::tags( 'p', null, $signatureHTML );

		$e->editFormTextBeforeContent .= $this->getSubjectEditor( '', $subject );

		$hookContainer->run( 'LiquidThreadsAfterShowNewThreadForm', [ &$e, $talkpage ] );

		$e->edit();

		if ( $e->didSave ) {
			$signature = $this->request->getVal( 'wpLqtSignature', null );

			$info =
				[
					'talkpage' => $talkpage,
					'text' => $e->textbox1,
					'summary' => $e->summary,
					'signature' => $signature,
					'root' => $article,
					'subject' => $subject,
				];

			$hookContainer->run( 'LiquidThreadsSaveNewThread',
					[ &$info, &$e, &$talkpage ] );

			$thread = self::newPostMetadataUpdates( $this->user, $info );
			self::consumeNonce( $submitted_nonce );
		}

		if ( $this->output->getRedirect() != '' ) {
			$redirectTitle = clone $talkpage->getTitle();
			if ( !empty( $thread ) ) {
				$redirectTitle->setFragment( '#' . $this->anchorName( $thread ) );
			}
			$this->output->redirect( $this->title->getLocalURL() );
		}

		$this->output->addHTML( '</div>' );
	}

	/**
	 * @param Thread $thread
	 */
	public function showReplyForm( Thread $thread ) {
		global $wgRequest;

		$submitted_nonce = $this->request->getVal( 'lqt_nonce' );
		if ( $this->request->wasPosted() && !$this->checkNonce( $submitted_nonce ) ) {
			return;
		}

		$perm_result = $thread->canUserReply( $this->user, 'quick' );
		if ( $perm_result !== true ) {
			$this->showReplyProtectedNotice( $thread );
			return;
		}

		$html = Xml::openElement( 'div',
					[ 'class' => 'lqt-reply-form lqt-edit-form' ] );
		$this->output->addHTML( $html );

		try {
			$t = $this->newReplyTitle( null, $thread );
		} catch ( Exception $excep ) {
			$t = $this->scratchTitle();
		}

		$article = new Article( $t, 0 );
		$talkpage = $thread->article();

		Hooks::$editTalkpage = $talkpage;
		Hooks::$editArticle = $article;
		Hooks::$editThread = $thread;
		Hooks::$editType = 'reply';
		Hooks::$editAppliesTo = $thread;

		$e = new EditPage( $article );
		$e->setContextTitle( $article->getTitle() );

		$e->mShowSummaryField = false;

		$reply_subject = $thread->subject();
		$reply_title = $thread->title()->getPrefixedText();
		$summary = wfMessage(
			'lqt-reply-summary',
			$reply_subject,
			$reply_title
		)->inContentLanguage()->text();
		$wgRequest->setVal( 'wpSummary', $summary );

		// Add an offset so it works if it's on the wrong page.
		$dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();
		$offset = wfTimestamp( TS_UNIX, $thread->topmostThread()->sortkey() );
		$offset++;
		$offset = $dbr->timestamp( $offset );

		$e->suppressIntro = true;
		$e->editFormTextBeforeContent .=
			$this->perpetuate( 'lqt_method', 'hidden' ) .
			$this->perpetuate( 'lqt_operand', 'hidden' ) .
			Html::hidden( 'lqt_nonce', MWCryptRand::generateHex( 32 ) ) .
			Html::hidden( 'offset', $offset ) .
			Html::hidden( 'wpMinorEdit', '' );

		[ $signatureEditor, $signatureHTML ] = $this->getSignatureEditor( $this->user );

		$e->editFormTextAfterContent .=
			$signatureEditor;
		$e->previewTextAfterContent .=
			Xml::tags( 'p', null, $signatureHTML );

		$wgRequest->setVal( 'wpWatchThis', false );

		$hookContainer = MediaWikiServices::getInstance()->getHookContainer();
		$hookContainer->run( 'LiquidThreadsShowReplyForm', [ &$e, $thread ] );

		$e->edit();

		if ( $e->didSave ) {
			$bump = !$this->request->getCheck( 'wpBumpThread' ) ||
				$this->request->getBool( 'wpBumpThread' );
			$signature = $this->request->getVal( 'wpLqtSignature', null );

			$info = [
					'replyTo' => $thread,
					'text' => $e->textbox1,
					'summary' => $e->summary,
					'bump' => $bump,
					'signature' => $signature,
					'root' => $article,
				];

			$hookContainer->run( 'LiquidThreadsSaveReply',
					[ &$info, &$e, &$thread ] );

			$newThread = self::replyMetadataUpdates( $this->user, $info );
			self::consumeNonce( $submitted_nonce );
		}

		if ( $this->output->getRedirect() != '' ) {
			$redirectTitle = clone $talkpage->getTitle();
			if ( !empty( $newThread ) ) {
				$redirectTitle->setFragment( '#' . $this->anchorName( $newThread ) );
			}
			$this->output->redirect( $this->title->getLocalURL() );
		}

		$this->output->addHTML( '</div>' );
	}

	/**
	 * @param Thread $thread
	 */
	public function showPostEditingForm( Thread $thread ) {
		$submitted_nonce = $this->request->getVal( 'lqt_nonce' );
		if ( $this->request->wasPosted() && !$this->checkNonce( $submitted_nonce ) ) {
			return;
		}

		$html = Xml::openElement( 'div',
			[ 'class' => 'lqt-edit-form' ] );
		$this->output->addHTML( $html );

		$subject = $this->request->getVal( 'lqt_subject_field', '' );

		if ( !$subject ) {
			$subject = $thread->subject() ?? '';
		}

		$t = null;
		$subjectOk = Thread::validateSubject(
			$subject,
			$this->user,
			$t,
			$thread->superthread(),
			$this->article
		);
		if ( !$subjectOk ) {
			$subject = false;
		}

		$article = $thread->root();
		$talkpage = $thread->article();

		MediaWikiServices::getInstance()->getHookContainer()
			->run( 'LiquidThreadsEditFormContent', [ $thread, &$article, $talkpage ] );

		Hooks::$editTalkpage = $talkpage;
		Hooks::$editArticle = $article;
		Hooks::$editThread = $thread;
		Hooks::$editType = 'edit';
		Hooks::$editAppliesTo = $thread;

		$e = new EditPage( $article );
		$e->setContextTitle( $article->getTitle() );

		global $wgRequest;
		// Quietly force a preview if no subject has been specified.
		if ( !$subjectOk ) {
			// Dirty hack to prevent saving from going ahead
			$wgRequest->setVal( 'wpPreview', true );

			if ( $this->request->wasPosted() ) {
				$e->editFormPageTop .=
					Xml::tags( 'div', [ 'class' => 'error' ],
						wfMessage( 'lqt_invalid_subject' )->parse() );
			}
		}

		// Add an offset so it works if it's on the wrong page.
		$dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();
		$offset = wfTimestamp( TS_UNIX, $thread->topmostThread()->sortkey() );
		$offset++;
		$offset = $dbr->timestamp( $offset );

		$e->suppressIntro = true;
		$e->editFormTextBeforeContent .=
			$this->perpetuate( 'lqt_method', 'hidden' ) .
			$this->perpetuate( 'lqt_operand', 'hidden' ) .
			Html::hidden( 'lqt_nonce', MWCryptRand::generateHex( 32 ) ) .
			Html::hidden( 'offset', $offset );

		[ $signatureEditor, $signatureHTML ] = $this->getSignatureEditor( $thread );

		$e->editFormTextAfterContent .=
			$signatureEditor;
		$e->previewTextAfterContent .=
			Xml::tags( 'p', null, $signatureHTML );

		if ( $thread->isTopmostThread() ) {
			$e->editFormTextBeforeContent .=
				$this->getSubjectEditor( $thread->subject(), $subject );
		}

		$e->edit();

		if ( $e->didSave ) {
			$bump = !$this->request->getCheck( 'wpBumpThread' ) ||
				$this->request->getBool( 'wpBumpThread' );
			$signature = $this->request->getVal( 'wpLqtSignature', null );

			self::editMetadataUpdates(
				[
					'thread' => $thread,
					'text' => $e->textbox1,
					'summary' => $e->summary,
					'bump' => $bump,
					'subject' => $subject,
					'signature' => $signature,
					'root' => $article,
				]
			);
			self::consumeNonce( $submitted_nonce );
		}

		if ( $this->output->getRedirect() != '' ) {
			$redirectTitle = clone $talkpage->getTitle();
			$redirectTitle->setFragment( '#' . $this->anchorName( $thread ) );
			$this->output->redirect( $this->title->getLocalURL() );
		}

		$this->output->addHTML( '</div>' );
	}

	/**
	 * @param Thread $thread
	 */
	public function showSummarizeForm( Thread $thread ) {
		$submitted_nonce = $this->request->getVal( 'lqt_nonce' );
		if ( $this->request->wasPosted() && !$this->checkNonce( $submitted_nonce ) ) {
			return;
		}

		if ( $thread->summary() ) {
			$article = $thread->summary();
			$summarizeMsg = 'lqt-update-summary-intro';
		} else {
			$t = $this->newSummaryTitle( $thread );
			$article = new Article( $t, 0 );
			$summarizeMsg = 'lqt-summarize-intro';
		}

		$html = Xml::openElement( 'div',
			[ 'class' => 'lqt-edit-form lqt-summarize-form' ] );
		$this->output->addHTML( $html );

		$this->output->addWikiMsg( $summarizeMsg );

		$talkpage = $thread->article();

		Hooks::$editTalkpage = $talkpage;
		Hooks::$editArticle = $article;
		Hooks::$editThread = $thread;
		Hooks::$editType = 'summarize';
		Hooks::$editAppliesTo = $thread;

		// @phan-suppress-next-line PhanTypeMismatchArgumentNullable
		$e = new EditPage( $article );
		$e->setContextTitle( $article->getTitle() );

		// Add an offset so it works if it's on the wrong page.
		$dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();
		$offset = wfTimestamp( TS_UNIX, $thread->topmostThread()->sortkey() );
		$offset++;
		$offset = $dbr->timestamp( $offset );

		$e->suppressIntro = true;
		$e->editFormTextBeforeContent .=
			$this->perpetuate( 'lqt_method', 'hidden' ) .
			$this->perpetuate( 'lqt_operand', 'hidden' ) .
			Html::hidden( 'lqt_nonce', MWCryptRand::generateHex( 32 ) ) .
			Html::hidden( 'offset', $offset );

		$e->edit();

		if ( $e->didSave ) {
			$bump = !$this->request->getCheck( 'wpBumpThread' ) ||
				$this->request->getBool( 'wpBumpThread' );

			self::summarizeMetadataUpdates(
				[
					'thread' => $thread,
					'article' => $article,
					'summary' => $e->summary,
					'bump' => $bump,
				]
			);
			self::consumeNonce( $submitted_nonce );
		}

		if ( $this->output->getRedirect() != '' ) {
			$redirectTitle = clone $talkpage->getTitle();
			$redirectTitle->setFragment( '#' . $this->anchorName( $thread ) );
			$this->output->redirect( $this->title->getLocalURL() );
		}

		$this->output->addHTML( '</div>' );
	}

	/**
	 * Check a nonce token on HTTP POST during a thread submission
	 *
	 * @param string $token
	 * @return bool Whether the user nonce token is unused or no token was provided
	 */
	public function checkNonce( $token ) {
		if ( !$token ) {
			return true;
		}

		// Primary data-center cluster cache
		$cache = MediaWikiServices::getInstance()->getObjectCacheFactory()->getLocalClusterInstance();
		$nonce_key = $cache->makeKey( 'lqt-nonce', $token, $this->user->getName() );

		if ( $cache->get( $nonce_key ) ) {
			$this->output->redirect( $this->article->getTitle()->getLocalURL() );
			return false;
		}

		return true;
	}

	/**
	 * Consume a nonce token on HTTP POST during a thread submission
	 *
	 * @param string $token
	 * @return bool Whether the user nonce token was acquired or no token was provided
	 */
	public function consumeNonce( $token ) {
		if ( !$token ) {
			return true;
		}

		// Primary data-center cluster cache
		$cache = MediaWikiServices::getInstance()->getObjectCacheFactory()->getLocalClusterInstance();
		$nonce_key = $cache->makeKey( 'lqt-nonce', $token, $this->user->getName() );

		return $cache->add( $nonce_key, 1, $cache::TTL_HOUR );
	}

	/**
	 * @param string $db_subject
	 * @param string|false $subject
	 * @return string HTML
	 */
	public function getSubjectEditor( $db_subject, $subject ) {
		if ( $subject === false ) {
			$subject = $db_subject;
		}

		$subject_label = wfMessage( 'lqt_subject' )->text();

		$attr = [ 'tabindex' => 1 ];

		return Xml::inputLabel( $subject_label, 'lqt_subject_field',
				'lqt_subject_field', 60, $subject, $attr ) .
			Xml::element( 'br' );
	}

	public function getSignatureEditor( $from ) {
		$signatureText = $this->request->getVal( 'wpLqtSignature', null );

		if ( $signatureText === null ) {
			if ( $from instanceof User ) {
				$signatureText = self::getUserSignature( $from );
			} elseif ( $from instanceof Thread ) {
				$signatureText = $from->signature() ?? '';
			} else {
				$signatureText = '';
			}
		}

		$signatureHTML = self::parseSignature( $signatureText );

		// Signature edit box
		$signaturePreview = Xml::tags(
			'span',
			[
				'class' => 'lqt-signature-preview',
				'style' => 'display: none;'
			],
			$signatureHTML
		);
		$signatureEditBox = Xml::input(
			'wpLqtSignature', 45, $signatureText,
			[ 'class' => 'lqt-signature-edit' ]
		);

		$signatureEditor = $signaturePreview . $signatureEditBox;

		return [ $signatureEditor, $signatureHTML ];
	}

	public static function replyMetadataUpdates( User $user, $data = [] ) {
		$requiredFields = [ 'replyTo', 'root', 'text' ];

		foreach ( $requiredFields as $f ) {
			if ( !isset( $data[$f] ) ) {
				throw new RuntimeException( "Missing required field $f" );
			}
		}

		$signature = $data['signature'] ?? self::getUserSignature( $user );

		$summary = $data['summary'] ?? '';

		$replyTo = $data['replyTo'];
		$root = $data['root'];
		$bump = !empty( $data['bump'] );

		$subject = $replyTo->subject();
		$talkpage = $replyTo->article();

		$thread = Thread::create(
			$root, $talkpage, $user, $replyTo, Threads::TYPE_NORMAL, $subject,
			$summary, $bump, $signature
		);

		MediaWikiServices::getInstance()->getHookContainer()
			->run( 'LiquidThreadsAfterReplyMetadataUpdates', [ &$thread ] );

		return $thread;
	}

	public static function summarizeMetadataUpdates( $data = [] ) {
		$requiredFields = [ 'thread', 'article', 'summary' ];

		foreach ( $requiredFields as $f ) {
			if ( !isset( $data[$f] ) ) {
				throw new RuntimeException( "Missing required field $f" );
			}
		}

		$thread = $data["thread"];
		$article = $data["article"];
		$summary = $data["summary"];

		$bump = $data["bump"] ?? null;

		$user = RequestContext::getMain()->getUser(); // Need to inject
		$thread->setSummary( $article );
		$thread->commitRevision(
			Threads::CHANGE_EDITED_SUMMARY, $user, $thread, $summary, $bump );

		return $thread;
	}

	public static function editMetadataUpdates( $data = [] ) {
		$requiredFields = [ 'thread', 'text', 'summary' ];

		foreach ( $requiredFields as $f ) {
			if ( !isset( $data[$f] ) ) {
				throw new RuntimeException( "Missing required field $f" );
			}
		}

		$thread = $data['thread'];

		// Use a separate type if the content is blanked.
		$type = strlen( trim( $data['text'] ) )
				? Threads::CHANGE_EDITED_ROOT
				: Threads::CHANGE_ROOT_BLANKED;

		if ( isset( $data['signature'] ) ) {
			$thread->setSignature( $data['signature'] );
		}

		$bump = !empty( $data['bump'] );

		$user = RequestContext::getMain()->getUser(); // Need to inject
		// Add the history entry.
		$thread->commitRevision( $type, $user, $thread, $data['summary'], $bump );

		// Update subject if applicable.
		if ( $thread->isTopmostThread() && !empty( $data['subject'] ) &&
				$data['subject'] != $thread->subject() ) {
			$thread->setSubject( $data['subject'] );
			$thread->commitRevision( Threads::CHANGE_EDITED_SUBJECT,
						$user, $thread, $data['summary'] );
		}

		return $thread;
	}

	public static function newPostMetadataUpdates( User $user, $data ) {
		$requiredFields = [ 'talkpage', 'root', 'text', 'subject' ];

		foreach ( $requiredFields as $f ) {
			if ( !isset( $data[$f] ) ) {
				throw new RuntimeException( "Missing required field $f" );
			}
		}

		$signature = $data['signature'] ?? self::getUserSignature( $user );

		$summary = $data['summary'] ?? '';

		$talkpage = $data['talkpage'];
		'@phan-var Article $talkpage';
		$root = $data['root'];
		$subject = $data['subject'];

		$thread = Thread::create(
			$root, $talkpage, $user, null,
			Threads::TYPE_NORMAL, $subject,
			$summary, null, $signature
		);

		MediaWikiServices::getInstance()->getHookContainer()
			->run( 'LiquidThreadsAfterNewPostMetadataUpdates', [ &$thread ] );

		return $thread;
	}

	/**
	 * @param string $subject
	 * @return Title
	 */
	public function newThreadTitle( $subject ) {
		return Threads::newThreadTitle( $subject, $this->article );
	}

	/**
	 * @param Thread $thread
	 * @return Title
	 */
	public function newSummaryTitle( Thread $thread ) {
		return Threads::newSummaryTitle( $thread );
	}

	/**
	 * @param mixed $unused
	 * @param Thread $thread
	 * @return Title
	 */
	public function newReplyTitle( $unused, Thread $thread ) {
		return Threads::newReplyTitle( $thread, $this->user );
	}

	/**
	 * @return Title
	 */
	public function scratchTitle() {
		return Title::makeTitle( NS_LQT_THREAD, MWCryptRand::generateHex( 32 ) );
	}

	/**
	 * @param Thread $thread
	 * @return array Example return value:
	 * 	array (
	 * 		edit => array( 'label'	 => 'Edit',
	 * 					'href'	  => 'http...',
	 * 					'enabled' => false ),
	 * 		reply => array( 'label'	  => 'Reply',
	 * 					'href'	  => 'http...',
	 * 					'enabled' => true )
	 * 	)
	 */
	public function threadCommands( Thread $thread ) {
		$commands = [];
		$isLqtPage = LqtDispatch::isLqtPage( $thread->getTitle() );

		$history_url = self::permalinkUrlWithQuery( $thread, [ 'action' => 'history' ] );
		$commands['history'] = [
			'label' => wfMessage( 'history_short' )->parse(),
			'href' => $history_url,
			'enabled' => true
		];

		if ( $thread->isHistorical() ) {
			return [];
		}
		$services = MediaWikiServices::getInstance();
		$user_can_edit = $services->getPermissionManager()
			->quickUserCan( 'edit', $this->user, $thread->root()->getTitle() );
		$editMsg = $user_can_edit ? 'edit' : 'viewsource';

		if ( $isLqtPage ) {
			$commands['edit'] = [
				'label' => wfMessage( $editMsg )->parse(),
				'href' => $this->talkpageUrl(
					$this->title,
					'edit', $thread,
					true, /* include fragment */
					$this->request
				),
				'enabled' => true
			];
		}

		if ( $this->user->isAllowed( 'delete' ) ) {
			$delete_url = $thread->title()->getLocalURL( 'action=delete' );
			$deleteMsg = $thread->type() == Threads::TYPE_DELETED ? 'lqt_undelete' : 'delete';

			$commands['delete'] = [
				'label' => wfMessage( $deleteMsg )->parse(),
				'href' => $delete_url,
				'enabled' => true
			];
		}

		if ( $isLqtPage ) {
			if ( !$thread->isTopmostThread() && $this->user->isAllowed( 'lqt-split' ) ) {
				$splitUrl = SpecialPage::getTitleFor( 'SplitThread',
					$thread->title()->getPrefixedText() )->getLocalURL();
				$commands['split'] = [
					'label' => wfMessage( 'lqt-thread-split' )->parse(),
					'href' => $splitUrl,
					'enabled' => true
				];
			}

			if ( $this->user->isAllowed( 'lqt-merge' ) ) {
				$mergeParams = $_GET;
				$mergeParams['lqt_merge_from'] = $thread->id();

				unset( $mergeParams['title'] );

				$mergeUrl = $this->title->getLocalURL( wfArrayToCgi( $mergeParams ) );
				$label = wfMessage( 'lqt-thread-merge' )->parse();

				$commands['merge'] = [
					'label' => $label,
					'href' => $mergeUrl,
					'enabled' => true
				];
			}
		}

		$commands['link'] = [
			'label' => wfMessage( 'lqt_permalink' )->parse(),
			'href' => $thread->title()->getLocalURL(),
			'enabled' => true,
			'showlabel' => true,
			'tooltip' => wfMessage( 'lqt_permalink' )->parse()
		];

		$services->getHookContainer()->run( 'LiquidThreadsThreadCommands', [ $thread, &$commands ] );

		return $commands;
	}

	/**
	 * Commands for the bottom.
	 * @param Thread $thread
	 * @return array[]
	 */
	public function threadMajorCommands( Thread $thread ) {
		$isLqtPage = LqtDispatch::isLqtPage( $thread->getTitle() );

		if ( $thread->isHistorical() ) {
			// No links for historical threads.
			$history_url = self::permalinkUrlWithQuery( $thread,
					[ 'action' => 'history' ] );
			$commands = [];

			$commands['history'] = [
				'label' => wfMessage( 'history_short' )->parse(),
				'href' => $history_url,
				'enabled' => true
			];

			return $commands;
		}

		$commands = [];

		if ( $isLqtPage ) {
			if ( $this->user->isAllowed( 'lqt-merge' ) &&
				$this->request->getCheck( 'lqt_merge_from' )
			) {
				$srcThread = Threads::withId( $this->request->getInt( 'lqt_merge_from' ) );
				$par = $srcThread->title()->getPrefixedText();
				$mergeTitle = SpecialPage::getTitleFor( 'MergeThread', $par );
				$mergeUrl = $mergeTitle->getLocalURL( 'dest=' . $thread->id() );
				$label = wfMessage( 'lqt-thread-merge-to' )->parse();

				$commands['merge-to'] = [
					'label' => $label,
					'href' => $mergeUrl,
					'enabled' => true,
					'tooltip' => $label
				];
			}

			if ( $thread->canUserReply( $this->user, 'quick' ) === true ) {
				$commands['reply'] = [
					'label' => wfMessage( 'lqt_reply' )->parse(),
					'href' => $this->talkpageUrl( $this->title, 'reply', $thread,
						true /* include fragment */, $this->request ),
					'enabled' => true,
					'showlabel' => 1,
					'tooltip' => wfMessage( 'lqt_reply' )->parse(),
					'icon' => 'reply.png',
				];
			}
		}

		// Parent post link
		if ( !$thread->isTopmostThread() ) {
			$parent = $thread->superthread();
			$anchor = $parent->getAnchorName();

			$commands['parent'] = [
				'label' => wfMessage( 'lqt-parent' )->parse(),
				'href' => '#' . $anchor,
				'enabled' => true,
				'showlabel' => 1,
			];
		}

		MediaWikiServices::getInstance()->getHookContainer()
			->run( 'LiquidThreadsThreadMajorCommands', [ $thread, &$commands ] );

		return $commands;
	}

	/**
	 * @param Thread $thread
	 * @return array
	 */
	public function topLevelThreadCommands( Thread $thread ) {
		$commands = [];

		$commands['history'] = [
			'label' => wfMessage( 'history_short' )->parse(),
			'href' => self::permalinkUrl( $thread, 'thread_history' ),
			'enabled' => true
		];

		if ( $this->user->isAllowed( 'move' ) ) {
			$move_href = SpecialPage::getTitleFor(
				'MoveThread', $thread->title()->getPrefixedText() )->getLocalURL();
			$commands['move'] = [
				'label' => wfMessage( 'lqt-movethread' )->parse(),
				'href' => $move_href,
				'enabled' => true
			];
		}

		$services = MediaWikiServices::getInstance();
		if ( $this->user->isAllowed( 'protect' ) ) {
			$protect_href = $thread->title()->getLocalURL( 'action=protect' );

			// Check if it's already protected
			if ( !$services->getRestrictionStore()->isProtected( $thread->title() ) ) {
				$label = wfMessage( 'protect' )->parse();
			} else {
				$label = wfMessage( 'unprotect' )->parse();
			}

			$commands['protect'] = [
				'label' => $label,
				'href' => $protect_href,
				'enabled' => true
			];
		}

		if ( !$this->user->isAnon() && !$services->getWatchlistManager()
				->isWatched( $this->user, $thread->title() ) ) {
			$commands['watch'] = [
				'label' => wfMessage( 'watch' )->parse(),
				'href' => self::permalinkUrlWithQuery(
					$thread,
					[
						'action' => 'watch',
						'token' => $this->article->getContext()->getCsrfTokenSet()->getToken( 'watch' )->toString()
					]
				),
				'enabled' => true
			];
		} elseif ( !$this->user->isAnon() ) {
			$commands['unwatch'] = [
				'label' => wfMessage( 'unwatch' )->parse(),
				'href' => self::permalinkUrlWithQuery(
					$thread,
					[
						'action' => 'unwatch',
						'token' => $this->article->getContext()->getCsrfTokenSet()->getToken( 'unwatch' )->toString()
					]
				),
				'enabled' => true
			];
		}

		if ( LqtDispatch::isLqtPage( $thread->getTitle() ) ) {
			$summarizeUrl = self::permalinkUrl( $thread, 'summarize', $thread->id() );
			$commands['summarize'] = [
				'label' => wfMessage( 'lqt_summarize_link' )->parse(),
				'href' => $summarizeUrl,
				'enabled' => true,
			];
		}

		$services->getHookContainer()->run( 'LiquidThreadsTopLevelCommands', [ $thread, &$commands ] );

		return $commands;
	}

	/**
	 * @param Article $post
	 * @param int|null $oldid
	 * @return string|false false if the article and revision do not exist. The HTML of the page to
	 * display if it exists. Note that this impacts the state out OutputPage by adding
	 * all the other relevant parts of the parser output. If you don't want this, call
	 * $post->getParserOutput.
	 */
	public function showPostBody( $post, $oldid = null ) {
		$parserOutput = $post->getParserOutput( $oldid );

		if ( $parserOutput === false ) {
			return false;
		}

		// Remove title, so that it stays set correctly.
		$parserOutput->setTitleText( '' );

		$out = RequestContext::getMain()->getOutput();
		$out->addParserOutputMetadata( $parserOutput );

		// LanguageConverter for language conversion
		$services = MediaWikiServices::getInstance();
		$langConv = $services
			->getLanguageConverterFactory()
			->getLanguageConverter( $services->getContentLanguage() );

		// TODO T371022 $parserOutput is passed as a parameter to this method and comes from a Thread object that
		// is shared in other parts of the code - it can consequently be used later in various contexts (whether
		// it actually is is an open question).
		// To avoid a new version of T353257, we stay conservative here, keep an equivalent to getText, and revisit
		// this later (including analysis of whether this precaution is at all necessary) to allow cloning.
		$oldText = $parserOutput->getRawText();
		$newText = $parserOutput
			->runOutputPipeline( $post->getParserOptions(), [ 'allowClone' => false ] )->getContentHolderText();
		$parserOutput->setRawText( $oldText );

		return $langConv->convert( $newText );
	}

	/**
	 * @param Thread $thread
	 * @return string
	 * @suppress SecurityCheck-DoubleEscaped
	 */
	public function showThreadToolbar( Thread $thread ) {
		$html = '';

		$headerParts = [];

		foreach ( $this->threadMajorCommands( $thread ) as $key => $cmd ) {
			$content = $this->contentForCommand( $cmd, false /* No icon divs */ );
			$headerParts[] = Xml::tags( 'li',
						[ 'class' => "lqt-command lqt-command-$key" ],
						$content );
		}

		// Drop-down menu
		$commands = $this->threadCommands( $thread );
		$menuHTML = Xml::tags( 'ul', [ 'class' => 'lqt-thread-toolbar-command-list' ],
					$this->listItemsForCommands( $commands ) );

		$triggerText = Xml::tags( 'a', [
				'class' => 'lqt-thread-actions-icon',
				'href' => '#'
			],
			wfMessage( 'lqt-menu-trigger' )->escaped() );
		$dropdownTrigger = Xml::tags( 'div',
				[ 'class' => 'lqt-thread-actions-trigger ' .
					'lqt-command-icon', 'style' => 'display: none;' ],
				$triggerText );

		if ( count( $commands ) ) {
			$headerParts[] = Xml::tags( 'li',
						[ 'class' => 'lqt-thread-toolbar-menu' ],
						$dropdownTrigger );
		}

		$html .= implode( ' ', $headerParts );

		$html = Xml::tags( 'ul', [ 'class' => 'lqt-thread-toolbar-commands' ], $html );

		$html = Xml::tags( 'div', [ 'class' => 'lqt-thread-toolbar' ], $html ) .
				$menuHTML;

		return $html;
	}

	public function listItemsForCommands( $commands ) {
		$result = [];
		foreach ( $commands as $key => $command ) {
			$thisCommand = $this->contentForCommand( $command );

			$thisCommand = Xml::tags(
				'li',
				[ 'class' => 'lqt-command lqt-command-' . $key ],
				$thisCommand
			);

			$result[] = $thisCommand;
		}
		return implode( ' ', $result );
	}

	/**
	 * @param array $command
	 * @param bool $icon_divs
	 * @return string HTML
	 */
	public function contentForCommand( $command, $icon_divs = true ) {
		$label = $command['label'];
		$href = $command['href'];
		$enabled = $command['enabled'];
		$tooltip = $command['tooltip'] ?? '';

		if ( isset( $command['icon'] ) ) {
			$icon = Xml::tags( 'div', [ 'title' => $label,
					'class' => 'lqt-command-icon' ], '&#160;' );
			if ( $icon_divs ) {
				if ( !empty( $command['showlabel'] ) ) {
					$fullLabel = $icon . '&#160;' . $label;
				} else {
					$fullLabel = $icon;
				}
			} elseif ( empty( $command['showlabel'] ) ) {
				$fullLabel = '';
			} else {
				$fullLabel = $label;
			}
		} else {
			$fullLabel = $label;
		}

		if ( $enabled ) {
			$thisCommand = Xml::tags( 'a', [ 'href' => $href, 'title' => $tooltip ],
					$fullLabel );
		} else {
			$thisCommand = Xml::tags( 'span', [ 'class' => 'lqt_command_disabled',
						'title' => $tooltip ], $fullLabel );
		}

		return $thisCommand;
	}

	/**
	 * Shows a normal (i.e. not deleted or moved) thread body
	 * @param Thread $thread
	 */
	public function showThreadBody( Thread $thread ) {
		$post = $thread->root();

		$divClass = $this->postDivClass( $thread );
		$html = '';

		// This is a bit of a hack to have individual histories work.
		// We can grab oldid either from lqt_oldid (which is a thread rev),
		// or from oldid (which is a page rev). But oldid only applies to the
		// thread being requested, not any replies.
		$page_rev = $this->request->getVal( 'oldid', null );
		if ( $page_rev !== null && $this->title->equals( $thread->root()->getTitle() ) ) {
			$oldid = $page_rev;
		} else {
			$oldid = $thread->isHistorical() ? $thread->rootRevision() : null;
		}

		$hookContainer = MediaWikiServices::getInstance()->getHookContainer();
		// If we're editing the thread, show the editing form.
		$showAnything = $hookContainer->run( 'LiquidThreadsShowThreadBody',
					[ $thread ] );
		if ( $this->methodAppliesToThread( 'edit', $thread ) && $showAnything ) {
			$html = Xml::openElement( 'div', [ 'class' => $divClass ] );
			$this->output->addHTML( $html );
			$html = '';

			// No way am I refactoring EditForm to return its output as HTML.
			// so I'm just flushing the HTML and displaying it as-is.
			$this->showPostEditingForm( $thread );
			$html .= Xml::closeElement( 'div' );
		} elseif ( $showAnything ) {
			$html .= Xml::openElement( 'div', [ 'class' => $divClass ] );

			$show = $hookContainer->run( 'LiquidThreadsShowPostContent',
						[ $thread, &$post ] );
			if ( $show ) {
				$html .= $this->showPostBody( $post, $oldid );
			}
			$html .= Xml::closeElement( 'div' );
			$hookContainer->run( 'LiquidThreadsShowPostThreadBody',
				[ $thread, $this->request, &$html ] );

			$html .= $this->showThreadToolbar( $thread );
			$html .= $this->threadSignature( $thread );
		}

		$this->output->addHTML( $html );
	}

	/**
	 * @param Thread $thread
	 * @return string
	 */
	public function threadSignature( Thread $thread ) {
		global $wgLang;

		$signature = $thread->signature() ?? '';
		$signature = self::parseSignature( $signature );

		$signature = Xml::tags( 'span', [ 'class' => 'lqt-thread-user-signature' ],
					$signature );

		$signature .= $wgLang->getDirMark();

		$timestamp = $wgLang->timeanddate( $thread->created(), true );
		$signature .= Xml::element( 'span',
					[ 'class' => 'lqt-thread-toolbar-timestamp' ],
					$timestamp );

		MediaWikiServices::getInstance()->getHookContainer()
			->run( 'LiquidThreadsThreadSignature', [ $thread, &$signature ] );

		$signature = Xml::tags( 'div', [ 'class' => 'lqt-thread-signature' ],
					$signature );

		return $signature;
	}

	/**
	 * @param Thread $thread
	 * @return string
	 */
	private function threadInfoPanel( Thread $thread ) {
		global $wgLang;

		$infoElements = [];

		// Check for edited flag.
		$editedFlag = $thread->editedness();
		$ebLookup = [ Threads::EDITED_BY_AUTHOR => 'author',
					Threads::EDITED_BY_OTHERS => 'others' ];
		$lastEdit = $thread->root()->getPage()->getTimestamp();
		$lastEditTime = $wgLang->time( $lastEdit, false, true );
		$lastEditDate = $wgLang->date( $lastEdit, false, true );
		$lastEdit = $wgLang->timeanddate( $lastEdit, true );
		$editors = '';
		$editorCount = 0;

		if ( $editedFlag > Threads::EDITED_BY_AUTHOR ) {
			$editors = $thread->editors();
			$editorCount = count( $editors );
			$formattedEditors = [];

			foreach ( $editors as $ed ) {
				$id = IPUtils::isIPAddress( $ed ) ? 0 : 1;
				$fEditor = Linker::userLink( $id, $ed ) .
					Linker::userToolLinks( $id, $ed );
				$formattedEditors[] = $fEditor;
			}

			$editors = $wgLang->commaList( $formattedEditors );
		}

		if ( isset( $ebLookup[$editedFlag] ) ) {
			$editedBy = $ebLookup[$editedFlag];
			$author = $thread->author();
			// Used messages: lqt-thread-edited-author, lqt-thread-edited-others
			$editedNotice = wfMessage( "lqt-thread-edited-$editedBy" )
				->params( $lastEdit )->numParams( $editorCount )
				->params( $lastEditTime, $lastEditDate )
				->params( $author->getName() )->parse();
			$editedNotice = str_replace( '$3', $editors, $editedNotice );
			$infoElements[] = Xml::tags( 'div', [ 'class' =>
						"lqt-thread-toolbar-edited-$editedBy" ],
						$editedNotice );
		}

		MediaWikiServices::getInstance()->getHookContainer()
			->run( 'LiquidThreadsThreadInfoPanel', [ $thread, &$infoElements ] );

		if ( !count( $infoElements ) ) {
			return '';
		}

		return Xml::tags( 'div', [ 'class' => 'lqt-thread-info-panel' ],
							implode( "\n", $infoElements ) );
	}

	/**
	 * Shows the headING for a thread (as opposed to the headeER for a post within
	 * a thread).
	 * @param Thread $thread
	 * @return string
	 */
	public function showThreadHeading( Thread $thread ) {
		if ( $thread->hasDistinctSubject() ) {
			if ( $thread->hasSuperthread() ) {
				$commands_html = "";
			} else {
				$commands = $this->topLevelThreadCommands( $thread );
				// @phan-suppress-next-line SecurityCheck-DoubleEscaped
				$lis = $this->listItemsForCommands( $commands );
				$id = 'lqt-threadlevel-commands-' . $thread->id();
				$commands_html = Xml::tags( 'ul',
						[ 'class' => 'lqt_threadlevel_commands',
							'id' => $id ],
						$lis );
			}

			$id = 'lqt-header-' . $thread->id();

			$services = MediaWikiServices::getInstance();
			$langConv = $services
				->getLanguageConverterFactory()
				->getLanguageConverter( $services->getContentLanguage() );
			$html = $langConv->convert( $thread->formattedSubject() );

			$show = $services->getHookContainer()->run( 'LiquidThreadsShowThreadHeading', [ $thread, &$html ] );

			if ( $show ) {
				$contLang = MediaWikiServices::getInstance()->getContentLanguage();
				$html = Xml::tags( 'span', [ 'class' => 'mw-headline' ], $html );
				$html .= Html::hidden( 'raw-header', $thread->subject() );
				$html = Xml::tags( 'h' . $this->headerLevel,
						[ 'class' => 'lqt_header', 'id' => $id, 'dir' => $contLang->getDir() ],
						$html ) . $commands_html;
			}

			// wrap it all in a container
			$html = Xml::tags( 'div',
					[ 'class' => 'lqt_thread_heading' ],
					$html );
			return $html;
		}

		return '';
	}

	public function postDivClass( Thread $thread ) {
		$levelClass = 'lqt-thread-nest-' . $this->threadNestingLevel;
		$alternatingType = ( $this->threadNestingLevel % 2 ) ? 'odd' : 'even';
		$alternatingClass = "lqt-thread-$alternatingType";
		$dir = MediaWikiServices::getInstance()->getContentLanguage()->getDir();

		return "lqt_post $levelClass $alternatingClass mw-content-$dir";
	}

	/**
	 * @param Thread $thread
	 * @return string
	 */
	public static function anchorName( Thread $thread ) {
		return $thread->getAnchorName();
	}

	/**
	 * Display a moved thread
	 *
	 * @param Thread $thread
	 * @throws Exception
	 */
	public function showMovedThread( Thread $thread ) {
		global $wgLang;

		// Grab target thread
		if ( !$thread->title() ) {
			return; // Odd case: moved thread with no title?
		}

		$article = new Article( $thread->title(), 0 );
		$target = $article->getPage()->getRedirectTarget();

		if ( !$target ) {
			$content = $article->getPage()->getContent();
			$contentText = ( $content instanceof TextContent ) ? $content->getText() : '';
			throw new LogicException( "Thread " . $thread->id() . ' purports to be moved, ' .
				'but no redirect found in text (' . $contentText . ') of ' .
				$thread->root()->getTitle()->getPrefixedText() . '. Dying.'
			);
		}

		$t_thread = Threads::withRoot(
			MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $target )
		);

		// Grab data about the new post.
		$author = $thread->author();
		$sig = Linker::userLink( $author->getId(), $author->getName() ) .
			Linker::userToolLinks( $author->getId(), $author->getName() );
		$newTalkpage = is_object( $t_thread ) ? $t_thread->getTitle() : '';

		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
		$html = wfMessage( 'lqt_move_placeholder' )
			->rawParams( $linkRenderer->makeLink( $target ), $sig )
			->params( $wgLang->date( $thread->modified() ), $wgLang->time( $thread->modified() ) )
			->rawParams( $newTalkpage ? $linkRenderer->makeLink( $newTalkpage ) : '' )->parse();

		$this->output->addHTML( $html );
	}

	/**
	 * Shows a deleted thread. Returns true to show the thread body
	 * @param Thread $thread
	 * @return bool
	 */
	public function showDeletedThread( $thread ) {
		if ( $this->user->isAllowed( 'deletedhistory' ) ) {
			$this->output->addWikiMsg( 'lqt_thread_deleted_for_sysops' );
			return true;
		} else {
			$msg = wfMessage( 'lqt_thread_deleted' )->parse();
			$msg = Xml::tags( 'em', null, $msg );
			$msg = Xml::tags( 'p', null, $msg );

			$this->output->addHTML( $msg );
			return false;
		}
	}

	/**
	 * Shows a single thread, rather than a thread tree.
	 *
	 * @param Thread $thread
	 */
	public function showSingleThread( Thread $thread ) {
		$html = '';

		// If it's a 'moved' thread, show the placeholder
		if ( $thread->type() == Threads::TYPE_MOVED ) {
			$this->showMovedThread( $thread );
			return;
		} elseif ( $thread->type() == Threads::TYPE_DELETED ) {
			$res = $this->showDeletedThread( $thread );

			if ( !$res ) {
				return;
			}
		}

		$this->output->addHTML( $this->threadInfoPanel( $thread ) );

		if ( $thread->summary() ) {
			$html .= $this->getSummary( $thread );
		}

		// Unfortunately, I can't rewrite showRootPost() to pass back HTML
		// as it would involve rewriting EditPage, which I do NOT intend to do.

		$this->output->addHTML( $html );

		$this->showThreadBody( $thread );
	}

	/**
	 * @param (Thread|int)[] $threads
	 * @return Thread[]
	 */
	public function getMustShowThreads( array $threads = [] ) {
		if ( $this->request->getCheck( 'lqt_operand' ) ) {
			$operands = explode( ',', $this->request->getVal( 'lqt_operand' ) );
			$operands = array_filter( $operands, 'ctype_digit' );
			$threads = array_merge( $threads, $operands );
		}

		if ( $this->request->getCheck( 'lqt_mustshow' ) ) {
			// Check for must-show in the request
			$specifiedMustShow = $this->request->getVal( 'lqt_mustshow' );
			$specifiedMustShow = explode( ',', $specifiedMustShow );
			$specifiedMustShow = array_filter( $specifiedMustShow, 'ctype_digit' );

			$threads = array_merge( $threads, $specifiedMustShow );
		}

		foreach ( $threads as $walk_thread ) {
			do {
				if ( !is_object( $walk_thread ) ) {
					$walk_thread = Threads::withId( $walk_thread );
				}

				if ( !is_object( $walk_thread ) ) {
					continue;
				}

				$threads[$walk_thread->id()] = $walk_thread;
				$walk_thread = $walk_thread->superthread();
			} while ( $walk_thread );
		}

		return $threads;
	}

	/**
	 * @param Thread $thread
	 * @param Thread $st
	 * @param int $i
	 * @return string
	 */
	public function getShowMore( Thread $thread, $st, $i ) {
		$linkText = new HtmlArmor( wfMessage( 'lqt-thread-show-more' )->parse() );
		$linkTitle = clone $thread->topmostThread()->title();
		$linkTitle->setFragment( '#' . $st->getAnchorName() );

		$link = MediaWikiServices::getInstance()->getLinkRenderer()->makeLink(
			$linkTitle,
			$linkText,
			[
				'class' => 'lqt-show-more-posts',
			]
		);
		$link .= Html::hidden( 'lqt-thread-start-at', (string)$i,
				[ 'class' => 'lqt-thread-start-at' ] );

		return $link;
	}

	/**
	 * When some replies are hidden because the are nested too deep,
	 * this method creates a link that can be used to show the hidden
	 * threats.
	 * @param Thread $thread
	 * @return string Html
	 */
	public function getShowReplies( Thread $thread ) {
		$linkText = new HtmlArmor( wfMessage( 'lqt-thread-show-replies' )
			->numParams( $thread->replyCount() )
			->parse() );
		$linkTitle = clone $thread->topmostThread()->title();
		$linkTitle->setFragment( '#' . $thread->getAnchorName() );

		$link = MediaWikiServices::getInstance()->getLinkRenderer()->makeLink(
			$linkTitle,
			$linkText,
			[
				'class' => 'lqt-show-replies',
			]
		);
		$link = Xml::tags( 'div', [ 'class' => 'lqt-thread-replies' ], $link );

		return $link;
	}

	/**
	 * @param Thread $thread
	 * @return bool
	 */
	public static function threadContainsRepliesWithContent( Thread $thread ) {
		$replies = $thread->replies();

		foreach ( $replies as $reply ) {
			$content = '';
			if ( $reply->root() ) {
				$pageContent = $reply->root()->getPage()->getContent();
				$content = ( $pageContent instanceof TextContent ) ? $pageContent->getText() : '';
			}

			if ( $content !== null && trim( $content ) != '' ) {
				return true;
			}

			if ( self::threadContainsRepliesWithContent( $reply ) ) {
				return true;
			}

			if ( $reply->type() == Threads::TYPE_MOVED ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param Thread $thread
	 * @param int $startAt
	 * @param int $maxCount
	 * @param bool $showThreads
	 * @param array $cascadeOptions
	 * @param bool $interruption
	 */
	public function showThreadReplies( Thread $thread, $startAt, $maxCount, $showThreads,
			$cascadeOptions, $interruption = false ) {
		$repliesClass = 'lqt-thread-replies lqt-thread-replies-' .
					$this->threadNestingLevel;

		if ( $interruption ) {
			$repliesClass .= ' lqt-thread-replies-interruption';
		}

		$div = Xml::openElement( 'div', [ 'class' => $repliesClass ] );

		$subthreadCount = count( $thread->subthreads() );
		$i = 0;
		$showCount = 0;
		$showThreads = true;

		$mustShowThreads = $cascadeOptions['mustShowThreads'];

		$replies = $thread->subthreads();
		usort( $replies, static fn ( Thread $a, Thread $b ) => $a->created() <=> $b->created() );

		foreach ( $replies as $st ) {
			++$i;

			// Only show undeleted threads that are above our 'startAt' index.
			$shown = false;
			if ( $st->type() != Threads::TYPE_DELETED &&
					$i >= $startAt &&
					$showThreads ) {
				if ( $showCount > $maxCount && $maxCount > 0 ) {
					// We've shown too many threads.
					$link = $this->getShowMore( $thread, $st, $i );

					$this->output->addHTML( $div . $link . '</div>' );
					$showThreads = false;
					continue;
				}

				++$showCount;
				if ( $showCount == 1 ) {
					// There's a post sep before each reply group to
					// separate from the parent thread.
					$this->output->addHTML( $div );
				}

				$this->showThread( $st, $i, $subthreadCount, $cascadeOptions );
				$shown = true;
			}

			// Handle must-show threads.
			// FIXME this thread will be duplicated if somebody clicks the
			// "show more" link (probably needs fixing in the JS)
			if ( $st->type() != Threads::TYPE_DELETED && !$shown &&
					array_key_exists( $st->id(), $mustShowThreads ) ) {
				$this->showThread( $st, $i, $subthreadCount, $cascadeOptions );
			}
		}

		// Show reply stuff
		$this->showReplyBox( $thread );

		$finishDiv = '';
		$finishDiv .= Xml::tags(
			'div',
			[ 'class' => 'lqt-replies-finish' ],
			'&#160;'
		);

		$this->output->addHTML( $finishDiv . Xml::closeElement( 'div' ) );
	}

	/**
	 * @param Thread $thread
	 * @param int $levelNum
	 * @param int $totalInLevel
	 * @param array $options
	 * @throws Exception
	 */
	public function showThread( Thread $thread, $levelNum = 1, $totalInLevel = 1,
		$options = []
	) {
		// Safeguard
		if ( $thread->type() & Threads::TYPE_DELETED ||
				!$thread->root() ) {
			return;
		}

		$this->threadNestingLevel++;

		// Figure out which threads *need* to be shown because they're involved in an
		// operation
		$mustShowOption = $options['mustShowThreads'] ?? [];
		$mustShowThreads = $this->getMustShowThreads( $mustShowOption );

		// For cascading.
		$options['mustShowThreads'] = $mustShowThreads;

		// Don't show blank posts unless we have to
		$content = '';
		if ( $thread->root() ) {
			$pageContent = $thread->root()->getPage()->getContent();
			$content = ( $pageContent instanceof TextContent ) ? $pageContent->getText() : '';
		}

		if (
			$content !== null && trim( $content ) == '' &&
			$thread->type() != Threads::TYPE_MOVED &&
			!self::threadContainsRepliesWithContent( $thread ) &&
			!array_key_exists( $thread->id(), $mustShowThreads )
		) {
			$this->threadNestingLevel--;
			return;
		}

		// Grab options
		$services = MediaWikiServices::getInstance();
		$userOptionsLookup = $services->getUserOptionsLookup();
		$maxDepth = $options['maxDepth'] ?? $userOptionsLookup->getOption( $this->user, 'lqtdisplaydepth' );
		$maxCount = $options['maxCount'] ?? $userOptionsLookup->getOption( $this->user, 'lqtdisplaycount' );
		$startAt = $options['startAt'] ?? 0;

		// Figure out if we have replies to show or not.
		$showThreads = ( $maxDepth == -1 ) ||
				( $this->threadNestingLevel <= $maxDepth );
		$mustShowThreadIds = array_keys( $mustShowThreads );
		$subthreadIds = array_keys( $thread->replies() );
		$mustShowSubthreadIds = array_intersect( $mustShowThreadIds, $subthreadIds );

		$hasSubthreads = self::threadContainsRepliesWithContent( $thread );
		$hasSubthreads = $hasSubthreads || count( $mustShowSubthreadIds );
		// Show subthreads if one of the subthreads is on the must-show list
		$showThreads = $showThreads ||
			count( array_intersect(
				array_keys( $mustShowThreads ), array_keys( $thread->replies() )
			) );
		$replyTo = $this->methodAppliesToThread( 'reply', $thread );

		$this->output->addModules( 'ext.liquidThreads' );

		$html = '';
		$services->getHookContainer()->run( 'EditPageBeforeEditToolbar', [ &$html ] );

		$class = $this->threadDivClass( $thread );
		if ( $levelNum == 1 ) {
			$class .= ' lqt-thread-first';
		} elseif ( $levelNum == $totalInLevel ) {
			$class .= ' lqt-thread-last';
		}

		if ( $hasSubthreads && $showThreads ) {
			$class .= ' lqt-thread-with-subthreads';
		} else {
			$class .= ' lqt-thread-no-subthreads';
		}

		if ( !$services->getPermissionManager()
			->quickUserCan( 'edit', $this->user, $thread->title() )
			|| !LqtDispatch::isLqtPage( $thread->getTitle() )
		) {
			$class .= ' lqt-thread-uneditable';
		}

		$class .= ' lqt-thread-wrapper';

		$html .= Xml::openElement(
			'div',
			[
				'class' => $class,
				'id' => 'lqt_thread_id_' . $thread->id()
			]
		);

		$html .= Xml::element( 'a', [ 'name' => $this->anchorName( $thread ) ], ' ' );
		$html .= $this->showThreadHeading( $thread );

		// Metadata stuck in the top of the lqt_thread div.
		// Modified time for topmost threads...
		if ( $thread->isTopmostThread() ) {
			$html .= Html::hidden(
				'lqt-thread-modified-' . $thread->id(),
				wfTimestamp( TS_MW, $thread->modified() ),
				[
					'id' => 'lqt-thread-modified-' . $thread->id(),
					'class' => 'lqt-thread-modified'
				]
			);
			$html .= Html::hidden(
				'lqt-thread-sortkey',
				$thread->sortkey(),
				[ 'id' => 'lqt-thread-sortkey-' . $thread->id() ]
			);

			$html .= Html::hidden(
				'lqt-thread-talkpage-' . $thread->id(),
				$thread->article()->getTitle()->getPrefixedText(),
				[
					'class' => 'lqt-thread-talkpage-metadata',
				]
			);
		}

		if ( !$thread->title() ) {
			throw new LogicException( "Thread " . $thread->id() . " has null title" );
		}

		// Add the thread's title
		$html .= Html::hidden(
			'lqt-thread-title-' . $thread->id(),
			$thread->title()->getPrefixedText(),
			[
				'id' => 'lqt-thread-title-' . $thread->id(),
				'class' => 'lqt-thread-title-metadata'
			]
		);

		// Flush output to display thread
		$this->output->addHTML( $html );
		$this->output->addHTML( Xml::openElement( 'div',
					[ 'class' => 'lqt-post-wrapper' ] ) );
		$this->showSingleThread( $thread );
		$this->output->addHTML( Xml::closeElement( 'div' ) );

		$cascadeOptions = $options;
		unset( $cascadeOptions['startAt'] );

		$replyInterruption = $levelNum < $totalInLevel;

		if ( ( $hasSubthreads && $showThreads ) ) {
			// If the thread has subthreads, and we want to show them, we should do so.
			$this->showThreadReplies( $thread, $startAt, $maxCount, $showThreads,
				$cascadeOptions, $replyInterruption );
		} elseif ( $hasSubthreads && !$showThreads ) {
			// If the thread has subthreads, but we don't want to show them, then
			// show the reply form if necessary, and add the "Show X replies" link.
			if ( $replyTo ) {
				$this->showReplyForm( $thread );
			}

			// Add a "show subthreads" link.
			$link = $this->getShowReplies( $thread );

			$this->output->addHTML( $link );

			if ( $levelNum < $totalInLevel ) {
				$this->output->addHTML(
					Xml::tags( 'div', [ 'class' => 'lqt-post-sep' ], '&#160;' ) );
			}
		} elseif ( $levelNum < $totalInLevel ) {
			// If we have no replies, and we're not at the end of this level, add the post separator
			// and a reply box if necessary.
			$this->output->addHTML(
				Xml::tags( 'div', [ 'class' => 'lqt-post-sep' ], '&#160;' ) );

			if ( $replyTo ) {
				$class = 'lqt-thread-replies lqt-thread-replies-' .
						$this->threadNestingLevel;
				$html = Xml::openElement( 'div', [ 'class' => $class ] );
				$this->output->addHTML( $html );

				$this->showReplyForm( $thread );

				$finishDiv = Xml::tags( 'div',
						[ 'class' => 'lqt-replies-finish' ],
						'&#160;' );
				// Layout plus close div.lqt-thread-replies

				$finishHTML = Xml::closeElement( 'div' ); // lqt-reply-form
				$finishHTML .= $finishDiv; // Layout
				$finishHTML .= Xml::closeElement( 'div' ); // lqt-thread-replies
				$this->output->addHTML( $finishHTML );
			}
		} elseif ( !$hasSubthreads && $replyTo ) {
			// If we have no replies, we're at the end of this level, and we want to reply,
			// show the reply box.
			$class = 'lqt-thread-replies lqt-thread-replies-' .
					$this->threadNestingLevel;
			$html = Xml::openElement( 'div', [ 'class' => $class ] );
			$this->output->addHTML( $html );

			$this->showReplyForm( $thread );

			$html = Xml::tags( 'div',
					[ 'class' => 'lqt-replies-finish' ],
					Xml::tags( 'div',
						[ 'class' =>
							'lqt-replies-finish-corner'
						], '&#160;' ) );
			$html .= Xml::closeElement( 'div' );
			$this->output->addHTML( $html );
		}

		$this->output->addHTML( Xml::closeElement( 'div' ) );

		$this->threadNestingLevel--;
	}

	/**
	 * @param Thread $thread
	 */
	public function showReplyBox( Thread $thread ) {
		// Check if we're actually replying to this thread.
		if ( $this->methodAppliesToThread( 'reply', $thread ) ) {
			$this->showReplyForm( $thread );
			return;
		} elseif ( !$thread->canUserReply( $this->user, 'quick' ) ) {
			return;
		}

		$html = '';
		$html .= Xml::tags( 'div',
			[ 'class' => 'lqt-reply-form lqt-edit-form',
				'style' => 'display: none;' ],
			''
		);

		$this->output->addHTML( $html );
	}

	/**
	 * @param Thread $thread
	 * @return string
	 */
	public function threadDivClass( Thread $thread ) {
		$levelClass = 'lqt-thread-nest-' . $this->threadNestingLevel;
		$alternatingType = ( $this->threadNestingLevel % 2 ) ? 'odd' : 'even';
		$alternatingClass = "lqt-thread-$alternatingType";
		$topmostClass = $thread->isTopmostThread() ? ' lqt-thread-topmost' : '';

		return "lqt_thread $levelClass $alternatingClass$topmostClass";
	}

	/**
	 * @param Thread $t
	 * @return string
	 */
	public function getSummary( $t ) {
		if ( !$t->summary() ) {
			return '';
		}
		if ( !( $t->summary()->getPage()->getContent() instanceof TextContent ) ) {
			return ''; // Blank summary
		}

		$label = wfMessage( 'lqt_summary_label' )->parse();
		$edit_text = wfMessage( 'edit' )->text();
		$link_text = new HtmlArmor( wfMessage( 'lqt_permalink' )->parse() );

		$html = '';

		$html .= Xml::tags(
			'span',
			[ 'class' => 'lqt_thread_permalink_summary_title' ],
			$label
		);

		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
		$link = $linkRenderer->makeLink(
			$t->summary()->getTitle(),
			$link_text,
			[
				'class' => 'lqt-summary-link',
			]
		);
		$link .= Html::hidden( 'summary-title', $t->summary()->getTitle()->getPrefixedText() );
		$edit_link = self::permalink( $t, $edit_text, 'summarize', $t->id() );
		$links = "[$link]\n[$edit_link]";
		$html .= Xml::tags(
			'span',
			[ 'class' => 'lqt_thread_permalink_summary_actions' ],
			$links
		);

		$summary_body = $this->showPostBody( $t->summary() );
		$html .= Xml::tags(
			'div',
			[ 'class' => 'lqt_thread_permalink_summary_body' ],
			$summary_body
		);

		$html = Xml::tags(
			'div',
			[ 'class' => 'lqt_thread_permalink_summary' ],
			$html
		);

		return $html;
	}

	/**
	 * @param User|int|string $user
	 * @return string
	 */
	public function getSignature( $user ) {
		if ( is_object( $user ) ) {
			$uid = $user->getId();
		} elseif ( is_int( $user ) ) {
			$uid = $user;
			$user = User::newFromId( $uid );
		} else {
			$user = User::newFromName( $user );
			$uid = $user->getId();
		}

		return $this->getUserSignature( $user, $uid );
	}

	/**
	 * @param User|null $user
	 * @param int|null $uid
	 * @return string
	 */
	public static function getUserSignature( $user, $uid = null ) {
		$parser = MediaWikiServices::getInstance()->getParser();

		if ( !$user ) {
			$user = User::newFromId( $uid );
		}

		$parser->setOptions( new ParserOptions( $user ) );

		$sig = $parser->getUserSig( $user );

		return $sig;
	}

	/**
	 * @param string $sig
	 * @return string
	 */
	public static function parseSignature( $sig ) {
		static $parseCache = [];
		$sigKey = md5( $sig );

		if ( isset( $parseCache[$sigKey] ) ) {
			return $parseCache[$sigKey];
		}

		$out = RequestContext::getMain()->getOutput();
		$sig = Parser::stripOuterParagraph(
			$out->parseAsContent( $sig )
		);

		$parseCache[$sigKey] = $sig;

		return $sig;
	}

	/**
	 * @param string $sig
	 * @param User $user
	 * @return string
	 */
	public static function signaturePST( $sig, $user ) {
		// phpcs:ignore MediaWiki.Usage.DeprecatedGlobalVariables.Deprecated$wgTitle
		global $wgTitle;

		$title = $wgTitle ?: $user->getUserPage();

		$sig = MediaWikiServices::getInstance()->getParser()->preSaveTransform(
			$sig,
			$title,
			$user,
			new ParserOptions( $user ),
			true
		);

		return $sig;
	}

	public function customizeNavigation( $skin, &$links ) {
		// No-op
	}

	public function show() {
		return true; // No-op
	}

	/**
	 * Copy-and-modify of MediaWiki\CommentFormatter\CommentFormatter::format
	 *
	 * @param string $s
	 * @return string
	 */
	public static function formatSubject( $s ) {
		# Sanitize text a bit:
		$s = str_replace( "\n", " ", $s );
		# Allow HTML entities
		$s = Sanitizer::escapeHtmlAllowEntities( $s );

		# Render links:
		return MediaWikiServices::getInstance()->getCommentFormatter()
			->formatLinks( $s, null, false );
	}
}
