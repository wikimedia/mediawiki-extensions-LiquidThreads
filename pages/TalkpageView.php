<?php

if ( !defined( 'MEDIAWIKI' ) ) die;

class TalkpageView extends LqtView {
	/* Added to SkinTemplateTabs hook in TalkpageView::show(). */
	function customizeTabs( $skintemplate, $content_actions ) {
		// The arguments are passed in by reference.
		unset( $content_actions['edit'] );
		unset( $content_actions['viewsource'] );
		unset( $content_actions['addsection'] );
		unset( $content_actions['history'] );
		unset( $content_actions['watch'] );
		unset( $content_actions['move'] );

		/*
		TODO:
		We could make these tabs actually follow the tab metaphor if we repointed
		the 'history' and 'edit' tabs to the original subject page. That way 'discussion'
		would just be one of four ways to view the article. But then those other tabs, for
		logged-in users, don't really fit the metaphor. What to do, what to do?
		*/
		return true;
	}

	function permalinksForThreads( $threads, $method = null, $operand = null ) {
		$permalinks = array();
		foreach ( $threads as $t ) {
			$l = $t->subjectWithoutIncrement();
			$permalinks[] = self::permalink( $t, $l, $method, $operand );
		}
		return $permalinks;
	}

	function showHeader() {
		/* Show the contents of the actual talkpage article if it exists. */
		
		global $wgUser;
		$sk = $wgUser->getSkin();

		$article = new Article( $this->title );
		$revision = Revision::newFromId( $article->getLatest() );
		if ( $revision ) $article_text = $revision->getRawText();

		$oldid = $this->request->getVal( 'oldid', null );

		wfLoadExtensionMessages( 'LiquidThreads' );
		// If $article_text == "", the talkpage was probably just created
		// when the first thread was posted to make the links blue.
		if ( $article->exists() && $article_text != "" ) {
			$html = '';
			
			$html .= $this->showPostBody( $article, $oldid );
			
			$actionLinks = array();
			$actionLinks[] = $sk->link( $this->title,
								wfMsgExt( 'edit', 'parseinline' ) . "&uarr;",
								array(), array( 'action' => 'edit' ) );
			$actionLinks[] = $sk->link( $this->title,
								wfMsgExt( 'history_short', 'parseinline' ) . "&uarr;",
								array(), array( 'action' => 'history' ) );
			
			$actions = '';
			foreach( $actionLinks as $link ) {
				$actions .= Xml::tags( 'li', null, "[$link]" ) . "\n";
			}
			$actions = Xml::tags( 'ul', array( 'class' => 'lqt_header_commands' ), $actions );
			$html .= $actions;

			$html = Xml::tags( 'div', array( 'class' => 'lqt_header_content' ), $html );
			
			$this->output->addHTML( $html );
		} else {
			
			$editLink = $sk->link( $this->title, wfMsgExt( 'lqt_add_header', 'parseinline' ),
									array(), array( 'action' => 'edit' ) );
			
			$html = Xml::tags( 'p', array( 'class' => 'lqt_header_notice' ), "[$editLink]" );
			
			$this->output->addHTML( $html );
		}
	}
	
	function getTOC( $threads ) {
		global $wgLang;
		
		wfLoadExtensionMessages( 'LiquidThreads' );

		$sk = $this->user->getSkin();
		
		$html = '';
		
		$html .= Xml::tags( 'h2', null, wfMsgExt( 'lqt_contents_title', 'parseinline' ) );
		
		// Header row
		$headerRow = '';
		$headers = array( 'lqt_toc_thread_title', 'lqt_toc_thread_author',
							'lqt_toc_thread_replycount', 'lqt_toc_thread_modified' );
		foreach( $headers as $msg ) {
			$headerRow .= Xml::tags( 'th', null, wfMsgExt( $msg, 'parseinline' ) );
		}
		$headerRow = Xml::tags( 'tr', null, $headerRow );
		$headerRow = Xml::tags( 'thead', null, $headerRow );
		
		// Table body
		$rows = array();
		foreach( $threads as $thread ) {
			$row = '';
			$anchor = '#'.$this->anchorName( $thread );
			$subject = $this->output->parseInline( $thread->subjectWithoutIncrement() );
			$subject = Xml::tags( 'a', array( 'href' => $anchor ), $subject );
			$row .= Xml::tags( 'td', null, $subject );
			
			$author = $thread->root()->originalAuthor();
			$authorLink = $sk->userLink( $author->getID(), $author->getName() );
			$row .= Xml::tags( 'td', null, $authorLink );
			
			$row .= Xml::element( 'td', null, count( $thread->replies() ) );
			
			$timestamp = $wgLang->timeanddate( $thread->created(), true );
			$row .= Xml::element( 'td', null, $timestamp );
			
			$row = Xml::tags( 'tr', null, $row );
			$rows[] = $row;
		}
		
		$html .= $headerRow . "\n" . Xml::tags( 'tbody', null, implode( "\n", $rows ) );
		$html = Xml::tags( 'table', array( 'class' => 'lqt_toc' ), $html );
		
		return $html;
	}
	
	function getList( $kind, $class, $id, $contents ) {
		$html = '';
		foreach ( $contents as $li ) {
			$html .= Xml::tags( 'li', null, $li );
		}
		$html = Xml::tags( $kind, array( 'class' => $class, 'id' => $id ), $html );
		
		return $html;
	}

	function getArchiveWidget( $threads ) {
		wfLoadExtensionMessages( 'LiquidThreads' );
		
		$threadlinks = self::permalinksForThreads( $threads );

		if ( count( $threadlinks ) > 0 ) {
			$url = $this->talkpageUrl( $this->title, 'talkpage_archive' );
		
			$html = '';
			$html = Xml::tags( 'h2', array( 'class' => 'lqt_recently_archived' ),
								wfMsgExt( 'lqt_recently_archived', 'parseinline' ) );
			$html .= $this->getList( 'ul', '', '', $threadlinks );
			$html = Xml::tags( 'div', array( 'class' => 'lqt_archive_teaser' ), $html );
			return $html;
		}
		
		return '';
	}

	function showTalkpageViewOptions( $article ) {
		wfLoadExtensionMessages( 'LiquidThreads' );

		if ( $this->methodApplies( 'talkpage_sort_order' ) ) {
			$remember_sort_checked = $this->request->getBool( 'lqt_remember_sort' );
			$this->user->setOption( 'lqt_sort_order', $this->sort_order );
			$this->user->saveSettings();
		} else {
			$remember_sort_checked = '';
		}

		if ( $article->exists() ) {
			$newest_changes = wfMsg( 'lqt_sort_newest_changes' );
			$newest_threads = wfMsg( 'lqt_sort_newest_threads' );
			$oldest_threads = wfMsg( 'lqt_sort_oldest_threads' );
			$lqt_remember_sort = wfMsg( 'lqt_remember_sort' ) ;
			$form_action_url = $this->talkpageUrl( $this->title, 'talkpage_sort_order' );
			$lqt_sort_newest_changes = wfMsg( 'lqt_sort_newest_changes' );
			$lqt_sort_newest_threads = wfMsg( 'lqt_sort_newest_threads' );
			$lqt_sort_oldest_threads = wfMsg( 'lqt_sort_oldest_threads' );
			$go = wfMsg( 'go' );
			
			$html = '';
			
			$html .= Xml::label( wfMsg( 'lqt_sorting_order' ), 'lqt_sort_select' ) . ' ';

			$sortOrderSelect =
				new XmlSelect( 'lqt_order', 'lqt_sort_select', $this->sort_order );
			
			$sortOrderSelect->setAttribute( 'class', 'lqt_sort_select' );
			$sortOrderSelect->addOption( wfMsg( 'lqt_sort_newest_changes' ),
											LQT_NEWEST_CHANGES );
			$sortOrderSelect->addOption( wfMsg( 'lqt_sort_newest_changes' ),
											LQT_NEWEST_THREADS );
			$sortOrderSelect->addOption( wfMsg( 'lqt_sort_newest_changes' ),
											LQT_OLDEST_THREADS );
			$html .= $sortOrderSelect->getHTML();

			if ( $this->user->isLoggedIn() ) {
				$html .= Xml::element( 'br' ) .
									Xml::checkLabel( $lqt_remember_sort, 'lqt_remember_sort',
										'lqt_remember_sort', $remember_sort_checked );
			}
			
			if ( $this->user->isAllowed( 'deletedhistory' ) ) {
				$show_deleted_checked = $this->request->getBool( 'lqt_show_deleted_threads' );
				
				$html .= Xml::element( 'br' ) .
							Xml::checkLabel( wfMsg('lqt_delete_show_checkbox' ),
											'lqt_show_deleted_threads',
											'lqt_show_deleted_threads',
											$show_deleted_checked );
			}
			
			$html .= Xml::submitButton( wfMsg( 'go' ), array( 'class' => 'lqt_go_sort',
										'name' => 'submitsort' ) );
			
			$html = Xml::tags( 'form', array( 'action' => $form_action_url,
												'method' => 'post',
												'name' => 'lqt_sort' ), $html );
			$html = Xml::tags( 'div', array( 'class' => 'lqt_view_options' ), $html );
			
			$this->output->addHTML( $html );
		}

	}

	function show() {
		global $wgHooks;
		wfLoadExtensionMessages( 'LiquidThreads' );
		// FIXME Why is a hook added here?
		$wgHooks['SkinTemplateTabs'][] = array( $this, 'customizeTabs' );

		$this->output->setPageTitle( $this->title->getPrefixedText() );
		self::addJSandCSS();
		$article = new Article( $this->title );

		$this->showHeader();
		
		$html = '';

		global $wgRequest;
		if ( $this->methodApplies( 'talkpage_new_thread' ) ) {
			$this->showNewThreadForm();
		} else {
			$this->showTalkpageViewOptions( $article );
			$newThreadLink = $this->talkpageLink( $this->title,
												wfMsgExt( 'lqt_new_thread', 'parseinline' ),
												'talkpage_new_thread', null, true,
												array( 'class' => 'lqt_start_discussion' ) );
												
			$this->output->addHTML( Xml::tags( 'strong', null, $newThreadLink ) );
		}
		
		$queryType =
			$wgRequest->getBool( 'lqt_show_deleted_threads' )
			? 'fresh' : 'fresh-undeleted';
		$threads = $this->queries->query( $queryType );

		$archiveBrowseLink = $this->talkpageLink( $this->title,
								wfMsgExt( 'lqt_browse_archive_without_recent', 'parseinline' ),
								'talkpage_archive' );
		$archiveBrowseLink = Xml::tags( 'div', array( 'class' => 'lqt_browse_archive' ),
										$archiveBrowseLink );
		$archiveBrowseLink = Xml::tags( 'div', array( 'class' => 'lqt_archive_teaser_empty' ),
										$archiveBrowseLink );
		
		$recently_archived_threads = $this->queries->query( 'recently-archived' );
		
		$toc = '';
		if ( count( $threads ) > 1 || count( $recently_archived_threads ) > 0 ) {
			$toc = $this->getTOC( $threads );
		}
		
		$archiveWidget = $this->getArchiveWidget( $recently_archived_threads );

		$html = Xml::tags( 'div', array( 'class' => 'lqt_toc_archive_wrapper' ),
							$archiveBrowseLink . $toc .
							$archiveWidget );
		
		$html .= Xml::element( 'br', array( 'style' => 'clear: both;' ) );
		
		$this->output->addHTML( $html );

		foreach ( $threads as $t ) {
			$this->showThread( $t );
		}
		
		return false;
	}
}
