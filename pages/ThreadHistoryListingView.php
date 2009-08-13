<?php

if ( !defined( 'MEDIAWIKI' ) ) die;

class ThreadHistoryListingView extends ThreadPermalinkView {
	function show() {
		global $wgHooks;
		$wgHooks['SkinTemplateTabs'][] = array( $this, 'customizeTabs' );

		if ( ! $this->thread ) {
			$this->showMissingThreadPage();
			return false;
		}
		self::addJSandCSS();
		wfLoadExtensionMessages( 'LiquidThreads' );

		$this->output->setSubtitle( $this->getSubtitle() . '<br />' . wfMsg( 'lqt_hist_listing_subtitle' ) );

		$this->showThreadHeading( $this->thread );
		
		$pager = new ThreadHistoryPager( $this, $this->thread );
		
		$html = $pager->getNavigationBar() .
				$pager->getBody() .
				$pager->getNavigationBar();
				
		$this->output->addHTML( $html );
		
		$this->showThread( $this->thread );
		
		return false;
	}
	
	function rowForThread( $t ) {
		global $wgLang, $wgOut; // TODO global.
		wfLoadExtensionMessages( 'LiquidThreads' );
		/* TODO: best not to refer to LqtView class directly. */
		/* We don't use oldid because that has side-effects. */
		
		$sk = $this->user->getSkin();		

		$change_names =
			array(
				Threads::CHANGE_EDITED_ROOT => wfMsg( 'lqt_hist_comment_edited' ),
				Threads::CHANGE_EDITED_SUMMARY => wfMsg( 'lqt_hist_summary_changed' ),
				Threads::CHANGE_REPLY_CREATED => wfMsg( 'lqt_hist_reply_created' ),
				Threads::CHANGE_NEW_THREAD => wfMsg( 'lqt_hist_thread_created' ),
				Threads::CHANGE_DELETED => wfMsg( 'lqt_hist_deleted' ),
				Threads::CHANGE_UNDELETED => wfMsg( 'lqt_hist_undeleted' ),
				Threads::CHANGE_MOVED_TALKPAGE => wfMsg( 'lqt_hist_moved_talkpage' ),
			);
			
		$change_label = '';
		
		if( array_key_exists( $t->changeType(), $change_names ) ) {
			$change_label = $change_names[$t->changeType()];
		}

		$user_id = $t->changeUser()->getID();
		$user_text = $t->changeUser()->getName();
		$userLinks = $sk->userLink( $user_id, $user_text ) .
						$sk->userToolLinks( $user_id, $user_text );

		$change_comment = $t->changeComment();
		if ( $change_comment != '' ) {
			$change_comment = $sk->commentBlock( $change_comment );
		}

		$html = '';
		
		$linkText = $wgLang->timeanddate( $t->modified(), true );
		$link = self::permalink( $this->thread, $linkText, null, null, null, array(),
							array( 'lqt_oldid' => $t->revisionNumber() ) );
		
		$html .= Xml::tags( 'td', null, $link );
		$html .= Xml::tags( 'td', null, $userLinks );
		$html .= Xml::tags( 'td', null, $change_label );
		$html .= Xml::tags( 'td', null, $change_comment );

		$html = Xml::tags( 'tr', null, $html );
		
		return $html;
	}
}

class ThreadHistoryPager extends ReverseChronologicalPager {
	function __construct( $view, $thread ) {
		parent::__construct();
		
		$this->thread = $thread;
		$this->view = $view;
	}
	
	function getQueryInfo() {
		$queryInfo =
			array(
				'tables' => array( 'historical_thread' ),
				'fields' => array( 'hthread_contents', 'hthread_revision' ),
				'conds' => array( 'hthread_id' => $this->thread->id() ),
				'options' => array( 'order by' => 'hthread_revision desc' ),
			);
			
		return $queryInfo;
	}
	
	function formatRow( $row ) {
		$hthread = HistoricalThread::fromTextRepresentation( $row->hthread_contents );
		return $this->view->rowForThread( $hthread );
	}
	
	function getStartBody() {
		$headers = array(
						'lqt-history-time',
						'lqt-history-user',
						'lqt-history-action',
					);

		$html = '';
		
		foreach( $headers as $header ) {
			$html .= Xml::tags( 'th', null,
								wfMsgExt( $header, 'parseinline' ) );
		}
		
		$html = Xml::tags( 'tr', null, $html );
		$html = Xml::tags( 'thead', null, $html );
		$html = "<table>$html<tbody>";
		
		return $html;
	}
	
	function getEndBody() {
		return "</tbody></table>";
	}
	
	function getIndexField() {
		return 'hthread_revision';
	}
}

