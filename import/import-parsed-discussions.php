<?php

require_once getenv( 'MW_INSTALL_PATH' ) !== false
	? getenv( 'MW_INSTALL_PATH' ) . "/maintenance/commandLine.inc"
	: __DIR__ . '/../../maintenance/commandLine.inc';

# # Imports JSON-encoded discussions from parse-wikitext-discussions.pl

// die( var_dump( $argv ) );

$structure = json_decode( file_get_contents( $argv[0] ), true );

$article = new Article( Title::newFromText( $argv[1] ), 0 );

// @phan-suppress-next-line PhanNonClassMethodCall
$wgOut->setTitle( $article->getTitle() );

$subject = '';
$rootPost = null;

recursiveParseArray( $structure );

function recursiveParseArray( $array ) {
	static $recurseLevel = 0;

	$recurseLevel++;

	if ( $recurseLevel > 90 ) {
		var_dump( $array );
		die( wfBacktrace() );
	}

	global $subject, $rootPost;
	if ( is_array( $array ) && isset( $array['title'] ) ) {
		$subject = $array['title'];
		recursiveParseArray( $array['content'] );

		$rootPost = null;
	} elseif ( is_array( $array ) && isset( $array['user'] ) ) {
		// We have a post.
		$t = createPost( $array, $subject, $rootPost );

		// @phan-suppress-next-line PhanRedundantCondition
		if ( !$rootPost ) {
			$rootPost = $t;
		}
	} elseif ( is_array( $array ) ) {
		foreach ( $array as $info ) {
			recursiveParseArray( $info );
		}

		$rootPost = null;
	}

	$recurseLevel--;
}

/**
 * @param array $info
 * @param string $subject
 * @param Thread|null $super
 *
 * @return Thread
 */
function createPost( $info, $subject, $super = null ) {
	$userName = $info['user'];
	if ( strpos( $userName, '#' ) !== false ) {
		$pos = strpos( $userName, '#' );

		$userName = substr( $userName, 0, $pos );
	}

	$user = User::newFromName( $userName, /* no validation */ false );

	if ( !$user ) {
		throw new Exception( "Username " . $info['user'] . " is invalid." );
	}

	global $article;

	if ( $super ) {
		$title = Threads::newReplyTitle( $super, $user );
	} else {
		$title = Threads::newThreadTitle( $subject, $article );
	}

	// @phan-suppress-next-line SecurityCheck-XSS
	print "Creating thread $title as a subthread of " . ( $super ? $super->title() : 'none' ) . "\n";

	$root = new Article( $title, 0 );

	$root->getPage()->doEditContent(
		ContentHandler::makeContent( $info['content'], $title ),
		'Imported from JSON',
		EDIT_NEW,
		false,
		$user
	);

	// @FIXME function does not exists T146316
	// @phan-suppress-next-line PhanUndeclaredStaticMethod
	$t = LqtView::postEditUpdates(
		$super ? 'reply' : 'new',
		$super,
		$root,
		$article,
		$subject,
		'Imported from JSON',
		null
	);

	$t = Threads::withId( $t->id() ); // Some weirdness.

	return $t;
}
