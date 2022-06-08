<?php

use MediaWiki\MediaWikiServices;

class ThreadRevision {
	/** @var string[] */
	public static $load =
		[
			'th_id'             => 'mId',
			'th_thread'         => 'mThreadId',

			'th_timestamp'      => 'mTimestamp',

			'th_user'           => 'mUserId',
			'th_user_text'      => 'mUserText',

			'th_change_type'    => 'mChangeType',
			'th_change_object'  => 'mChangeObjectId',
			'th_change_comment' => 'mChangeComment',
			'th_content'        => 'mObjSer',
		];

	/** @var int */
	protected $mId;
	/** @var int */
	protected $mThreadId;
	/** @var string */
	protected $mTimestamp;
	/** @var User */
	protected $mUser;
	/** @var int */
	protected $mUserId;
	/** @var string */
	protected $mUserText;
	/** @var int */
	protected $mChangeType;
	/** @var int */
	protected $mChangeObjectId;
	/** @var Thread|null|false */
	protected $mChangeObject;
	/** @var string */
	protected $mChangeComment;
	/** @var string|null */
	protected $mObjSer;
	/** @var Thread|false|null */
	protected $mThreadObj;

	public static function loadFromId( $id ) {
		$dbr = wfGetDB( DB_REPLICA );
		$row = $dbr->selectRow( 'thread_history', '*', [ 'th_id' => $id ], __METHOD__ );

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

	public static function create(
		Thread $thread,
		$change_type,
		User $user,
		$change_object = null,
		$comment = ''
	) {
		$timestamp = wfTimestampNow();

		if ( $comment === null ) {
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
		} elseif ( $change_object === null ) {
			$rev->mChangeObjectId = $thread->id();
			$rev->mChangeObject = $thread;
		} else {
			$rev->mChangeObjectId = $change_object;
		}

		// This field is TINYTEXT so it can only fit 255 bytes.
		$rev->mChangeComment = MediaWikiServices::getInstance()->getContentLanguage()
			->truncateForDatabase( $comment, 255 );

		$rev->mThreadObj = $thread->topmostThread();
		$rev->mObjSer = serialize( $rev->mThreadObj );

		$rev->insert();

		return $rev;
	}

	public function insert() {
		$dbw = wfGetDB( DB_PRIMARY );

		$row = $this->getRow();

		$dbw->insert( 'thread_history', $row, __METHOD__ );

		$this->mId = $dbw->insertId();
	}

	public function save() {
		$row = $this->getRow();

		$dbw = wfGetDB( DB_PRIMARY );

		$dbw->replace( 'thread_history', 'th_thread', $row, __METHOD__ );
	}

	public function getRow() {
		$row = [];

		// First, prep the data for insertion
		$dbw = wfGetDB( DB_PRIMARY );
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

		return User::newFromName( $this->mUserText, /* No validation */ false );
	}

	public function getChangeType() {
		return $this->mChangeType;
	}

	public function getChangeObject() {
		if ( $this->mChangeObject === null && $this->mChangeObjectId ) {
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
		if ( $this->mThreadObj === null ) {
			if ( $this->mObjSer !== null ) {
				$this->mThreadObj = unserialize( $this->mObjSer );
			} else {
				var_dump( $this );
				throw new Exception( "Missing mObjSer" );
			}
		}

		if ( !( $this->mThreadObj instanceof Thread ) ) {
			$this->mThreadObj = false;
			return false;
		}

		$this->mThreadObj->threadRevision = $this;

		return $this->mThreadObj;
	}

	public function prev() {
		$dbr = wfGetDB( DB_REPLICA );

		$cond = 'th_id<' . $dbr->addQuotes( intval( $this->getId() ) );
		$row = $dbr->selectRow( 'thread_history', '*',
				[ $cond, 'th_thread' => $this->mThreadId ],
				__METHOD__ );

		return self::loadFromRow( $row );
	}

	public function next() {
		$dbr = wfGetDB( DB_REPLICA );

		$cond = 'th_id>' . $dbr->addQuotes( intval( $this->getId() ) );
		$row = $dbr->selectRow( 'thread_history', '*',
				[ $cond, 'th_thread' => $this->mThreadId ],
				__METHOD__ );

		return self::loadFromRow( $row );
	}
}
