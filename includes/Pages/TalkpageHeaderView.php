<?php

use MediaWiki\Xml\Xml;

/**
 * Pass-through wrapper with an extra note at the top
 */
class TalkpageHeaderView extends LqtView {
	public function customizeNavigation( $skin, &$links ) {
		$remove = [
			'actions/edit',
			'actions/addsection',
			'views/history',
			'actions/watch', 'actions/move'
		];

		foreach ( $remove as $rem ) {
			[ $section, $item ] = explode( '/', $rem, 2 );
			unset( $links[$section][$item] );
		}

		$links['views']['header'] = [
			'class' => 'selected',
			'text' => wfMessage( 'lqt-talkpage-history-tab' )->text(),
			'href' => '',
		];
	}

	public function show() {
		if ( $this->request->getVal( 'action' ) === 'edit' ) {
			$html = '';

			$warn_bold = Xml::tags(
				'strong',
				null,
				wfMessage( 'lqt_header_warning_bold' )->parse()
			);

			$warn_link = $this->talkpageLink(
				$this->title,
				wfMessage( 'lqt_header_warning_new_discussion' )->text(),
				'talkpage_new_thread'
			);

			$html .= wfMessage( 'lqt_header_warning_before_big' )
				->rawParams( $warn_bold, $warn_link )->parse();
			$html .= Xml::tags(
				'big',
				null,
				wfMessage( 'lqt_header_warning_big' )->rawParams( $warn_bold, $warn_link )->parse()
			);
			$html .= wfMessage( 'word-separator' )->escaped();
			$html .= wfMessage( 'lqt_header_warning_after_big' )
				->rawParams( $warn_bold, $warn_link )->parse();

			$html = Xml::tags( 'p', [ 'class' => 'lqt_header_warning' ], $html );

			$this->output->addHTML( $html );
		}

		return true;
	}
}
