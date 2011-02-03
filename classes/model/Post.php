<?php

/**
 * LiquidThreads Post class.
 * This class represents a single comment in a threaded discussion.
 * @addtogroup LiquidThreads model
 */
class LiquidThreadsPost {

	/* MEMBER VARIABLES */
	/** The current LiquidThreadsPostVersion object. **/
	protected $currentVersion;
	/** The ID of the current version **/
	protected $currentVersionID;
	
	/** The version that is being worked on by set methods **/
	protected $pendingVersion;
	
	/** The unique ID for this Post **/
	protected $id;
	
	/** The ID of the topic that this post is in. **/
	protected $topicID;
	/** The LiquidThreadsTopic object that this post is in. **/
	protected $topic;
	
	/** The ID of this post's parent post **/
	protected $parentID;
	/** The LiquidThreadsPost that this is found underneath. **/
	protected $parent;
	
	/* FACTORY METHODS */
	
	/**
	 * Default constructor.
	 * Not to be called directly.
	 */
	protected function __construct() {
		
	}
	
	/**
	 * Factory method to retrieve Posts from Database conditions.
	 * Don't use this method externally, use one of the other factory methods.
	 * @param $conditions Array: Conditions to pass to Database::select
	 * @return Array of LiquidThreadsPost objects with those conditions.
	 */
	public static function loadFromConditions( $conditions, $fetchText = false ) {
		$dbr = wfGetDB( DB_SLAVE );
		
		$tables = array( 'lqt_post', 'lqt_post_version', 'text' );
		$fields = '*';
		$joins = array(
			'lqt_post_version' => array(
				'left join' => array(
					'lqp_current_version=lpv_id',
				),
			),
			'text' => array(
				'left join' => array(
					'old_id=lpv_text_id',
				),
			),
		);
		
		$res = $dbr->select( $tables, $fields, $conditions, __METHOD__, array(), $joins );
		
		$output = array();
		
		foreach( $res as $row ) {
			$post = new LiquidThreadsPost;
			$post->initialiseFromRow( $row );
			
			$output[$post->getID()] = $post;
		}
		
		return $output;
	}
	
	/**
	 * Factory method to retrieve a Post by ID.
	 * Throws an exception if the post does not exist.
	 * @param $id Integer The ID of the post to retrieve.
	 * @return LiquidThreadsPost: The Post with that ID.
	 */
	public static function newFromID( $id ) {
		$condition = array( 'lqp_id' => $id );
		
		$posts = self::loadFromConditions( $condition );
		
		// Check that the post actually exists.
		if ( count($posts) < 1 ) {
			throw new MWException( "Attempt to load post #$id, which does not exist" );
		}
		
		$post = array_shift( $posts );
		return $post;
	}
	
	/**
	 * Factory method to load a post from a row object.
	 * The row object may optionally contain the appropriate Version data, too.
	 * @param $row Object: Row object returned from DatabaseResult::fetchObject()
	 * @return LiquidThreadsPost The new Post object.
	 */
	public static function newFromRow( $row ) {
		$post = new LiquidThreadsPost;
		$post->initialiseFromRow( $row );
		
		return $post;
	}
	
	/**
	 * Factory method to create a new post.
	 * Must be saved with LiquidThreadsPost::save()
	 * @param $topic LiquidThreadsTopic: The topic this post is in.
	 * @param $parent LiquidThreadsPost: (optional) A parent post for this post.
	 * @return LiquidThreadsPost: The new post.
	 */
	public static function create( $topic, $parent = null ) {
		$post = new LiquidThreadsPost;
		
		$post->initialiseNew( $topic, $parent );
		
		return $post;
	}
	
	/* Initialisation functions. One of these has to be called on a new object */
	
	/**
	 * Initialise this object from a database row.
	 * This "row" may be joined to the current version row.
	 * @param $row Object: A row object containing the
	 * appropriate lqt_post and (optionally) lqt_post_version/lqt_topic(_version) rows.
	 */
	protected function initialiseFromRow( $row ) {
		if ( empty($row->lqp_id) ) {
			throw new MWException( "Invalid input to ".__METHOD__ );
		}
		
		// Load members
		$this->id = $row->lqp_id;
		$this->topicID = $row->lqp_topic;
		$this->currentVersionID = $row->lqp_current_version;
		
		if ( isset($row->lpv_id) ) {
			$version = LiquidThreadsPostVersion::newFromRow( $row );
			$this->currentVersion = $version;
		}
		
		if ( isset($row->lqt_id) ) {
			$topic = LiquidThreadsTopic::newFromRow( $row );
			$this->topic = $topic;
		}
	}
	
	/**
	 * Initialise this object as a new Post.
	 * @param $topic LiquidThreadsTopic: The topic this post is in.
	 * @param $parent LiquidThreadsPost: (optional) A parent post for this post.
	 */
	protected function initialiseNew( $topic, $parent = null ) {
		$this->id = 0;
		
		$this->currentVersion = LiquidThreadsPostVersion::createNewPost();
		$this->pendingVersion = $this->currentVersion;
		
		$this->topic = $topic;
		$this->parent = $parent;
	}
	
	/* SAVE CODE */
	
	/**
	 * Commits pending changes to the database.
	 * Internally, triggers a commit() operation on the current pending version.
	 * @param $comment String: Optional edit comment for this operation.
	 */
	public function save( $comment = null ) {
		if ( $this->pendingVersion ) {
			$this->pendingVersion->setComment( $comment );
			$this->pendingVersion->commit( $comment );
			$this->pendingVersion = null;
			
			if ( !$this->id ) {
				$this->insert();
			}
		} else {
			throw new MWException( "There are no pending changes." );
		}
	}
	
	/**
	 * Inserts this Post into the database.
	 * ONLY to be called *after* the first PostVersion is saved to the database.
	 * This should only really be called from LiquidThreadsPost::save
	 */
	protected function insert() {
		$dbw = wfGetDB( DB_MASTER );
		
		$row = array(
			'lqp_id' => $dbw->nextSequenceValue( 'lqt_post_lqp_id' ),
			'lqp_current_version' => 0, // Filled later
			'lqp_topic' => $this->topic->getID(),
			'lqp_parent_post' => $this->parent ? $this->parent->getID() : null,
		);
		
		$dbw->insert( 'lqt_post', $row, __METHOD__ );
		
		$postId = $dbw->insertId();
		$this->id = $postId;
		
		$dbw->update( 'lqt_post_version', array( 'lpv_post' => $postId ),
				array( 'lpv_id' => $this->currentVersion->getID() ),
				__METHOD__ );
	}

	/* PROPERTY ACCESSORS */
	
	/**
	 * Returns the unique ID assigned to this Post. This ID is unique among Posts.
	 */
	public function getID() {
		return $this->id;
	}
	
	/**
	 * Returns the current version object.
	 */
	public function getCurrentVersion() {
		if ( $this->currentVersion ) {
			return $this->currentVersion;
		} elseif ( $this->currentVersionID ) {
			$this->currentVersion =
				LiquidThreadsPostVersion::newFromID( $this->currentVersionID );
			return $this->currentVersion;
		} else {
			throw new MWException( "No current version to retrieve" );
		}
	}
	
	/**
	 * Returns the pending version object.
	 * The pending version is the version being affected by set*() functions.
	 * It is saved to the database when you call LiquidThreadsPost::commit()
	 * If it doesn't exist, it will be created.
	 */
	protected function getPendingVersion() {
		if ( !$this->pendingVersion ) {
			$this->pendingVersion = LiquidThreadsPostVersion::create( $this );
		}
		
		return $this->pendingVersion;
	}
	
	/* Accessors for various properties. Mostly stored in the current version */
	
	/**
	 * Returns the current comment text.
	 */
	public function getText() {
		return $this->getCurrentVersion()->getText();
	}
	
	/**
	 * Returns the person that this post is attributed to.
	 */
	public function getPoster() {
		return $this->getCurrentVersion()->getPoster();
	}
	
	/**
	 * Returns the ID of the topic that this post belongs to.
	 */
	public function getTopicID() {
		return $this->getCurrentVersion()->getTopicID();
	}
	
	/**
	 * Returns the ID of the parent post for this post.
	 */
	public function getParentID() {
		return $this->getCurrentVersion()->getParentID();
	}
	
	/**
	 * Returns the signature associated with this post.
	 */
	public function getSignature() {
		return $this->getCurrentVersion()->getSignature();
	}
	
	/* PROPERTY SETTERS */
	
	
}

