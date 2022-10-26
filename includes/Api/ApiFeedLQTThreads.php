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

namespace MediaWiki\Extension\LiquidThreads\Api;

use ApiBase;
use ApiFormatFeedWrapper;
use ApiMain;
use Linker;
use MediaWiki\Feed\FeedItem;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Page\WikiPageFactory;
use TextContent;
use Thread;
use Threads;
use Title;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;

/**
 * This action returns LiquidThreads threads/posts in RSS/Atom formats.
 *
 * @ingroup API
 */
class ApiFeedLQTThreads extends ApiBase {
	/** @var LinkRenderer */
	private $linkRenderer;
	/** @var WikiPageFactory */
	private $wikiPageFactory;

	public function __construct(
		ApiMain $main,
		$action,
		LinkRenderer $linkRenderer,
		WikiPageFactory $wikiPageFactory
	) {
		parent::__construct( $main, $action );
		$this->linkRenderer = $linkRenderer;
		$this->wikiPageFactory = $wikiPageFactory;
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
		$content = $thread->root()->getPage()->getContent();
		$completeText = ( $content instanceof TextContent ) ? $content->getText() : '';
		$completeText = $this->getOutput()->parseAsContent( $completeText );
		$threadTitle = clone $thread->topmostThread()->title();
		$threadTitle->setFragment( '#' . $thread->getAnchorName() );
		$titleUrl = $threadTitle->getFullURL();
		$timestamp = $thread->created();
		$user = $thread->author()->getName();

		// Prefix content with a quick description
		$userLink = Linker::userLink( $thread->author()->getId(), $user );
		$talkpageLink = $this->linkRenderer->makeLink( $thread->getTitle() );
		if ( $thread->hasSuperthread() ) {
			$stTitle = clone $thread->topmostThread()->title();
			$stTitle->setFragment( '#' . $thread->superthread()->getAnchorName() );
			$superthreadLink = $this->linkRenderer->makeLink( $stTitle );
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

		if ( !count( array_diff( [ 'replies', 'newthreads' ], $types ) ) ) {
			$msg = 'lqt-feed-title-all';
		} elseif ( in_array( 'replies', $types ) ) {
			$msg = 'lqt-feed-title-replies';
		} elseif ( in_array( 'newthreads', $types ) ) {
			$msg = 'lqt-feed-title-new-threads';
		} else {
			$msg = 'lqt-feed-title-all';
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
		foreach ( $threads as $threadName ) {
			$thread = null;
			$threadTitle = Title::newFromText( $threadName );
			if ( $threadTitle ) {
				$thread = Threads::withRoot( $this->wikiPageFactory->newFromTitle( $threadTitle ) );
			}

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
				ParamValidator::PARAM_DEFAULT => 'rss',
				ParamValidator::PARAM_TYPE => $feedFormatNames
			],
			'days' => [
				ParamValidator::PARAM_DEFAULT => 7,
				ParamValidator::PARAM_TYPE => 'integer',
				IntegerDef::PARAM_MIN => 1,
				IntegerDef::PARAM_MAX => 30,
			],
			'type' => [
				ParamValidator::PARAM_DEFAULT => 'newthreads',
				ParamValidator::PARAM_TYPE => [ 'replies', 'newthreads' ],
				ParamValidator::PARAM_ISMULTI => true,
			],
			'talkpage' => [
				ParamValidator::PARAM_ISMULTI => true,
			],
			'thread' => [
				ParamValidator::PARAM_ISMULTI => true,
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
