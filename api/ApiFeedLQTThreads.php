<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

use MediaWiki\MediaWikiServices;

/**
 * This action returns LiquidThreads threads/posts in RSS/Atom formats.
 *
 * @ingroup API
 */
class ApiFeedLQTThreads extends ApiBase {
	public function __construct( $main, $action ) {
		parent::__construct( $main, $action );
	}

	/**
	 * This module uses a custom feed wrapper printer.
	 * @return ApiFormatFeedWrapper
	 */
	public function getCustomPrinter() {
		return new ApiFormatFeedWrapper( $this->getMain() );
	}

	/**
	 * Make a nested call to the API to request items in the last $hours.
	 * Wrap the result as an RSS/Atom feed.
	 */
	public function execute() {
		global $wgFeedClasses;

		$params = $this->extractRequestParams();

		$db = wfGetDB( DB_REPLICA );

		$feedTitle = $this->createFeedTitle( $params );
		$feedClass = $wgFeedClasses[$params['feedformat']];
		$feedItems = [];

		$feedUrl = Title::newMainPage()->getFullURL();

		$tables = [ 'thread' ];
		$fields = [ $db->tableName( 'thread' ) . ".*" ];
		$conds = $this->getConditions( $params, $db );
		$options = [ 'LIMIT' => 200, 'ORDER BY' => 'thread_created DESC' ];

		$res = $db->select( $tables, $fields, $conds, __METHOD__, $options );

		foreach ( $res as $row ) {
			$feedItems[] = $this->createFeedItem( $row );
		}

		$feed = new $feedClass( $feedTitle, '', $feedUrl );

		ApiFormatFeedWrapper::setResult( $this->getResult(), $feed, $feedItems );
	}

	private function createFeedItem( $row ) {
		$thread = Thread::newFromRow( $row );

		$titleStr = $thread->subject();
		$completeText = ContentHandler::getContentText( $thread->root()->getPage()->getContent() );
		$completeText = $this->getOutput()->parseAsContent( $completeText );
		$threadTitle = clone $thread->topmostThread()->title();
		$threadTitle->setFragment( '#' . $thread->getAnchorName() );
		$titleUrl = $threadTitle->getFullURL();
		$timestamp = $thread->created();
		$user = $thread->author()->getName();
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();

		// Prefix content with a quick description
		$userLink = Linker::userLink( $thread->author()->getId(), $user );
		$talkpageLink = $linkRenderer->makeLink( $thread->getTitle() );
		if ( $thread->hasSuperthread() ) {
			$stTitle = clone $thread->topmostThread()->title();
			$stTitle->setFragment( '#' . $thread->superthread()->getAnchorName() );
			$superthreadLink = $linkRenderer->makeLink( $stTitle );
			$description = $this->msg( 'lqt-feed-reply-intro' )
				->rawParams( $talkpageLink, $userLink, $superthreadLink )
				->params( $user )
				->parseAsBlock();
		} else {
			// Third param is unused
			$description = $this->msg( 'lqt-feed-new-thread-intro' )
				->rawParams( $talkpageLink, $userLink, '' )
				->params( $user )
				->parseAsBlock();
		}

		$completeText = $description . $completeText;

		return new FeedItem( $titleStr, $completeText, $titleUrl, $timestamp, $user );
	}

	public function createFeedTitle( $params ) {
		$fromPlaces = [];

		foreach ( (array)$params['thread'] as $thread ) {
			$t = Title::newFromText( $thread );
			if ( !$t ) {
				continue;
			}
			$fromPlaces[] = $t->getPrefixedText();
		}

		foreach ( (array)$params['talkpage'] as $talkpage ) {
			$t = Title::newFromText( $talkpage );
			if ( !$t ) {
				continue;
			}
			$fromPlaces[] = $t->getPrefixedText();
		}

		$fromCount = count( $fromPlaces );
		$fromPlaces = $this->getLanguage()->commaList( $fromPlaces );

		// What's included?
		$types = (array)$params['type'];

		$msg = 'lqt-feed-title-all';
		if ( !count( array_diff( [ 'replies', 'newthreads' ], $types ) ) ) {
			$msg = 'lqt-feed-title-all';
		} elseif ( in_array( 'replies', $types ) ) {
			$msg = 'lqt-feed-title-replies';
		} elseif ( in_array( 'newthreads', $types ) ) {
			$msg = 'lqt-feed-title-new-threads';
		}

		if ( $fromCount ) {
			$msg .= '-from';
		}

		return $this->msg( $msg, $fromPlaces )->numParams( $fromCount )->text();
	}

	/**
	 * @param array $params
	 * @param \Wikimedia\Rdbms\IDatabase $db
	 * @return array
	 */
	private function getConditions( $params, $db ) {
		$conds = [];

		// Types
		$conds['thread_type'] = Threads::TYPE_NORMAL;

		// Limit
		$cutoff = time() - intval( $params['days'] * 24 * 3600 );
		$cutoff = $db->timestamp( $cutoff );
		$conds[] = 'thread_created > ' . $db->addQuotes( $cutoff );

		// Talkpage conditions
		$pageConds = [];

		$talkpages = (array)$params['talkpage'];
		foreach ( $talkpages as $page ) {
			$title = Title::newFromText( $page );
			if ( !$title ) {
				$this->dieWithError( [ 'apierror-invalidtitle', wfEscapeWikiText( $page ) ] );
			}
			$pageCond = [
				'thread_article_namespace' => $title->getNamespace(),
				'thread_article_title' => $title->getDBkey()
			];
			$pageConds[] = $db->makeList( $pageCond, LIST_AND );
		}

		// Thread conditions
		$threads = (array)$params['thread'];
		foreach ( $threads as $thread ) {
			$thread = Threads::withRoot(
				WikiPage::factory(
					Title::newFromText( $thread )
				)
			);

			if ( !$thread ) {
				continue;
			}

			$threadCond = [
				'thread_ancestor' => $thread->id(),
				'thread_id' => $thread->id()
			];
			$pageConds[] = $db->makeList( $threadCond, LIST_OR );
		}
		if ( count( $pageConds ) ) {
			$conds[] = $db->makeList( $pageConds, LIST_OR );
		}

		// New thread v. Reply
		$types = (array)$params['type'];
		if ( !in_array( 'replies', $types ) ) {
			$conds[] = Threads::topLevelClause();
		} elseif ( !in_array( 'newthreads', $types ) ) {
			$conds[] = '!' . Threads::topLevelClause();
		}

		return $conds;
	}

	public function getAllowedParams() {
		global $wgFeedClasses;
		$feedFormatNames = array_keys( $wgFeedClasses );
		return [
			'feedformat' => [
				ApiBase::PARAM_DFLT => 'rss',
				ApiBase::PARAM_TYPE => $feedFormatNames
			],
			'days' => [
				ApiBase::PARAM_DFLT => 7,
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_MIN => 1,
				ApiBase::PARAM_MAX => 30,
			],
			'type' => [
				ApiBase::PARAM_DFLT => 'newthreads',
				ApiBase::PARAM_TYPE => [ 'replies', 'newthreads' ],
				ApiBase::PARAM_ISMULTI => true,
			],
			'talkpage' => [
				ApiBase::PARAM_ISMULTI => true,
			],
			'thread' => [
				ApiBase::PARAM_ISMULTI => true,
			],
		];
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 * @return array
	 */
	protected function getExamplesMessages() {
		return [
			'action=feedthreads'
				=> 'apihelp-feedthreads-example-1',
			'action=feedthreads&type=replies&thread=Thread:Foo'
				=> 'apihelp-feedthreads-example-2',
			'action=feedthreads&type=newthreads&talkpage=Talk:Main_Page'
				=> 'apihelp-feedthreads-example-3',
		];
	}
}
