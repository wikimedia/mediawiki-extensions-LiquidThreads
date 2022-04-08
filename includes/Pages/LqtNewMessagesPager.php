<?php

class LqtNewMessagesPager extends LqtDiscussionPager {
	/** @var User */
	private $user;

	public function __construct( $user ) {
		$this->user = $user;

		parent::__construct( false, false );
	}

	/**
	 * Returns an array of structures. Each structure has the keys 'top' and 'posts'.
	 * 'top' contains the top-level thread to display.
	 * 'posts' contains an array of integer post IDs which should be highlighted.
	 * @return false|array
	 */
	public function getThreads() {
		$rows = $this->getRows();

		if ( !count( $rows ) ) {
			return false;
		}

		$threads = Thread::bulkLoad( $rows );
		$thread_ids = array_keys( $threads );
		$output = [];

		foreach ( $threads as $id => $thread ) {
			$output[$id] = [ 'top' => $thread, 'posts' => [] ];
		}

		$dbr = wfGetDB( DB_REPLICA );

		$res = $dbr->select( [ 'user_message_state' ],
					[ 'ums_thread', 'ums_conversation' ],
					[
						'ums_user' => $this->user->getId(),
						'ums_conversation' => $thread_ids
					],
					__METHOD__
					);

		foreach ( $res as $row ) {
			$top = $row->ums_conversation;
			$thread = $row->ums_thread;
			$output[$top]['posts'][] = $thread;
		}

		return $output;
	}

	public function getQueryInfo() {
		$dbr = wfGetDB( DB_REPLICA );

		$queryInfo = [
			'tables' => [ 'thread', 'user_message_state' ],
			'fields' => [ $dbr->tableName( 'thread' ) . '.*', 'ums_conversation' ],
			'conds' => [
				'ums_user' => $this->user->getId(),
				'thread_type != ' . $this->mDb->addQuotes( Threads::TYPE_DELETED ),
			],
			'join_conds' => [
				'thread' => [ 'join', 'ums_conversation=thread_id' ]
			],
			'options' => [
				'group by' => 'ums_conversation'
			]
		];

		return $queryInfo;
	}

	public function getPageLimit() {
		return 25;
	}

	public function getDefaultDirections() {
		return true; // Descending
	}

	public function getIndexField() {
		return [ 'ums_conversation' ];
	}
}
