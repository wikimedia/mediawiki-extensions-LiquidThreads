<?php

if ( !defined( 'MEDIAWIKI' ) ) die;

class TalkpageView extends LqtView {
	/* Added to SkinTemplateTabs hook in TalkpageView::show(). */
	function customizeTabs( $skintemplate, &$content_actions ) {
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

	function showHeader() {
		/* Show the contents of the actual talkpage article if it exists. */
		
		global $wgUser;
		$sk = $wgUser->getSkin();

		$article = new Article( $this->title );

		$oldid = $this->request->getVal( 'oldid', null );

		wfLoadExtensionMessages( 'LiquidThreads' );
		// If $article_text == "", the talkpage was probably just created
		// when the first thread was posted to make the links blue.
		if ( $article->exists() && $article->getContent() ) {
			$html = '';
			
			$article->view();
			
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
			
			$author = $thread->author();
			$authorLink = $sk->userLink( $author->getId(), $author->getName() );
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

	function getArchiveWidget( ) {
		wfLoadExtensionMessages( 'LiquidThreads' );
		$url = $this->talkpageUrl( $this->title, 'talkpage_archive' );
	
		$html = '';
		$html = Xml::tags( 'div', array( 'class' => 'lqt_archive_teaser' ), $html );
		return $html;
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
			$lqt_remember_sort = wfMsg( 'lqt_remember_sort' ) ;
			
			$form_action_url = $this->talkpageUrl( $this->title, 'talkpage_sort_order' );
			$go = wfMsg( 'go' );
			
			$html = '';
			
			$html .= Xml::label( wfMsg( 'lqt_sorting_order' ), 'lqt_sort_select' ) . ' ';

			$sortOrderSelect =
				new XmlSelect( 'lqt_order', 'lqt_sort_select', $this->getSortType() );
			
			$sortOrderSelect->setAttribute( 'class', 'lqt_sort_select' );
			$sortOrderSelect->addOption( wfMsg( 'lqt_sort_newest_changes' ),
											LQT_NEWEST_CHANGES );
			$sortOrderSelect->addOption( wfMsg( 'lqt_sort_newest_threads' ),
											LQT_NEWEST_THREADS );
			$sortOrderSelect->addOption( wfMsg( 'lqt_sort_oldest_threads' ),
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
	
	function getArchiveTeaser() {
		$archiveBrowseLink = $this->talkpageLink( $this->title,
								wfMsgExt( 'lqt_browse_archive_without_recent', 'parseinline' ),
								'talkpage_archive' );
		$archiveBrowseLink = Xml::tags( 'div', array( 'class' => 'lqt_browse_archive' ),
										$archiveBrowseLink );
		$archiveBrowseLink = Xml::tags( 'div', array( 'class' => 'lqt_archive_teaser_empty' ),
										$archiveBrowseLink );
										
		$archiveWidget = $this->getArchiveWidget( $recently_archived_threads );

		$html = Xml::tags( 'div', array( 'class' => 'lqt_toc_archive_wrapper' ),
							$archiveBrowseLink . $toc .
							$archiveWidget );
	}

	function show() {
		global $wgHooks;
		wfLoadExtensionMessages( 'LiquidThreads' );
		// FIXME Why is a hook added here?
		$wgHooks['SkinTemplateTabs'][] = array( $this, 'customizeTabs' );

		$this->output->setPageTitle( $this->title->getPrefixedText() );
		self::addJSandCSS();
		$article = new Article( $this->title );
		
		if ( $this->request->getBool( 'lqt_inline' ) ) {
			$this->doInlineEditForm();
			return false;
		}

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
												array( 'class' => 'lqt_start_discussion' ),
												array( 'known' ) );
												
			$this->output->addHTML( Xml::tags( 'strong', null, $newThreadLink ) );
		}
		
		$pager = $this->getPager();
		
		$threads = $this->getPageThreads( $pager );
		
// 		$html .= $this->getArchiveWidget();

		if ( count($threads) > 0 ) {
			$html .= Xml::element( 'br', array( 'style' => 'clear: both;' ) );
			$html .= $this->getTOC( $threads );
		} else {
			$html .= wfMsgExt( 'lqt-no-threads', 'parseinline' );
		}
		
		$html .= $pager->getNavigationBar();
		
		$this->output->addHTML( $html );

		foreach ( $threads as $t ) {
			$this->showThread( $t );
		}
		
		$this->output->addHTML( $pager->getNavigationBar() );
		
		return false;
	}
	
	function getPager() {
		$showDeleted = $this->request->getBool( 'lqt_show_deleted_threads' );
		$showDeleted = $showDeleted && $this->user->isAllowed( 'deletedhistory' );
		
		$sortType = $this->getSortType();
		return new LqtDiscussionPager( $this->article, $sortType, $showDeleted );
	}
	
	function getPageThreads( $pager ) {
		$rows = $pager->getRows();
		
		return Thread::bulkLoad( $rows );
	}
	
	function getSortType() {
		// Determine sort order
		if ( $this->methodApplies( 'talkpage_sort_order' ) ) {
			// Sort order is explicitly specified through UI
			$lqt_order = $this->request->getVal( 'lqt_order' );
			switch( $lqt_order ) {
				case 'nc':
					return LQT_NEWEST_CHANGES;
				case 'nt':
					return LQT_NEWEST_THREADS;
				case 'ot':
					return LQT_OLDEST_THREADS;
			}
		} else {
			// Sort order set in user preferences overrides default
			$user_order = $this->user->getOption( 'lqt_sort_order' ) ;
			if ( $user_order ) {
				return $user_order;
			}
		}
		
		// Default
		return LQT_NEWEST_CHANGES;
	}
}

class LqtDiscussionPager extends IndexPager {

	function __construct( $article, $orderType, $showDeleted ) {
		$this->article = $article;
		$this->orderType = $orderType;
		$this->showDeleted = $showDeleted;
		
		parent::__construct();
		
		$this->mLimit = 20;
	}
	
	function getQueryInfo() {
		$queryInfo =
			array(
				'tables' => array( 'thread' ),
				'fields' => '*',
				'conds' =>
					array(
						Threads::articleClause( $this->article ),
						Threads::topLevelClause(),
					),
			);
			
		if ( !$this->showDeleted ) {
			$queryInfo['where']['thread_deleted'] = 0;
		}
			
		return $queryInfo;
	}
	
	// Adapted from getBody().
	function getRows() {
		if ( !$this->mQueryDone ) {
			$this->doQuery();
		}
		
		# Don't use any extra rows returned by the query
		$numRows = min( $this->mResult->numRows(), $this->mLimit );

		$rows = array();
		
		if ( $numRows ) {
			if ( $this->mIsBackwards ) {
				for ( $i = $numRows - 1; $i >= 0; $i-- ) {
					$this->mResult->seek( $i );
					$row = $this->mResult->fetchObject();
					$rows[] = $row;
				}
			} else {
				$this->mResult->seek( 0 );
				for ( $i = 0; $i < $numRows; $i++ ) {
					$row = $this->mResult->fetchObject();
					$rows[] = $row;
				}
			}
		}
		
		return $rows;
	}
	
	function formatRow( $row ) {
		// No-op, we get the list of rows from getRows()
	}
	
	function getIndexField() {
		switch( $this->orderType ) {
			case LQT_NEWEST_CHANGES:
				return 'thread_modified';
			case LQT_OLDEST_THREADS:
			case LQT_NEWEST_THREADS:
				return 'thread_created';
			default:
				throw new MWException( "Unknown sort order ".$this->orderType );
		}
	}
	
	function getDefaultDirections() {
		switch( $this->orderType ) {
			case LQT_NEWEST_CHANGES:
			case LQT_NEWEST_THREADS:
				return true; // Descending
			case LQT_OLDEST_THREADS:
				return false; // Ascending
			default:
				throw new MWException( "Unknown sort order ".$this->orderType );
		}
	}
	
	/**
	 * A navigation bar with images
	 * Stolen from TablePager because it's pretty.
	 */
	function getNavigationBar() {
		global $wgStylePath, $wgContLang;

		if ( method_exists( $this, 'isNavigationBarShown' ) &&
				!$this->isNavigationBarShown() )
			return '';

		$path = "$wgStylePath/common/images";
		$labels = array(
			'first' => 'table_pager_first',
			'prev' => 'table_pager_prev',
			'next' => 'table_pager_next',
			'last' => 'table_pager_last',
		);
		$images = array(
			'first' => $wgContLang->isRTL() ? 'arrow_last_25.png' : 'arrow_first_25.png',
			'prev' =>  $wgContLang->isRTL() ? 'arrow_right_25.png' : 'arrow_left_25.png',
			'next' =>  $wgContLang->isRTL() ? 'arrow_left_25.png' : 'arrow_right_25.png',
			'last' =>  $wgContLang->isRTL() ? 'arrow_first_25.png' : 'arrow_last_25.png',
		);
		$disabledImages = array(
			'first' => $wgContLang->isRTL() ? 'arrow_disabled_last_25.png' : 'arrow_disabled_first_25.png',
			'prev' =>  $wgContLang->isRTL() ? 'arrow_disabled_right_25.png' : 'arrow_disabled_left_25.png',
			'next' =>  $wgContLang->isRTL() ? 'arrow_disabled_left_25.png' : 'arrow_disabled_right_25.png',
			'last' =>  $wgContLang->isRTL() ? 'arrow_disabled_first_25.png' : 'arrow_disabled_last_25.png',
		);

		$linkTexts = array();
		$disabledTexts = array();
		foreach ( $labels as $type => $label ) {
			$msgLabel = wfMsgHtml( $label );
			$linkTexts[$type] = "<img src=\"$path/{$images[$type]}\" alt=\"$msgLabel\"/><br/>$msgLabel";
			$disabledTexts[$type] = "<img src=\"$path/{$disabledImages[$type]}\" alt=\"$msgLabel\"/><br/>$msgLabel";
		}
		$links = $this->getPagingLinks( $linkTexts, $disabledTexts );

		$navClass = htmlspecialchars( $this->getNavClass() );
		$s = "<table class=\"$navClass\" align=\"center\" cellpadding=\"3\"><tr>\n";
		$cellAttrs = 'valign="top" align="center" width="' . 100 / count( $links ) . '%"';
		foreach ( $labels as $type => $label ) {
			$s .= "<td $cellAttrs>{$links[$type]}</td>\n";
		}
		$s .= "</tr></table>\n";
		return $s;
	}
	
	function getNavClass() {
		return 'TalkpagePager_nav';
	}
}
