<?php

use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\Article;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Xml\Xml;
use MediaWiki\Xml\XmlSelect;

class TalkpageView extends LqtView {
	public const LQT_NEWEST_CHANGES = 'nc';
	public const LQT_NEWEST_THREADS = 'nt';
	public const LQT_OLDEST_THREADS = 'ot';

	/** @var string[] */
	protected $mShowItems = [ 'toc', 'options', 'header' ];
	/** @var Article */
	protected $talkpage;

	/**
	 * @var \MediaWiki\Linker\LinkRenderer
	 */
	protected $linkRenderer;

	public function __construct( &$output, &$article, &$title, &$user, &$request ) {
		parent::__construct( $output, $article, $title, $user, $request );

		$this->talkpage = $article;
		$this->linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
	}

	public function setTalkPage( $tp ) {
		$this->talkpage = $tp;
	}

	public static function customizeTalkpageNavigation( $skin, &$links, $view ) {
		$remove = [ 'views/edit', 'views/viewsource', 'actions/delete' ];

		foreach ( $remove as $rem ) {
			[ $section, $item ] = explode( '/', $rem, 2 );
			unset( $links[$section][$item] );
		}

		if ( isset( $links['views']['history'] ) ) {
			$title = $view->article->getTitle();
			$history_url = $title->getLocalURL( 'lqt_method=talkpage_history' );
			$links['views']['history']['href'] = $history_url;
		}
	}

	public function customizeNavigation( $skintemplate, &$links ) {
		self::customizeTalkpageNavigation( $skintemplate, $links, $this );
	}

	public function showHeader() {
		/* Show the contents of the actual talkpage article if it exists. */

		$article = $this->talkpage;
		$quickCanEdit = MediaWikiServices::getInstance()->getPermissionManager()
			->quickUserCan( 'edit', $this->user, $article->getTitle() );

		// If $article_text == "", the talkpage was probably just created
		// when the first thread was posted to make the links blue.
		if ( $article->getPage()->exists() ) {
			$html = '';

			$article->view();

			$actionLinks = [];
			$msgKey = $quickCanEdit ? 'edit' : 'viewsource';
			$actionLinks[] = $this->linkRenderer->makeLink(
				$article->getTitle(),
				new HtmlArmor( wfMessage( $msgKey )->parse() . "↑" ),
				[],
				[ 'action' => 'edit' ]
			);

			$actionLinks[] = $this->linkRenderer->makeLink(
				$this->title,
				new HtmlArmor( wfMessage( 'history_short' )->parse() . "↑" ),
				[],
				[ 'action' => 'history' ]
			);

			if ( $this->user->isAllowed( 'delete' ) ) {
				$actionLinks[] = $this->linkRenderer->makeLink(
					$article->getTitle(),
					new HtmlArmor( wfMessage( 'delete' )->parse() . '↑' ),
					[],
					[ 'action' => 'delete' ]
				);
			}

			$actions = '';
			foreach ( $actionLinks as $link ) {
				$actions .= Xml::tags( 'li', null, "[$link]" ) . "\n";
			}
			$actions = Xml::tags( 'ul', [ 'class' => 'lqt_header_commands' ], $actions );
			$html .= $actions;

			$html = Xml::tags( 'div', [ 'class' => 'lqt_header_content' ], $html );

			$this->output->addHTML( $html );
		} elseif ( $quickCanEdit ) {
			$editLink = $this->linkRenderer->makeLink(
				$this->talkpage->getTitle(),
				new HtmlArmor( wfMessage( 'lqt_add_header' )->parse() ),
				[],
				[ 'action' => 'edit' ]
			);

			$html = Xml::tags( 'p', [ 'class' => 'lqt_header_notice' ], "[$editLink]" );

			$this->output->addHTML( $html );
		}
	}

	/**
	 * @param Thread[] $threads
	 * @return string
	 */
	public function getTOC( array $threads ) {
		global $wgLang;

		$html = '';

		$h2_header = Xml::tags( 'h2', null, wfMessage( 'lqt_contents_title' )->parse() );

		// Header row
		$headerRow = '';
		$headers = [ 'lqt_toc_thread_title',
				'lqt_toc_thread_replycount', 'lqt_toc_thread_modified' ];
		foreach ( $headers as $msg ) {
			$headerRow .= Xml::tags( 'th', null, wfMessage( $msg )->parse() );
		}
		$headerRow = Xml::tags( 'tr', null, $headerRow );
		$headerRow = Xml::tags( 'thead', null, $headerRow );

		// Table body
		$rows = [];
		$services = MediaWikiServices::getInstance();
		$contLang = $services->getContentLanguage();
		$langConv = $services
				->getLanguageConverterFactory()
				->getLanguageConverter( $services->getContentLanguage() );
		foreach ( $threads as $thread ) {
			if ( $thread->root() && !$thread->root()->getPage()->getContent() &&
				!LqtView::threadContainsRepliesWithContent( $thread )
			) {
				continue;
			}

			$row = '';
			$anchor = '#' . $this->anchorName( $thread );
			$subject = Xml::tags( 'a', [ 'href' => $anchor ],
					Threads::stripHTML( $langConv->convert( $thread->formattedSubject() ) ) );
			$row .= Xml::tags( 'td', [ 'dir' => $contLang->getDir() ], $subject );

			$row .= Xml::element( 'td', null, $wgLang->formatNum( $thread->replyCount() ) );

			$timestamp = $wgLang->timeanddate( $thread->modified(), true );
			$row .= Xml::element( 'td', null, $timestamp );

			$row = Xml::tags( 'tr', null, $row );
			$rows[] = $row;
		}

		$html .= $headerRow . "\n" . Xml::tags( 'tbody', null, implode( "\n", $rows ) );
		$html = $h2_header . Xml::tags( 'table', [ 'class' => 'lqt_toc' ], $html );
		// wrap our output in a div for containment
		$html = Xml::tags( 'div', [ 'class' => 'lqt-contents-wrapper' ], $html );

		return $html;
	}

	public function getList( $kind, $class, $id, $contents ) {
		$html = '';
		foreach ( $contents as $li ) {
			$html .= Xml::tags( 'li', null, $li );
		}
		$html = Xml::tags( $kind, [ 'class' => $class, 'id' => $id ], $html );

		return $html;
	}

	public function getArchiveWidget() {
		$html = '';
		$html = Xml::tags( 'div', [ 'class' => 'lqt_archive_teaser' ], $html );
		return $html;
	}

	public function showTalkpageViewOptions() {
		$form_action_url = $this->talkpageUrl( $this->title, 'talkpage_sort_order' );
		$html = '';

		$html .= Xml::label( wfMessage( 'lqt_sorting_order' )->text(), 'lqt_sort_select' ) . ' ';

		$sortOrderSelect =
			new XmlSelect( 'lqt_order', 'lqt_sort_select', $this->getSortType() );

		$sortOrderSelect->setAttribute( 'class', 'lqt_sort_select' );
		$sortOrderSelect->addOption(
			wfMessage( 'lqt_sort_newest_changes' )->text(),
			self::LQT_NEWEST_CHANGES
		);
		$sortOrderSelect->addOption(
			wfMessage( 'lqt_sort_newest_threads' )->text(),
			self::LQT_NEWEST_THREADS
		);
		$sortOrderSelect->addOption(
			wfMessage( 'lqt_sort_oldest_threads' )->text(),
			self::LQT_OLDEST_THREADS
		);
		$html .= $sortOrderSelect->getHTML();

		$html .= Html::submitButton(
			wfMessage( 'lqt-changesortorder' )->text(),
			[ 'class' => 'lqt_go_sort' ]
		);
		$html .= Html::hidden( 'title', $this->title->getPrefixedText() );

		$html = Xml::tags(
			'form',
			[
				'action' => $form_action_url,
				'method' => 'get',
				'name' => 'lqt_sort'
			],
			$html
		);
		$html = Xml::tags( 'div', [ 'class' => 'lqt_view_options' ], $html );

		return $html;
	}

	/**
	 * @return bool
	 */
	public function show() {
		$this->output->addModules( 'ext.liquidThreads' );

		$article = $this->talkpage;
		if ( !LqtDispatch::isLqtPage( $article->getTitle() ) ) {
			$this->output->addWikiMsg( 'lqt-not-discussion-page' );
			return false;
		}

		$this->output->setPageTitle( $this->title->getPrefixedText() );

		// Expose feed links.
		global $wgFeedClasses;
		$apiParams = [ 'action' => 'feedthreads', 'type' => 'replies|newthreads',
				'talkpage' => $this->title->getPrefixedText() ];
		$urlPrefix = wfScript( 'api' ) . '?';
		foreach ( $wgFeedClasses as $format => $class ) {
			$theseParams = $apiParams + [ 'feedformat' => $format ];
			$url = $urlPrefix . wfArrayToCgi( $theseParams );
			$this->output->addFeedLink( $format, $url );
		}

		if ( $this->request->getBool( 'lqt_inline' ) ) {
			$this->doInlineEditForm();
			return false;
		}

		$this->output->addHTML(
			Xml::openElement( 'div', [ 'class' => 'lqt-talkpage' ] )
		);

		// Search!
		if ( $this->request->getCheck( 'lqt_search' ) ) {
			$q = $this->request->getText( 'lqt_search' );
			$q .= ' ondiscussionpage:' . $article->getTitle()->getPrefixedText();

			$params = [
				'search' => $q,
				'fulltext' => 1,
				'ns' . NS_LQT_THREAD => 1,
				'srbackend' => 'LuceneSearch',
			];

			$t = SpecialPage::getTitleFor( 'Search' );
			$url = $t->getLocalURL( wfArrayToCgi( $params ) );

			$this->output->redirect( $url );
			return true;
		}

		if ( $this->shouldShow( 'header' ) ) {
			$this->showHeader();
		}

		global $wgLang;

		// This closes the div of mw-content-ltr/rtl containing lang and dir attributes
		$this->output->addHTML(
			Html::closeElement( 'div' ) . Html::openElement( 'div', [
				'class' => 'lqt-talkpage',
				'lang' => $wgLang->getCode(),
				'dir' => $wgLang->getDir()
			]
		) );

		$html = '';

		// Set up a per-page header for new threads, search box, and sorting stuff.

		$talkpageHeader = '';
		$newThreadLink = '';

		if ( Thread::canUserPost( $this->user, $this->talkpage, 'quick' ) ) {
			$newThreadText = new HtmlArmor( wfMessage( 'lqt_new_thread' )->parse() );
			$newThreadLink = $this->linkRenderer->makeKnownLink(
				$this->title, $newThreadText,
				[ 'lqt_talkpage' => $this->talkpage->getTitle()->getPrefixedText() ],
				[ 'lqt_method' => 'talkpage_new_thread' ]
			);

			$newThreadLink = Xml::tags(
				'strong',
				[ 'class' => 'lqt_start_discussion' ],
				$newThreadLink
			);

			$talkpageHeader .= $newThreadLink;
		}

		global $wgSearchTypeAlternatives, $wgSearchType;
		if ( $wgSearchType == "LuceneSearch"
			|| in_array( "LuceneSearch", $wgSearchTypeAlternatives ?: [] )
		) {
			$talkpageHeader .= $this->getSearchBox();
		}
		$talkpageHeader .= $this->showTalkpageViewOptions();
		$talkpageHeader = Xml::tags(
			'div',
			[ 'class' => 'lqt-talkpage-header' ],
			$talkpageHeader
		);

		if ( $this->shouldShow( 'options' ) ) {
			$this->output->addHTML( $talkpageHeader );
		} elseif ( $this->shouldShow( 'simplenew' ) ) {
			$this->output->addHTML( $newThreadLink );
		}

		if ( $this->methodApplies( 'talkpage_new_thread' ) ) {
			$this->showNewThreadForm( $this->talkpage );
		} else {
			$this->output->addHTML( Xml::tags( 'div',
				[ 'class' => 'lqt-new-thread lqt-edit-form' ], '' ) );
		}

		$pager = $this->getPager();

		$threads = $this->getPageThreads( $pager );

		if ( count( $threads ) > 0 && $this->shouldShow( 'toc' ) ) {
			$html .= $this->getTOC( $threads );
		} elseif ( count( $threads ) == 0 ) {
			$html .= Xml::tags( 'div', [ 'class' => 'lqt-no-threads' ],
				wfMessage( 'lqt-no-threads' )->parse() );
		}

		$this->output->addModuleStyles( $pager->getModuleStyles() );

		$html .= $pager->getNavigationBar();
		$html .= Xml::openElement( 'div', [ 'class' => 'lqt-threads lqt-talkpage-threads' ] );

		$this->output->addHTML( $html );

		foreach ( $threads as $t ) {
			$this->showThread( $t );
		}

		$this->output->addHTML(
			Xml::closeElement( 'div' ) .
			$pager->getNavigationBar() .
			Xml::closeElement( 'div' )
		);

		return false;
	}

	private function getSearchBox() {
		$html = '';
		$html .= Xml::inputLabel(
			wfMessage( 'lqt-search-label' )->text(),
			'lqt_search',
			'lqt-search-box',
			45
		);

		$html .= ' ' . Html::submitButton( wfMessage( 'lqt-search-button' )->text() );
		$html .= Html::hidden( 'title', $this->title->getPrefixedText() );
		$html = Xml::tags(
			'form',
			[
				'action' => $this->title->getLocalURL(),
				'method' => 'get'
			],
			$html
		);

		$html = Xml::tags( 'div', [ 'class' => 'lqt-talkpage-search' ], $html );

		return $html;
	}

	private function getPager() {
		$sortType = $this->getSortType();
		return new LqtDiscussionPager( $this->talkpage, $sortType );
	}

	private function getPageThreads( $pager ) {
		$rows = $pager->getRows();

		return Thread::bulkLoad( $rows );
	}

	private function getSortType() {
		// Determine sort order
		if ( $this->request->getCheck( 'lqt_order' ) ) {
			// Sort order is explicitly specified through UI
			$lqt_order = $this->request->getVal( 'lqt_order' );
			switch ( $lqt_order ) {
				case 'nc':
					return self::LQT_NEWEST_CHANGES;
				case 'nt':
					return self::LQT_NEWEST_THREADS;
				case 'ot':
					return self::LQT_OLDEST_THREADS;
			}
		}

		// Default
		return self::LQT_NEWEST_CHANGES;
	}

	/**
	 * Hide a number of items from the view
	 * Valid values: toc, options, header
	 *
	 * @param string[]|string $items
	 */
	public function hideItems( $items ) {
		$this->mShowItems = array_diff( $this->mShowItems, (array)$items );
	}

	/**
	 * Show a number of items in the view
	 * Valid values: toc, options, header
	 *
	 * @param string[]|string $items
	 */
	public function showItems( $items ) {
		$this->mShowItems = array_merge( $this->mShowItems, (array)$items );
	}

	/**
	 * Whether or not to show an item
	 *
	 * @param string $item
	 * @return bool
	 */
	public function shouldShow( $item ) {
		return in_array( $item, $this->mShowItems );
	}

	/**
	 * @param string[] $items
	 */
	public function setShownItems( $items ) {
		$this->mShowItems = $items;
	}
}
