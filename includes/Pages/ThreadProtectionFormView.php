<?php

/**
 * Pass-through wrapper
 */
class ThreadProtectionFormView extends LqtView {

	/** @var Thread */
	public $thread;

	public function customizeNavigation( $skintemplate, &$links ) {
		ThreadPermalinkView::customizeThreadNavigation( $skintemplate, $links, $this );

		if ( isset( $links['actions']['protect'] ) ) {
			$links['actions']['protect']['class'] = 'selected';
		}

		if ( isset( $links['actions']['unprotect'] ) ) {
			$links['actions']['unprotect']['class'] = 'selected';
		}
	}

	public function __construct( &$output, &$article, &$title, &$user, &$request ) {
		parent::__construct( $output, $article, $title, $user, $request );

		$t = Threads::withRoot( $this->article->getPage() );

		$this->thread = $t;
		if ( !$t ) {
			return;
		}

		$this->article = $t->article();
	}
}
