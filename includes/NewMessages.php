<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserIdentity;
use Wikimedia\Rdbms\IResultWrapper;

class NewMessages {
	public static function markThreadAsUnreadByUser( Thread $thread, UserIdentity $user ) {
		self::writeUserMessageState( $thread, $user );
	}

	/**
	 * @param Thread $thread
	 * @param UserIdentity $user
	 */
	public static function markThreadAsReadByUser( Thread $thread, UserIdentity $user ) {
		$thread_id = $thread->id();
		$user_id = $user->getId();

		$dbw = wfGetDB( DB_PRIMARY );

		$dbw->delete(
			'user_message_state',
			[ 'ums_user' => $user_id, 'ums_thread' => $thread_id ],
			__METHOD__
		);

		self::recacheMessageCount( $user_id );
	}

	/**
	 * @param UserIdentity|int $user
	 */
	public static function markAllReadByUser( $user ) {
		if ( is_object( $user ) ) {
			$user_id = $user->getId();
		} elseif ( is_int( $user ) ) {
			$user_id = $user;
		} else {
			throw new Exception( __METHOD__ . " expected User or integer but got $user" );
		}

		$dbw = wfGetDB( DB_PRIMARY );

		$dbw->delete(
			'user_message_state',
			[ 'ums_user' => $user_id ],
			__METHOD__
		);

		self::recacheMessageCount( $user_id );
	}

	private static function writeUserMessageState( Thread $thread, UserIdentity $user ) {
		$thread_id = $thread->id();
		$user_id = $user->getId();

		$conversation = Threads::withId( $thread_id )->topmostThread()->id();

		$dbw = wfGetDB( DB_PRIMARY );
		$dbw->replace(
			'user_message_state',
			[ [ 'ums_user', 'ums_thread' ] ],
			[
				'ums_user' => $user_id,
				'ums_thread' => $thread_id,
				'ums_read_timestamp' => null,
				'ums_conversation' => $conversation
			],
			__METHOD__
		);

		self::recacheMessageCount( $user_id );
	}

	/**
	 * Get the where clause for an update
	 * If the thread is on a user's talkpage, set that user's newtalk.
	 *
	 * @param Thread $t
	 * @return string
	 */
	private static function getWhereClause( $t ) {
		$dbw = wfGetDB( DB_PRIMARY );

		$tpTitle = $t->getTitle();
		$rootThread = $t->topmostThread()->root()->getTitle();

		// Select any applicable watchlist entries for the thread.
		$talkpageWhere = [
			'wl_namespace' => $tpTitle->getNamespace(),
			'wl_title' => $tpTitle->getDBkey()
		];
		$rootWhere = [
			'wl_namespace' => $rootThread->getNamespace(),
			'wl_title' => $rootThread->getDBkey()
		];

		$talkpageWhere = $dbw->makeList( $talkpageWhere, LIST_AND );
		$rootWhere = $dbw->makeList( $rootWhere, LIST_AND );

		return $dbw->makeList( [ $talkpageWhere, $rootWhere ], LIST_OR );
	}

	/**
	 * @param Thread $t
	 * @return IResultWrapper
	 */
	private static function getRowsObject( $t ) {
		$tables = [ 'watchlist', 'user_message_state', 'user_properties' ];
		$joins = [
			'user_message_state' =>
			[
				'LEFT JOIN',
				[
					'ums_user=wl_user',
					'ums_thread' => $t->id()
				]
			],
			'user_properties' =>
			[
				'LEFT JOIN',
				[
					'up_user=wl_user',
					'up_property' => 'lqtnotifytalk',
				]
			]
		];
		$fields = [ 'wl_user', 'ums_user', 'ums_read_timestamp', 'up_value' ];

		$dbr = wfGetDB( DB_REPLICA );
		return $dbr->select( $tables, $fields, self::getWhereClause( $t ), __METHOD__, [], $joins );
	}

	/**
	 * Write a user_message_state for each user who is watching the thread.
	 * If the thread is on a user's talkpage, set that user's newtalk.
	 * @param Thread $t
	 * @param int $type
	 * @param UserIdentity $changeUser
	 */
	public static function writeMessageStateForUpdatedThread( $t, $type, $changeUser ) {
		wfDebugLog( 'LiquidThreads', 'Doing notifications' );

		$usersByCategory = self::getNotifyUsers( $t, $changeUser );
		$userIds = $usersByCategory['notify'];
		$notifyUsers = $usersByCategory['email'];

		// Do the actual updates
		if ( count( $userIds ) ) {
			$insertRows = [];
			foreach ( $userIds as $u ) {
				$insertRows[] = [
					'ums_user' => $u,
					'ums_thread' => $t->id(),
					'ums_read_timestamp' => null,
					'ums_conversation' => $t->topmostThread()->id(),
				];
			}

			$dbw = wfGetDB( DB_PRIMARY );
			$dbw->replace(
				'user_message_state',
				[ [ 'ums_user', 'ums_thread' ] ],
				$insertRows, __METHOD__
			);
		}

		global $wgLqtEnotif;
		if ( count( $notifyUsers ) && $wgLqtEnotif ) {
			self::notifyUsersByMail( $t, $notifyUsers, wfTimestampNow(), $type );
		}
	}

	/**
	 * @param Thread $t
	 * @param UserIdentity $changeUser
	 * @return array
	 */
	public static function getNotifyUsers( $t, $changeUser ) {
		// Pull users to update the message state for, including whether or not a
		// user_message_state row exists for them, and whether or not to send an email
		// notification.
		$userIds = [];
		$notifyUsers = [];
		$services = MediaWikiServices::getInstance();
		$userOptionsLookup = $services->getUserOptionsLookup();
		$res = self::getRowsObject( $t );
		foreach ( $res as $row ) {
			// Don't notify yourself
			if ( $changeUser->getId() == $row->wl_user ) {
				continue;
			}

			if ( !$row->ums_user || $row->ums_read_timestamp ) {
				$userIds[] = $row->wl_user;
				self::recacheMessageCount( $row->wl_user );
			}

			global $wgHiddenPrefs;
			if ( !in_array( 'lqtnotifytalk', $wgHiddenPrefs ) && isset( $row->up_value ) ) {
				$wantsTalkNotification = (bool)$row->wl_user;
			} else {
				$wantsTalkNotification = $userOptionsLookup->getDefaultOption( 'lqtnotifytalk' );
			}

			if ( $wantsTalkNotification ) {
				$notifyUsers[] = $row->wl_user;
			}
		}

		// Add user talk notification
		if ( $t->getTitle()->getNamespace() == NS_USER_TALK ) {
			$name = $t->getTitle()->getText();

			$user = User::newFromName( $name );
			if ( $user && $user->getName() !== $changeUser->getName() ) {
				$services->getTalkPageNotificationManager()
					->setUserHasNewMessages( $user );

				$userOptionsLookup = MediaWikiServices::getInstance()->getUserOptionsLookup();
				$userIds[] = $user->getId();
				if ( $userOptionsLookup->getOption( $user, 'enotifusertalkpages' ) ) {
					$notifyUsers[] = $user->getId();
				}
			}
		}

		return [
			'notify' => $userIds,
			'email' => $notifyUsers,
		];
	}

	/**
	 * @param Thread $t
	 * @param int[] $watching_users
	 * @param string $timestamp
	 * @param int $type
	 */
	public static function notifyUsersByMail( $t, $watching_users, $timestamp, $type ) {
		$messages = [
			Threads::CHANGE_REPLY_CREATED => 'lqt-enotif-reply',
			Threads::CHANGE_NEW_THREAD => 'lqt-enotif-newthread',
		];
		$subjects = [
			Threads::CHANGE_REPLY_CREATED => 'lqt-enotif-subject-reply',
			Threads::CHANGE_NEW_THREAD => 'lqt-enotif-subject-newthread',
		];

		if ( !isset( $messages[$type] ) || !isset( $subjects[$type] ) ) {
			wfDebugLog( 'LiquidThreads', "Email notification failed: type $type unrecognised" );
			return;
		} else {
			$msgName = $messages[$type];
			$subjectMsg = $subjects[$type];
		}

		// Send email notification, fetching all the data in one go
		$dbr = wfGetDB( DB_REPLICA );

		$tables = [
			'user',
			'tc_prop' => 'user_properties',
			'l_prop' => 'user_properties'
		];

		$fields = [
			$dbr->tableName( 'user' ) . '.*',
			'tc_prop.up_value AS timecorrection',
			'l_prop.up_value as language'
		];

		$join_conds = [
			'tc_prop' => [
				'LEFT JOIN',
				[
					'tc_prop.up_user=user_id',
					'tc_prop.up_property' => 'timecorrection',
				]
			],
			'l_prop' => [
				'LEFT JOIN',
				[
					'l_prop.up_user=user_id',
					'l_prop.up_property' => 'language',
				]
			]
		];

		$res = $dbr->select(
			$tables, $fields,
			[ 'user_id' => $watching_users ], __METHOD__,
			[], $join_conds
		);

		// Set up one-time data.
		global $wgPasswordSender;
		$link_title = clone $t->getTitle();
		$link_title->setFragment( '#' . $t->getAnchorName() );
		$permalink = LqtView::linkInContextCanonicalURL( $t );
		$talkPage = $t->getTitle()->getPrefixedText();
		$from = new MailAddress( $wgPasswordSender, wfMessage( 'emailsender' )->text() );
		$threadSubject = $t->subject();

		// Parse content and strip HTML of post content
		$emailer = MediaWikiServices::getInstance()->getEmailer();
		$languageFactory = MediaWikiServices::getInstance()->getLanguageFactory();
		foreach ( $res as $row ) {
			$u = User::newFromRow( $row );

			if ( $row->language ) {
				$langCode = $row->language;
			} else {
				global $wgLanguageCode;

				$langCode = $wgLanguageCode;
			}

			$lang = $languageFactory->getLanguage( $langCode );

			// Adjust with time correction
			$timeCorrection = $row->timecorrection;
			$adjustedTimestamp = (string)$lang->userAdjust( $timestamp, $timeCorrection );

			$date = $lang->date( $adjustedTimestamp );
			$time = $lang->time( $adjustedTimestamp );

			$content = $t->root()->getPage()->getContent();
			$params = [
				$u->getName(),
				$t->subject(),
				$date,
				$time,
				$talkPage,
				$permalink,
				( $content instanceof TextContent ) ? $content->getText() : '',
				$t->author()->getName()
			];

			// Get message in user's own language, bug 20645
			$msg = wfMessage( $msgName, $params )->inLanguage( $langCode )->text();

			$to = MailAddress::newFromUser( $u );
			$subject = wfMessage( $subjectMsg, $threadSubject )->inLanguage( $langCode )->text();

			$emailer->send( [ $to ], $from, $subject, $msg );
		}
	}

	public static function newUserMessages( $user ) {
		$talkPage = MediaWikiServices::getInstance()->getWikiPageFactory()
			->newFromTitle( $user->getUserPage()->getTalkPage() );

		$dbr = wfGetDB( DB_REPLICA );

		$joinConds = [ 'ums_user' => null ];
		$joinConds[] = $dbr->makeList(
			[
				'ums_user' => $user->getId(),
				'ums_thread=thread_id'
			],
			LIST_AND
		);
		$joinClause = $dbr->makeList( $joinConds, LIST_OR );

		$res = $dbr->select(
			[ 'thread', 'user_message_state' ],
			'*',
			[
				'ums_read_timestamp' => null,
				Threads::articleClause( $talkPage )
			],
			__METHOD__,
			[],
			[
				'thread' =>
				[ 'LEFT JOIN', $joinClause ]
			]
		);

		return Threads::loadFromResult( $res, $dbr );
	}

	/**
	 * @param User $user
	 * @param int $dbIndex
	 * @return int
	 */
	public static function newMessageCount( $user, $dbIndex = DB_REPLICA ) {
		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$fname = __METHOD__;

		return (int)$cache->getWithSetCallback(
			$cache->makeKey( 'lqt-new-messages-count', $user->getId() ),
			$cache::TTL_DAY,
			static function () use ( $user, $dbIndex, $fname ) {
				$dbr = wfGetDB( $dbIndex );

				$cond = [ 'ums_user' => $user->getId(), 'ums_read_timestamp' => null ];
				$options = [ 'LIMIT' => 500 ];

				$res = $dbr->select( 'user_message_state', '1', $cond, $fname, $options );
				$count = $res->numRows();
				if ( $count >= 500 ) {
					$count = $dbr->estimateRowCount( 'user_message_state', '*', $cond, $fname );
				}

				return $count;
			}
		);
	}

	public static function recacheMessageCount( $uid ) {
		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$cache->delete( $cache->makeKey( 'lqt-new-messages-count', $uid ) );
		User::newFromId( $uid )->clearSharedCache( 'refresh' );
	}

	public static function watchedThreadsForUser( User $user ) {
		$talkPage = MediaWikiServices::getInstance()->getWikiPageFactory()
			->newFromTitle( $user->getUserPage()->getTalkPage() );

		$dbr = wfGetDB( DB_REPLICA );

		$res = $dbr->select(
			[ 'thread', 'user_message_state' ],
			'*',
			[
				'ums_read_timestamp' => null,
				'ums_user' => $user->getId(),
				'not (' . Threads::articleClause( $talkPage ) . ')',
			],
			__METHOD__,
			[],
			[
				'user_message_state' =>
				[ 'INNER JOIN', 'ums_thread=thread_id' ],
			]
		);

		return Threads::loadFromResult( $res, $dbr );
	}
}
