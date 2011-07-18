<?php
if ( !defined( 'MEDIAWIKI' ) ) die;

class TalkpageView extends LqtView {
	protected $talkpage;
	
	function __construct( &$output, &$article, &$title, &$user, &$request ) {
		parent::__construct( $output, $article, $title, $user, $request );
		
		$this->talkpage = $article;
	}
	
	function setTalkPage($tp) {
		$this->talkpage = $tp;
	}

	/* Added to SkinTemplateTabs hook in TalkpageView::show(). */
	static function customizeTalkpageTabs( $skintemplate, &$content_actions, $view ) {
		// The arguments are passed in by reference.
		unset( $content_actions['edit'] );
		unset( $content_actions['viewsource'] );
		unset( $content_actions['delete'] );

		# Protection against non-SkinTemplate skins
		if ( isset( $content_actions['history'] ) ) {
			$thisTitle = $view->article->getTitle();
			$history_url = $thisTitle->getLocalURL( 'lqt_method=talkpage_history' );
			$content_actions['history']['href'] = $history_url;
		}
	}

	static function customizeTalkpageNavigation( $skin, &$links, $view ) {
		$remove = array( 'views/edit', 'views/viewsource', 'actions/delete' );

		foreach ( $remove as $rem ) {
			list( $section, $item ) = explode( '/', $rem, 2 );
			unset( $links[$section][$item] );
		}

		if ( isset( $links['views']['history'] ) ) {
			$title = $view->article->getTitle();
			$history_url = $title->getLocalURL( 'lqt_method=talkpage_history' );
			$links['views']['history']['href'] = $history_url;
		}
	}

	function customizeTabs( $skintemplate, &$links ) {
		self::customizeTalkpageTabs( $skintemplate, $links, $this );
	}

	function customizeNavigation( $skintemplate, &$links ) {
		self::customizeTalkpageNavigation( $skintemplate, $links, $this );
	}

	function showHeader() {
		/* Show the contents of the actual talkpage article if it exists. */

		global $wgUser;
		$sk = $wgUser->getSkin();

		$article = new Article( $this->title );

		// If $article_text == "", the talkpage was probably just created
		// when the first thread was posted to make the links blue.
		if ( $article->exists() ) {
			$html = '';

			$article->view();

			$actionLinks = array();
			$actionLinks[] = $sk->link(
				$this->title,
				wfMsgExt( 'edit', 'parseinline' ) . "↑",
				array(),
				array( 'action' => 'edit' )
			);
			$actionLinks[] = $sk->link(
				$this->title,
				wfMsgExt( 'history_short', 'parseinline' ) . "↑",
				array(),
				array( 'action' => 'history' )
			);

			if ( $wgUser->isAllowed( 'delete' ) ) {
				$actionLinks[] = $sk->link(
					$this->title,
					wfMsgExt( 'delete', 'parseinline' ) . '↑',
					array(),
					array( 'action' => 'delete' )
				);
			}

			$actions = '';
			foreach ( $actionLinks as $link ) {
				$actions .= Xml::tags( 'li', null, "[$link]" ) . "\n";
			}
			$actions = Xml::tags( 'ul', array( 'class' => 'lqt_header_commands' ), $actions );
			$html .= $actions;

			$html = Xml::tags( 'div', array( 'class' => 'lqt_header_content' ), $html );

			$this->output->addHTML( $html );
		} else {

			$editLink = $sk->link(
				$this->title,
				wfMsgExt( 'lqt_add_header', 'parseinline' ),
				array(),
				array( 'action' => 'edit' )
			);

			$html = Xml::tags( 'p', array( 'class' => 'lqt_header_notice' ), "[$editLink]" );

			$this->output->addHTML( $html );
		}
	}


	function show() {
		$this->output->setPageTitle( $this->title->getPrefixedText() );

		$this->showHeader();

		try {
			$channel = LiquidThreadsChannel::newFromTitle( $this->title );
		} catch ( MWException $excep ) {
			$channel = LiquidThreadsChannel::create( $title );
		}
		
		$action = LqtDispatch::getAction();
		
		$channelView = new LiquidThreadsChannelView( $channel );
		
		$channelView->show( $action );

		return false;
	}

}

