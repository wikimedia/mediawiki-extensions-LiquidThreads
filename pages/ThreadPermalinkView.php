<?php

if ( !defined( 'MEDIAWIKI' ) ) die;

class ThreadPermalinkView extends LqtView {
	protected $thread;

	function customizeTabs( $skintemplate, $content_actions ) {
		wfLoadExtensionMessages( 'LiquidThreads' );
		// Insert fake 'article' and 'discussion' tabs before the thread tab.
		// If you call the key 'talk', the url gets re-set later. TODO:
		// the access key for the talk tab doesn't work.
		if ($this->thread) {
			$article_t = $this->thread->article()->getTitle();
			$talk_t = $this->thread->article()->getTitle();
		} else {
			return true;
		}
		
		$articleTab =
			array(
				'text' => wfMsg( $article_t->getNamespaceKey() ),
				'href' => $article_t->getFullURL(),
				'class' => $article_t->exists() ? '' : 'new'
			);
		efInsertIntoAssoc( 'article', $articleTab, 'nstab-thread', $content_actions );
		
		$talkTab =
			array(
				// talkpage certainly exists since this thread is from it.
				'text' => wfMsg( 'talk' ),
				'href' => $talk_t->getFullURL()
			);
		
		efInsertIntoAssoc( 'not_talk', $talkTab, 'nstab-thread', $content_actions );

		unset( $content_actions['edit'] );
		unset( $content_actions['viewsource'] );
		unset( $content_actions['talk'] );
		
		if ( array_key_exists( 'move', $content_actions ) && $this->thread ) {
			$content_actions['move']['href'] =
			SpecialPage::getTitleFor( 'MoveThread' )->getFullURL() . '/' .
			$this->thread->title()->getPrefixedURL();
		}
		
		if ( array_key_exists( 'delete', $content_actions ) && $this->thread ) {
			$content_actions['delete']['href'] =
			SpecialPage::getTitleFor( 'DeleteThread' )->getFullURL() . '/' .
			$this->thread->title()->getPrefixedURL();
		}

		if ( array_key_exists( 'history', $content_actions ) ) {
			$content_actions['history']['href'] = $this->permalinkUrl( $this->thread, 'thread_history' );
			if ( $this->methodApplies( 'thread_history' ) ) {
				$content_actions['history']['class'] = 'selected';
			}
		}

		return true;
	}

	function showThreadHeading( $thread ) {
		if ( $this->headerLevel == 2 ) {
			$this->output->setPageTitle( $thread->wikilink() );
		} else {
			parent::showThreadHeading( $thread );
		}
	}

	function noSuchRevision() {
		wfLoadExtensionMessages( 'LiquidThreads' );
		$this->output->addHTML( wfMsg( 'lqt_nosuchrevision' ) );
	}

	function showMissingThreadPage() {
		wfLoadExtensionMessages( 'LiquidThreads' );
		$this->output->setPageTitle( wfMsg( 'lqt_nosuchthread_title' ) );
		$this->output->addWikiMsg( 'lqt_nosuchthread' );
	}

	function getSubtitle() {
		wfLoadExtensionMessages( 'LiquidThreads' );
		
		if ( $this->thread->isHistorical() ) {
			// TODO: Point to the relevant part of the archive.
			$query = '';
		} else {
			$query = '';
		}
		
		$talkpage = $this->thread->article()->getTitle();
		$talkpage_link = $this->user->getSkin()->link( $talkpage );
		
		if ( $this->thread->hasSuperthread() ) {
			$permalink = $this->permalink( $this->thread->topmostThread(),
							wfMsg( 'lqt_discussion_link' ) );
							
			return wfMsg( 'lqt_fragment', $permalink, $talkpage_link );
		} else {
			return wfMsg( 'lqt_from_talk', $talkpage_link );
		}
	}

	function __construct( &$output, &$article, &$title, &$user, &$request ) {

		parent::__construct( $output, $article, $title, $user, $request );

		$t = Threads::withRoot( $this->article );
		$oldid = $this->request->getVal( 'lqt_oldid', null );
		
		if ( $oldid ) {
			$t = $t->atRevision( $oldid );
			
			if ( !$t ) {
				$this->noSuchRevision();
				return;
			}
		}
		
		$this->thread = $t;
		if ( !$t ) {
			return; // error reporting is handled in show(). this kinda sucks.
		}

		// $this->article gets saved to thread_article, so we want it to point to the
		// subject page associated with the talkpage, always, not the permalink url.
		$this->article = $t->article(); # for creating reply threads.

	}

	function show() {
		global $wgHooks;
		$wgHooks['SkinTemplateTabs'][] = array( $this, 'customizeTabs' );

		if ( !$this->thread ) {
			$this->showMissingThreadPage();
			return false;
		}

		self::addJSandCSS();
		$this->output->setSubtitle( $this->getSubtitle() );

		if ( $this->methodApplies( 'summarize' ) )
			$this->showSummarizeForm( $this->thread );

		$this->showThread( $this->thread );
		return false;
	}
}
