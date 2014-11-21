<?php
if ( !defined( 'MEDIAWIKI' ) ) die;

// Pass-through wrapper
class ThreadProtectionFormView extends LqtView {
	function customizeNavigation( $skintemplate, &$links ) {
		ThreadPermalinkView::customizeThreadNavigation( $skintemplate, $links, $this );

		if ( isset( $links['actions']['protect'] ) ) {
			$links['actions']['protect']['class'] = 'selected';
		}

		if ( isset( $links['actions']['unprotect'] ) ) {
			$links['actions']['unprotect']['class'] = 'selected';
		}
	}

	function __construct( &$output, &$article, &$title, &$user, &$request ) {
		parent::__construct( $output, $article, $title, $user, $request );

		$t = Threads::withRoot( $this->article );

		$this->thread = $t;
		if ( !$t ) {
			return;
		}

		$this->article = $t->article();
	}
}
