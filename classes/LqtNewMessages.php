<?php
if ( !defined( 'MEDIAWIKI' ) ) die;

class NewMessages {

	static function markThreadAsUnreadByUser( $thread, $user ) {
		self::writeUserMessageState( $thread, $user, null );
	}

	static function markThreadAsReadByUser( $thread, $user ) {
		self::writeUserMessageState( $thread, $user, wfTimestampNow() );
	}

	private static function writeUserMessageState( $thread, $user, $timestamp ) {
		global $wgDBprefix;
		if ( is_object( $thread ) ) $thread_id = $thread->id();
		else if ( is_integer( $thread ) ) $thread_id = $thread;
		else throw new MWException( "writeUserMessageState expected Thread or integer but got $thread" );

		if ( is_object( $user ) ) $user_id = $user->getID();
		else if ( is_integer( $user ) ) $user_id = $user;
		else throw new MWException( "writeUserMessageState expected User or integer but got $user" );

		if ( $timestamp === null ) $timestamp = "NULL";

		// use query() directly to pass in 'true' for don't-die-on-errors.
		$dbr =& wfGetDB( DB_MASTER );
		$success = $dbr->query( "insert into {$wgDBprefix}user_message_state values ($user_id, $thread_id, $timestamp)",
			__METHOD__, true );

		if ( !$success ) {
			// duplicate key; update.
			$dbr->query( "update {$wgDBprefix}user_message_state set ums_read_timestamp = $timestamp" .
				" where ums_thread = $thread_id and ums_user = $user_id",
				__METHOD__ );
		}
	}

	/**
	 * Write a user_message_state for each user who is watching the thread.
	 * If the thread is on a user's talkpage, set that user's newtalk.
	*/
	static function writeMessageStateForUpdatedThread( $t, $type, $changeUser ) {
		global $wgDBprefix, $wgUser;
		
		wfDebugLog( 'LiquidThreads', 'Doing notifications' );

		$dbw =& wfGetDB( DB_MASTER );

		$talkpage_t = $t->article()->getTitle()->getSubjectPage();
		$root_t = $t->root()->getTitle();

		$q_talkpage_t = $dbw->addQuotes( $talkpage_t->getDBkey() );
		$q_root_t = $dbw->addQuotes( $root_t->getDBkey() );

		// Select any applicable watchlist entries for the thread.
		$talkpageWhere = array( 'wl_namespace' => $talkpage_t->getNamespace(),
								'wl_title' => $talkpage_t->getDBkey() );
		$rootWhere = array( 'wl_namespace' => $root_t->getNamespace(),
								'wl_title' => $root_t->getDBkey() );
								
		$talkpageWhere = $dbw->makeList( $talkpageWhere, LIST_AND );
		$rootWhere = $dbw->makeList( $rootWhere, LIST_AND );
		
		$where_clause = $dbw->makeList( array( $talkpageWhere, $rootWhere ), LIST_OR );

		// it sucks to not have 'on duplicate key update'. first update users who already have a ums for this thread
		// and who have already read it, by setting their state to unread.
		
		// Pull users to update the message state for, including whether or not a
		//  user_message_state row exists for them, and whether or not to send an email
		//  notification.
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select(  array( 'watchlist', 'user_message_state', 'user_properties' ),
								array( 'wl_user', 'ums_user', 'ums_read_timestamp', 'up_value' ),
								$where_clause, __METHOD__, array(),
								array( 'user_message_state' =>
									array( 'left join',  array( 'ums_user=wl_user',
										'ums_thread' => $t->id() ) ),
									'user_properties' => array(
										'left join',
										array( 'up_user=wl_user',
											'up_property' => 'lqtnotifytalk',
										)
									),
								)
							);
		
		$insert_rows = array();
		$update_tuples = array();
		$notify_users = array();
		while( $row = $dbr->fetchObject( $res ) ) {
			// Don't notify yourself
			if ( $changeUser->getId() == $row->wl_user )
				continue;
				
			if ( $row->ums_user && !$row->ums_read_timestamp ) {
				// It's already positive.
			} else {
				$insert_rows[] =
					array(
						'ums_user' => $row->wl_user,
						'ums_thread' => $t->id(),
					);
			}
			
			if ( ( is_null($row->up_value) && User::getDefaultOption( 'lqtnotifytalk' ) )
					|| $row->up_value ) {
				$notify_users[] = $row->wl_user;
			}
		}
		
		// Add user talk notification
		if ( $t->article()->getTitle()->getNamespace() == NS_USER_TALK ) {
			$name = $t->article()->getTitle()->getText();
			
			$user = User::newFromName( $name );
			if ( $user ) {
				$user->setNewtalk( true );
				
				$insert_rows[] = array( 'ums_user' => $user->getId(),
										'ums_thread' => $t->id(),
										'ums_read_timestamp' => null );
				
				if ( $user->getOption( 'enotifusertalkpages' ) ) {
					$notify_users[] = $user->getId();
				}
			}
			
		}
		
		// Do the actual updates
		if ( count($insert_rows) ) {
			$dbw->replace( 'user_message_state', array( array( 'ums_user', 'ums_thread' ) ),
							$insert_rows, __METHOD__ );
		}
		
		if ( count($notify_users) ) {
			self::notifyUsersByMail( $t, $notify_users, wfTimestampNow(), $type );
		}
	}
	
	static function notifyUsersByMail( $t, $watching_users, $timestamp, $type ) {
		wfLoadExtensionMessages( 'LiquidThreads' );
		$messages = array(
			Threads::CHANGE_REPLY_CREATED => 'lqt-enotif-reply',
			Threads::CHANGE_NEW_THREAD => 'lqt-enotif-newthread',
		);
		$subjects = array(
			Threads::CHANGE_REPLY_CREATED => 'lqt-enotif-subject-reply',
			Threads::CHANGE_NEW_THREAD => 'lqt-enotif-subject-newthread',
		);
			
		if ( !isset($messages[$type]) || !isset($subjects[$type]) ) {
			wfDebugLog( 'LiquidThreads', "Email notification failed: type $type unrecognised" );
			return;
		} else {
			$msgName = $messages[$type];
			$subjectMsg = $subjects[$type];
		}
		
		// Send email notification, fetching all the data in one go
		$dbr = wfGetDB( DB_SLAVE );
		$res = $dbr->select( array( 'user', 'user_properties' ), '*',
							array( 'user_id' => $watching_users ), __METHOD__, array(),
							array( 'user_properties' =>
								array( 'left join', 
									array(
										'up_user=user_id',
										'up_property' => 'timecorrection'
									)
								)
							)
						);
		
		while( $row = $dbr->fetchObject( $res ) ) {
			$u = User::newFromRow( $row );
			
			global $wgLang;
			
			$permalink = LqtView::permalinkUrl( $t );
			
			// Adjust with time correction
			$adjustedTimestamp = $wgLang->userAdjust( $timestamp, $row->up_value );
			
			$date = $wgLang->date( $adjustedTimestamp );
			$time = $wgLang->time( $adjustedTimestamp );
			
			$talkPage = $t->article()->getTitle()->getPrefixedText();
			$msg = wfMsg( $msgName, $u->getName(), $t->subjectWithoutIncrement(),
							$date, $time, $talkPage, $permalink );
							
			global $wgPasswordSender;
							
			$from = new MailAddress( $wgPasswordSender, 'WikiAdmin' );
			$to   = new MailAddress( $u );
			$subject = wfMsg( $subjectMsg, $t->subjectWithoutIncrement() );
			
			UserMailer::send( $to, $from, $subject, $msg );
		}
	}

	static function newUserMessages( $user ) {
		global $wgDBprefix;
		
		$talkPage = new Article( $user->getUserPage()->getTalkPage() );
		return Threads::where( array( 'ums_read_timestamp is null',
									Threads::articleClause( $talkPage ) ),
							 array(), array(),
							 "left outer join {$wgDBprefix}user_message_state on " .
							 "ums_user is null or ".
							 "(ums_user = {$user->getID()} and ums_thread = thread.thread_id)" );
	}

	static function watchedThreadsForUser( $user ) {
		return Threads::where( array( 'ums_read_timestamp is null',
			'ums_user' => $user->getID(),
			'ums_thread = thread.thread_id',
			'NOT (' . Threads::articleClause( new Article( $user->getUserPage() ) ) . ')' ),
			array(), array( 'user_message_state' ) );
	}
}
