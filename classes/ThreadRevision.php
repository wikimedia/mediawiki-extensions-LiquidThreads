<?php

class ThreadRevision {
	public static $load =
		array(
			'th_id'             => 'mId',
			'th_thread'         => 'mThreadId',

			'th_timestamp'      => 'mTimestamp',

			'th_user'           => 'mUserId',
			'th_user_text'      => 'mUserText',

			'th_change_type'    => 'mChangeType',
			'th_change_object'  => 'mChangeObjectId',
			'th_change_comment' => 'mChangeComment',
			'th_content'        => 'mObjSer',
		);

	protected $mId, $mThreadId, $mTimestamp, $mUserId, $mUserText, $mChangeType,
				$mChangeObjectId, $mChangeObject, $mChangeComment, $mObjSer, $mThreadObj;

	public static function loadFromId( $id ) {
		$dbr = wfGetDB( DB_SLAVE );
		$row = $dbr->selectRow( 'thread_history', '*', array( 'th_id' => $id ), __METHOD__ );

		if ( !$row ) {
			return null;
		}

		return self::loadFromRow( $row );
	}

	public static function loadFromRow( $row ) {
		if ( !$row ) {
			return null;
		}

		$rev = new ThreadRevision;

		foreach ( self::$load as $col => $field ) {
			$rev->$field = $row->$col;
		}

		$rev->mUser = User::newFromName( $rev->mUserText, /* Don't validate */ false );
		$rev->mThreadObj = unserialize( $rev->mObjSer );

		return $rev;
	}

	public static function create( $thread, $change_type, $change_object = null, $comment = '',
		$user = null, $timestamp = null
	) {
		global $wgContLang;

		if ( is_null( $user ) ) {
			global $wgUser;
			$user = $wgUser;
		}

		if ( is_null( $timestamp ) ) {
			$timestamp = wfTimestampNow();
		}

		if ( is_null( $comment ) ) {
			$comment = '';
		}

		$rev = new ThreadRevision;

		$rev->mThreadId = $thread->topmostThread()->id();
		$rev->mTimestamp = $timestamp;

		$rev->mUser = $user;
		$rev->mUserId = $user->getId();
		$rev->mUserText = $user->getName();

		$rev->mChangeType = $change_type;

		if ( $change_object instanceof Thread ) {
			$rev->mChangeObjectId = $change_object->id();
			$rev->mChangeObject = $change_object;
		} elseif ( is_null( $change_object ) ) {
			$rev->mChangeObjectId = $thread->id();
			$rev->mChangeObject = $thread;
		} else {
			$rev->mChangeObjectId = $change_object;
		}

		// This field is TINYTEXT so it can only fit 255 bytes.
		$rev->mChangeComment = $wgContLang->truncate( $comment, 255 );

		$rev->mThreadObj = $thread->topmostThread();
		$rev->mObjSer = serialize( $rev->mThreadObj );

		$rev->insert();

		return $rev;
	}

	public function insert() {
		$dbw = wfGetDB( DB_MASTER );

		$row = $this->getRow();
		$row['th_id'] = $dbw->nextSequenceValue( 'thread_history_th_id' );

		$dbw->insert( 'thread_history', $row, __METHOD__ );

		$this->mId = $dbw->insertId();
	}

	public function save() {
		$row = $this->getRow();

		$dbw = wfGetDB( DB_MASTER );

		$dbw->replace( 'thread_history', array( 'th_thread' ), $row, __METHOD__ );
	}

	public function getRow() {
		$row = array();

		// First, prep the data for insertion
		$dbw = wfGetDB( DB_MASTER );
		$this->mTimestamp = $dbw->timestamp( $this->mTimestamp );

		foreach ( self::$load as $col => $field ) {
			$row[$col] = $this->$field;
		}

		return $row;
	}

	public function getTimestamp() {
		return wfTimestamp( TS_MW, $this->mTimestamp );
	}

	public function getUser() {
		if ( $this->mUserId ) {
			return User::newFromId( $this->mUserId );
		}

		return User::newFromText( $this->mUserText, /* No validation */ false );
	}

	public function getChangeType() {
		return $this->mChangeType;
	}

	public function getChangeObject() {
		if ( is_null( $this->mChangeObject ) && $this->mChangeObjectId ) {
			$threadObj = $this->getThreadObj();

			if ( $threadObj instanceof Thread ) {
				$objectId = $this->mChangeObjectId;
				$this->mChangeObject = $threadObj->replyWithId( $objectId );
			}

			if ( !$this->mChangeObject ) {
				$this->mChangeObject = false;
			}
		}

		return $this->mChangeObject;
	}

	public function getChangeComment() {
		return $this->mChangeComment;
	}

	public function getId() {
		return $this->mId;
	}

	public function getThreadObj() {
		if ( is_null( $this->mThreadObj ) && !is_null( $this->mObjSer ) ) {
			$this->mThreadObj = unserialize( $this->mObjSer );
		} elseif ( is_null( $this->mThreadObj ) && is_null( $this->mObjSer ) ) {
			var_dump( $this );
			throw new Exception( "Missing mObjSer" );
		}

		if ( !( $this->mThreadObj instanceof Thread ) ) {
			$this->mThreadObj = false;
			return false;
		}

		$this->mThreadObj->threadRevision = $this;

		return $this->mThreadObj;
	}

	public function prev() {
		$dbr = wfGetDB( DB_SLAVE );

		$cond = 'th_id<' . $dbr->addQuotes( intval( $this->getId() ) );
		$row = $dbr->selectRow( 'thread_history', '*',
				array( $cond, 'th_thread' => $this->mThreadId ),
				__METHOD__ );

		return self::loadFromRow( $row );
	}

	public function next() {
		$dbr = wfGetDB( DB_SLAVE );

		$cond = 'th_id>' . $dbr->addQuotes( intval( $this->getId() ) );
		$row = $dbr->selectRow( 'thread_history', '*',
				array( $cond, 'th_thread' => $this->mThreadId ),
				__METHOD__ );

		return self::loadFromRow( $row );
	}
}
