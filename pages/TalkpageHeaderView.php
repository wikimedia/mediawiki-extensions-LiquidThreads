<?php
if ( !defined( 'MEDIAWIKI' ) ) die;

// Pass-through wrapper with an extra note at the top
class TalkpageHeaderView extends LqtView {
	function customizeTabs( $skintemplate, &$content_actions ) {
		unset( $content_actions['edit'] );
		unset( $content_actions['addsection'] );
		unset( $content_actions['history'] );
		unset( $content_actions['watch'] );
		unset( $content_actions['move'] );

		$content_actions['talk']['class'] = false;
		$content_actions['header'] = array(
			'class' => 'selected',
			'text' => wfMessage( 'lqt-talkpage-history-tab' )->text(),
			'href' => '',
		);
	}

	function customizeNavigation( $skin, &$links ) {
		$remove = array(
			'actions/edit',
			'actions/addsection',
			'views/history',
			'actions/watch', 'actions/move'
		);

		foreach ( $remove as $rem ) {
			list( $section, $item ) = explode( '/', $rem, 2 );
			unset( $links[$section][$item] );
		}

		$links['views']['header'] = array(
			'class' => 'selected',
			'text' => wfMessage( 'lqt-talkpage-history-tab' )->text(),
			'href' => '',
		);
	}

	function show() {
		global $wgOut, $wgTitle, $wgRequest;

		if ( $wgRequest->getVal( 'action' ) === 'edit' ) {
			$html = '';

			$warn_bold = Xml::tags(
				'strong',
				null,
				wfMessage( 'lqt_header_warning_bold' )->parse()
			);

			$warn_link = $this->talkpageLink(
				$wgTitle,
				wfMessage( 'lqt_header_warning_new_discussion' )->parse(),
				'talkpage_new_thread'
			);

			$html .= wfMessage( 'lqt_header_warning_before_big' )
				->rawParams( $warn_bold, $warn_link )->parse();
			$html .= Xml::tags(
				'big',
				null,
				wfMessage( 'lqt_header_warning_big' )->rawParams( $warn_bold, $warn_link )->parse()
			);
			$html .= wfMessage( 'word-separator' )->text();
			$html .= wfMessage( 'lqt_header_warning_after_big' )
				->rawParams( $warn_bold, $warn_link )->parse();

			$html = Xml::tags( 'p', array( 'class' => 'lqt_header_warning' ), $html );

			$wgOut->addHTML( $html );
		}

		return true;
	}
}
