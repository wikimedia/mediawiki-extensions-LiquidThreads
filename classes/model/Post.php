<?php

/**
 * LiquidThreads Post class.
 * This class represents a single comment in a threaded discussion.
 * @addtogroup LiquidThreads model
 */
class LiquidThreadsPost extends LiquidThreadsObject {

	/* MEMBER VARIABLES */
	
	/** The unique ID for this Post **/
	protected $id;
	
	/** The ID of the topic that this post is in. **/
	protected $topicID;
	/** The LiquidThreadsTopic object that this post is in. **/
	protected $topic;
	
	/** The version that is being worked on by set methods **/
	protected $pendingVersion;
	
	/** The ID of this post's parent post **/
	protected $parentID;
	/** The LiquidThreadsPost that this is found underneath. **/
	protected $parent;
	
	/** Replies **/
	protected $replies = null;
	
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
				'left join',
				array(
					'lqp_current_version=lpv_id',
				),
			),
			'text' => array(
				'left join',
				array(
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
		
		$this->currentVersion = LiquidThreadsPostVersion::createNewPost( $this, $topic, $parent );
		$this->pendingVersion = $this->currentVersion;
		
		$this->topic = $topic;
		$this->parent = $parent;
	}
	
	/* SAVE CODE */
	
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
	public function getPendingVersion() {
		if ( !$this->pendingVersion ) {
			$this->pendingVersion = LiquidThreadsPostVersion::create( $this );
		}
		
		return $this->pendingVersion;
	}
	
	/**
	 * Discard unsaved changes.
	 */
	public function reset() {
		$this->pendingVersion->destroy();
		$this->pendingVersion = null;
	}
	
	/**
	 * Commits pending changes to the database.
	 * Internally, triggers a commit() operation on the current pending version.
	 * @param $comment String: Optional edit comment for this operation.
	 */
	public function save( $comment = null ) {
		if ( $this->pendingVersion ) {
			$this->pendingVersion->commit( $comment );
		} else {
			throw new MWException( "There are no pending changes." );
		}
	}
	
	/**
	 * Get a row array to insert into the database
	 * @return Row in Array form to insert into the database.
	 */
	protected function getRow() {
		$dbw = wfGetDB( DB_MASTER );
		
		if ( $this->getTopicID() == 0 ) {
			throw new MWException( "Topic must be saved first!" );
		}
		
		if ( $this->currentVersion == null ) {
			throw new MWException( "No current version available!" );
		}
		
		$row = array(
			'lqp_current_version' => $this->currentVersion->getID(),
			'lqp_topic' => $this->getTopicID(),
			'lqp_parent_post' => $this->parent ? $this->parent->getID() : null,
		);
		
		if ( !$this->id ) {
			$row['lqp_id'] = $dbw->nextSequenceValue( 'lqt_post_lqp_id' );
		}
		
		return $row;
	}
	
	/**
	 * Inserts this Post into the database.
	 * ONLY to be called *after* the first PostVersion is saved to the database.
	 * This should only really be called from LiquidThreadsPost::save
	 * @param $version The LiquidThreadsPostVersion that has just been inserted.
	 */
	public function insert( $version ) {
		$dbw = wfGetDB( DB_MASTER );
		
		if ( $this->getID() ) {
			throw new MWException( "Post has already been inserted.!" );
		}
		
		$row = $this->getRow();
		
		$result = $dbw->insert( 'lqt_post', $row, __METHOD__ );
		
		$postId = $dbw->insertId();
		$this->id = $postId;
		
		$this->currentVersion = $version;
		$this->pendingVersion = null;
		$version->setPostID( $postId );
		
		if ( $this->topic ) {
			$this->topic->addPost( $this );
		}
	}
	
	/**
	 * Updates this post in the database.
	 * ONLY to be called *after* a PostVersion has been moved into currentVersion.
	 * Only to be called from LiquidThreadsPost::update()
	 * @param $version The newest version of the post.
	 */
	public function update( $version ) {
		$dbw = wfGetDB( DB_MASTER );
		
		if ( ! $this->getID() ) {
			throw new MWException( "Post has not been saved!" );
		}
		
		$this->previousVersion = $this->currentVersion;
		$this->currentVersion = $version;
		$this->pendingVersion = null;
		
		$row = $this->getRow();
		
		$dbw->update( 'lqt_post', $row, array( 'lqp_id' => $this->getID() ),
				__METHOD__ );
				
		$this->getTopic()->touch();
	}

	/* PROPERTY ACCESSORS */
	
	/**
	 * Returns the unique ID assigned to this Post. This ID is unique among Posts.
	 */
	public function getID() {
		return $this->id;
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
	 * Returns the LiquidThreadsTopic that this post belongs to.
	 */
	public function getTopic() {
		if ( !$this->topic ) {
			$this->topic = LiquidThreadsTopic::newFromID( $this->getTopicID() );
		}
		
		return $this->topic;
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
	
	/**
	 * Sets the text content of this post.
	 * @param $text String: new text content.
	 */
	public function setText( $text ) {
		$this->getPendingVersion()->setText( $text );
	}
	
	/**
	 * Sets the topic ID for this post (i.e. moves the post to a new topic)
	 * Also handles removing the parent, if one exists and we're changing topic.
	 * @param $id Integer: The ID of the topic to move the post to.
	 */
	public function setTopicID( $id ) {
		$this->getPendingVersion()->setTopicID( $id );
		
		if ( $id != $this->getTopicID() ) {
			$this->setParent(null);
		}
	}
	
	/**
	 * Sets the parent ID for this post (i.e. moves the post underneath another post).
	 * It is assumed that the other post is contained in the same topic.
	 * If you're not sure, instantiate the new parent Post and use
	 *  LiquidThreadsPost::setParent()
	 * @param $id Integer: The ID of the post to move this post underneath.
	 */
	public function setParentID( $id ) {
		$this->getPendingVersion()->setParentID( $id );
	}
	
	/**
	 * Sets the parent post to $post (moves this post underneath $post.
	 * An exception will be thrown if the new parent post is in a different topic.
	 * @param $post LiquidThreadsPost: The post to move underneath, or NULL for none.
	 * @param $check Boolean: Whether or not to check if the new parent post is in the same topic.
	 */
	public function setParent( LiquidThreadsPost $post, $check = true ) {
		if ( $post == null ) {
			$this->setParentID(0);
			return;
		}
	
		$parent_topics = array( $post->getTopicID() );
		$parent_topics[] = $post->getPendingVersion()->getTopicID();
		$parent_topics = array_unique( $parent_topics );
		
		$my_topics = array( $this->getTopicID() );
		$my_topics[] = $this->getPendingVersion()->getTopicID();
		
		// If the new parent is neither currently nor going to be in the same topic
		if ( $check &&
			count(array_intersect( $my_topics, $parent_topics )) == 0 )
		{
			throw new MWException( "Attempt to assign parent post to a post outside this topic." );
		}
		
		$this->setParentID( $post->getID() );
	}
	
	/**
	 * Moves this post to another topic.
	 * @param $topic LiquidThreadsTopic: Where to move this post.
	 */
	public function setTopic( LiquidThreadsTopic $topic ) {
		if ( !$topic || ! $topic instanceof LiquidThreadsTopic ) {
			throw new MWException( "Invalid argument to ".__METHOD__ );
		}
		
		$this->setTopicID( $topic->getID() );
	}
	
	/**
	 * Sets the signature for this post.
	 * @param $sig String: The new signature
	 */
	public function setSignature( $sig ) {
		$this->getPendingVersion()->setSignature( $sig );
	}
	
	/**
	 * Gets the timestamp attributed to this post.
	 */
	public function getPostTime() {
		return wfTimestamp( TS_MW, $this->getCurrentVersion()->getPostTime() );
	}
	
	/**
	 * Gets replies to this post.
	 */
	public function getReplies() {
		if ( is_null( $this->replies ) ) {
			$conditions = array( 'lqp_parent_post' => $this->getID() );
			$this->replies = self::loadFromConditions( $conditions, true );
		}
		
		return $this->replies;
	}
	
	/**
	 * Gets a globally unique (for all objects) identifier for this object
	 * @return String
	 */
	public function getUniqueIdentifier() {
		return 'lqt-post_'.$this->getID();
	}
}

