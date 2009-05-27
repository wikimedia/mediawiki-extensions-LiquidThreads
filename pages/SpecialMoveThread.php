<?php

if ( !defined( 'MEDIAWIKI' ) ) die;

class SpecialMoveThread extends UnlistedSpecialPage {
	private $user, $output, $request, $title, $thread;

	function __construct() {
		parent::__construct( 'Movethread' );
		$this->includable( false );
	}

	/**
	* @see SpecialPage::getDescription
	*/
	function getDescription() {
		wfLoadExtensionMessages( 'LiquidThreads' );
		return wfMsg( 'lqt_movethread' );
	}
	
	function getFormFields() {
		return
			array(
				'dest-title' =>
					array(
						'label-message' => 'lqt_move_destinationtitle',
						'type' => 'text',
						'validation-callback' => array( $this, 'validateTarget' ),
					),
				'reason' =>
					array(
						'label-message' => 'movereason',
						'type' => 'text',
					),
			);
	}

	function execute( $par ) {
		wfLoadExtensionMessages( 'LiquidThreads' );
		
		global $wgOut;
		
		// Page title
		$wgOut->setPageTitle( wfMsg( 'lqt_movethread' ) );
		
		// Handle parameter
		$this->mTarget = $par;
		if ( $par === null || $par === "" ) {
			wfLoadExtensionMessages( 'LiquidThreads' );
			$this->output->addHTML( wfMsg( 'lqt_threadrequired' ) );
			return;
		}
		$thread = Threads::withRoot( new Article( Title::newFromURL( $par ) ) );
		if ( !$thread ) {
			$this->output->addHTML( wfMsg( 'lqt_nosuchthread' ) );
			return;
		}
		$this->mThread = $thread;
		
		// Generate introduction
		$intro = '';
		
		global $wgUser;
		$sk = $wgUser->getSkin();
		$page = $article_name = $thread->article()->getTitle()->getPrefixedText();
		
		$edit_text = wfMsgExt( 'lqt_move_torename_edit', 'parseinline' );
		$edit_link = $sk->link( $thread->title(), $edit_text, array(),
						array( 'lqt_method' => 'edit', 'lqt_operand' => $thread->id() ) );
		
		$intro .= wfMsgExt( 'lqt_move_movingthread', 'parse',
					array('[['.$this->mTarget.']]', '[['.$page.']]') );
		$intro .= wfMsgExt( 'lqt_move_torename', array( 'parse', 'replaceafter' ),
							array( $edit_link ) );
		
		$form = new HTMLForm( $this->getFormFields(), 'lqt-move' );
		
		$form->setSubmitText( wfMsg('lqt_move_move') );
		$form->setTitle( SpecialPage::getTitleFor( 'MoveThread', $par ) );
		$form->setSubmitCallback( array( $this, 'trySubmit' ) );
		$form->setIntro( $intro );
		
		$form->show();
	}
	
	function checkUserRights( $oldTitle, $newTitle ) {
		global $wgUser, $wgOut;
		
		$oldErrors = $oldTitle->getUserPermissionsErrors( 'move', $wgUser );
		$newErrors = $newTitle->getUserPermissionsErrors( 'move', $wgUser );
		
		// Custom merge/unique function because we don't have the second parameter to
		// array_unique on Wikimedia.
		$mergedErrors = array();
		foreach( array_merge( $oldErrors, $newErrors ) as $key => $value ) {
			if ( !is_numeric($key) ) {
				$mergedErrors[$key] = $value;
			} elseif ( !in_array( $value, $mergedErrors ) ) {
				$mergedErrors[] = $value;
			}
		}
		
		if ( count($mergedErrors) > 0 ) {
			return 	$wgOut->parse(
						$wgOut->formatPermissionsErrorMessage( $mergedErrors, 'move' )
					);
		}

		return true;
	}
	
	function trySubmit( $data ) {
		// Load data
		$tmp = $data['dest-title'];
		$newtitle = Title::newFromText( $tmp );
		$reason = $data['reason'];
		
		$rightsResult = $this->checkUserRights( $this->mThread->title(), $newtitle );
		
		if ($rightsResult !== true)
			return $rightsResult;

		// TODO no status code from this method.
		$this->mThread->moveToPage( $newtitle, $reason, true );
		
		global $wgOut, $wgUser;
		$sk = $wgUser->getSkin();
		$wgOut->addHTML( wfMsgExt( 'lqt_move_success', array( 'parse', 'replaceafter' ),
			array( $sk->link( $newtitle ) ) ) );
		
		return true;
	}
	
	function validateTarget( $target ) {
		if (!$target) {
			return wfMsgExt( 'lqt_move_nodestination', 'parseinline' );
		}
			
		$title = Title::newFromText( $target );
		
		if ( !$title || !LqtDispatch::isLqtPage( $title ) ) {
			return wfMsgExt( 'lqt_move_thread_bad_destination', 'parseinline' );
		}
		
		if ( $title->equals( $this->mThread->article()->getTitle() ) ) {
			return wfMsgExt( 'lqt_move_samedestination', 'parseinline' );
		}
			
		return true;
	}
	
}
