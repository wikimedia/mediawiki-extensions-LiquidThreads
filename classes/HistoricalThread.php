<?php

class HistoricalThread extends Thread {
	/* Information about what changed in this revision. */
	protected $changeType;
	protected $changeObject;
	protected $changeComment;
	protected $changeUser;
	protected $changeUserText;

	public function __construct( $t ) {
		/* SCHEMA changes must be reflected here. */
		$this->rootId = $t->rootId;
		$this->rootRevision = $t->rootRevision;
		$this->articleId = $t->articleId;
		$this->summaryId = $t->summaryId;
		$this->articleNamespace = $t->articleNamespace;
		$this->articleTitle = $t->articleTitle;
		$this->modified = $t->modified;
		$this->created = $t->created;
		$this->ancestorId = $t->ancestorId;
		$this->parentId = $t->parentId;
		$this->id = $t->id;
		$this->revisionNumber = $t->revisionNumber;
		$this->editedness = $t->editedness;

		$this->replies = [];
		foreach ( $t->replies() as $r ) {
			$this->replies[] = new HistoricalThread( $r );
		}
	}

	public static function textRepresentation( $t ) {
		$ht = new HistoricalThread( $t );
		return serialize( $ht );
	}

	public static function fromTextRepresentation( $r ) {
		return unserialize( $r );
	}

	public static function withIdAtRevision( $id, $rev ) {
		$dbr = wfGetDB( DB_REPLICA );
		$line = $dbr->selectRow(
			'historical_thread',
			'hthread_contents',
			[
				'hthread_id' => $id,
				'hthread_revision' => $rev
			],
			__METHOD__ );
		if ( $line ) {
			return self::fromTextRepresentation( $line->hthread_contents );
		} else {
			return null;
		}
	}

	public function isHistorical() {
		return true;
	}

	public function changeType() {
		return $this->changeType;
	}

	public function changeObject() {
		return $this->replyWithId( $this->changeObject );
	}

	public function setChangeType( $t ) {
		if ( in_array( $t, Threads::$VALID_CHANGE_TYPES ) ) {
			$this->changeType = $t;
		} else {
			throw new Exception( __METHOD__ . ": invalid changeType $t." );
		}
	}

	public function setChangeObject( $o ) {
		# we assume $o to be a Thread.
		if ( $o === null ) {
			$this->changeObject = null;
		} else {
			$this->changeObject = $o->id();
		}
	}

	public function changeUser() {
		if ( $this->changeUser == 0 ) {
			return User::newFromName( $this->changeUserText, false /* No validation */ );
		} else {
			return User::newFromId( $this->changeUser );
		}
	}

	public function changeComment() {
		return $this->changeComment;
	}

	public function setChangeUser( $user ) {
		$this->changeUser = $user->getId();
		$this->changeUserText = $user->getName();
	}
}
