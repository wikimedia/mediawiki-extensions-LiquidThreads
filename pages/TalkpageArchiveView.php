<?php

if ( !defined( 'MEDIAWIKI' ) ) die;

class TalkpageArchiveView extends TalkpageView {
	function __construct( &$output, &$article, &$title, &$user, &$request ) {
		parent::__construct( $output, $article, $title, $user, $request );
	}

	function show() {
		global $wgHooks, $wgOut;
		$wgHooks['SkinTemplateTabs'][] = array( $this, 'customizeTabs' );

		wfLoadExtensionMessages( 'LiquidThreads' );
		$this->output->setPageTitle( $this->title->getPrefixedText() );
		$this->output->setSubtitle( wfMsg( 'lqt-archive-subtitle' ) );
		self::addJSandCSS();
		
		$this->output->addWikiMsg( 'lqt-archive-intro',
									$this->article->getTitle()->getPrefixedText() );

		$pager = new TalkpageArchivePager( $this->article, $this );
		
		$html = $pager->getNavigationBar() .
				$pager->getBody() .
				$pager->getNavigationBar();
				
		$wgOut->addHTML( $html );

		return false;
	}
}

class TalkpageArchivePager extends TablePager {
	
	public function __construct( $article, $view ) {
		parent::__construct();
		$this->article = $article;
		$this->view = $view;
	}

	public function isFieldSortable( $field ) {
		$sortable = array( 'page_title', 'thread_created', 'thread_modified' );
		
		return in_array( $field, $sortable );
	}
	
	public function formatValue( $field, $value ) {
		global $wgUser, $wgLang;
		
		$sk = $wgUser->getSkin();
		
		switch( $field ) {
			case 'page_title':
				$title = Title::makeTitle( NS_LQT_THREAD, $value );
				$split = Thread::splitIncrementFromSubject( $title->getText() );
				return $sk->link( $title, $split[1] );
			case 'thread_summary_page':
				$summary = '';
				
				if ($value) {
					$page = Article::newFromId( $value );
					$summary = $this->view->showPostBody( $page );
				} elseif ($this->mCurrentRow->thread_type == Threads::TYPE_MOVED) {
					$thread = new Thread( $this->mCurrentRow, array() );
					
					$rt = $thread->redirectThread();
					if ( $rt->hasSummary() ) {
						$summaryPage = $rt->summary();
						$summary = $this->view->showPostBody( $summaryPage );
					}
				}
				return $summary;
			case 'thread_created':
			case 'thread_modified':
				return $wgLang->timeanddate( $value, true );
			case 'rev_user_text':
				$uid = $this->mCurrentRow->rev_user;
				return $sk->userLink( $uid, $value ) . $sk->userToolLinks( $uid, $value );
			default:
				return $value;
		}
	}
	
	public function getDefaultSort() {
		return 'thread_created';
	}
	
	public function getFieldNames() {
		$fieldToMsg =
			array(
				'thread_created' => 'lqt-thread-created',
				'page_title' => 'lqt-title',
				'thread_modified' => 'lqt_toc_thread_modified',
				'rev_user_text' => 'lqt_toc_thread_author',
				'thread_summary_page' => 'lqt-summary',
			);
		
		return array_map( 'wfMsg', $fieldToMsg );
	}
	
	public function getQueryInfo() {
		$dbr = wfGetDB( DB_SLAVE );
		
		$hasSummaryClauses = array( 'thread_summary_page is not null',
									'thread_type' => Threads::TYPE_MOVED );
		$hasSummaryClause = $dbr->makeList( $hasSummaryClauses, LIST_OR );
		
		$queryInfo =
			array(
				'tables' => array( 'thread', 'page', 'revision' ),
				'fields' => '*',
				'conds' =>
					array(
						Threads::articleClause( $this->article ),
						Threads::topLevelClause(),
						$hasSummaryClause,
						'rev_parent_id' => 0,
					),
				'join_conds' =>
					array(
						'page' => array( 'left join', 'thread_root=page_id' ),
						'revision' => array( 'left join', 'rev_page=page_id' ),
					),
			);
			

		return $queryInfo;
	}
}
