<?php

/**
 * LiquidThreads Topic class.
 * This class represents a single threaded discussion.
 * @addtogroup LiquidThreads model
 */
class LiquidThreadsTopic extends LiquidThreadsObject {

	/* MEMBER VARIABLES */
	/** The current LiquidThreadsTopicVersion object. **/
	protected $currentVersion;
	/** The ID of the current version **/
	protected $currentVersionID;
	
	/** The version that is being worked on by set methods **/
	protected $pendingVersion;
	
	/** The unique ID for this Topic **/
	protected $id;
	
	/** The ID of the channel that this topic is in. **/
	protected $channelID;
	/** The LiquidThreadsChannel object that this topic is in. **/
	protected $channel;
	
	/** Array of LiquidThreadsPost objects, the posts in this topic **/
	protected $posts;
	
	/** Array of LiquidThreadsPost objects, the direct responses to this topic. **/
	protected $directResponses;
	
	/** The number of replies that this topic has **/
	protected $replyCount;
	
	/** The last time this LiquidThreadsTopic was modified or replied to. **/
	protected $touchedTime;
	
	/* FACTORY METHODS */
	
	/**
	 * Default constructor.
	 * Not to be called directly.
	 */
	protected function __construct() {
		
	}
	
	/**
	 * Factory method to retrieve Topics from Database conditions.
	 * Don't use this method externally, use one of the other factory methods.
	 * @param $conditions Array: Conditions to pass to Database::select
	 * @return Array of LiquidThreadsTopic objects with those conditions.
	 */
	public static function loadFromConditions( $conditions, $fetchText = false ) {
		$dbr = wfGetDB( DB_SLAVE );
		
		$tables = array( 'lqt_topic', 'lqt_topic_version' );
		$fields = '*';
		$joins = array(
			'lqt_topic_version' => array(
				'left join',
				array(
					'lqt_current_version=ltv_id',
				),
			),
		);
		
		$res = $dbr->select( $tables, $fields, $conditions, __METHOD__, array(), $joins );
		
		$output = array();
		
		foreach( $res as $row ) {
			$topic = new LiquidThreadsTopic;
			$topic->initialiseFromRow( $row );
			
			$output[$topic->getID()] = $topic;
		}
		
		return $output;
	}
	
	/**
	 * Factory method to retrieve a Topic by ID.
	 * Throws an exception if the topic does not exist.
	 * @param $id Integer The ID of the topic to retrieve.
	 * @return LiquidThreadsTopic: The topic with that ID.
	 */
	public static function newFromID( $id ) {
		$condition = array( 'lqt_id' => $id );
		
		$topics = self::loadFromConditions( $condition );
		
		// Check that the post actually exists.
		if ( count($topics) < 1 ) {
			throw new MWException( "Attempt to load topic #$id, which does not exist" );
		}
		
		$topic = array_shift( $topics );
		return $topic;
	}
	
	/**
	 * Factory method to load a topic from a row object.
	 * The row object may optionally contain the appropriate Version data, too.
	 * @param $row Object: Row object returned from DatabaseResult::fetchObject()
	 * @return LiquidThreadsTopic The new Topic object.
	 */
	public static function newFromRow( $row ) {
		$topic = new LiquidThreadsTopic;
		$topic->initialiseFromRow( $row );
		
		return $topic;
	}
	
	/**
	 * Factory method to create a new topic.
	 * Must be saved with LiquidThreadsTopic::save()
	 * @param $channel LiquidThreadsChannel: The channel this topic is in.
	 * @return LiquidThreadsTopic: The new post.
	 */
	public static function create( $channel ) {
		$topic = new LiquidThreadsTopic;
		
		$topic->initialiseNew( $channel );
		
		return $topic;
	}
	
	/* Initialisation functions. One of these has to be called on a new object */
	
	/**
	 * Initialise this object from a database row.
	 * This "row" may be joined to the current version row.
	 * @param $row Object: A row object containing the
	 * appropriate lqt_topic and (optionally) lqt_topic_version rows.
	 */
	protected function initialiseFromRow( $row ) {
		if ( empty($row->lqt_id) ) {
			throw new MWException( "Invalid input to ".__METHOD__ );
		}
		
		// Load members
		$this->id = $row->lqt_id;
		$this->channelID = $row->lqt_channel;
		$this->currentVersionID = $row->lqt_current_version;
		$this->replyCount = $row->lqt_replies;
		$this->touchedTime = $row->lqt_touched;
		
		if ( isset($row->ltv_id) ) {
			$version = LiquidThreadsTopicVersion::newFromRow( $row );
			$this->currentVersion = $version;
		}
	}
	
	/**
	 * Initialise this object as a new Topic.
	 * @param $channel LiquidThreadsChannel: The channel this topic is in.
	 */
	protected function initialiseNew( $channel ) {
		$this->id = 0;
		
		$this->currentVersionID = 0;
		$this->currentVersion = LiquidThreadsTopicVersion::createNewTopic( $this, $channel );
		$this->pendingVersion = $this->currentVersion;
		$this->replyCount = 0;
		
		$this->channel = $channel;
	}
	
	/* SAVE CODE */
	
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
	 * Updates the Topic row in the database.
	 * To be called after a new LiquidThreadsTopicVersion is inserted and
	 *  $this->currentVersion has been updated.
	 * Should only really be called from LiquidThreadsTopicVersion::commit
	 * @param $version The LiquidThreadsTopicVersion object that was just saved.
	 */
	public function update( $version ) {
		if ( ! $this->getID() ) {
			throw new MWException( "Attempt to call update() on a topic not yet in the database." );
		}
		
		$dbw = wfGetDB( DB_MASTER );
		
		$this->pendingVersion = null;
		$this->previousVersion = $this->currentVersion;
		$this->currentVersion = $version;
		
		$row = $this->getRow();
		$dbw->update( 'lqt_topic', $row, array( 'lqt_id' => $this->getID() ),
				__METHOD__ );

		$title = $this->getChannel()->getTitle();
		$title->invalidateCache();
	}
	
	/**
	 * Inserts this Post into the database.
	 * ONLY to be called *after* the first PostVersion is saved to the database.
	 * This should only really be called from LiquidThreadsTopicVersion::commit
	 * @param $version The LiquidThreadsTopicVersion object that was just saved.
	 */
	public function insert( $version ) {
		if ( $this->getID() ) {
			throw new MWException( "Attempt to call insert() on a topic already inserted" );
		}
		
		$this->currentVersion = $version;
		$this->pendingVersion = null;
		
		$dbw = wfGetDB( DB_MASTER );
		
		$row = $this->getRow();		
		$dbw->insert( 'lqt_topic', $row, __METHOD__ );
		
		$topicID = $dbw->insertId();
		$this->id = $topicID;
		
		$version->setTopicID( $topicID );
		
		$title = $this->getChannel()->getTitle();
		$title->invalidateCache();
	}
	
	/**
	 * Generates a row object to be inserted or updated in the database.
	 * Used internally by update() and insert()
	 */
	protected function getRow() {
		$dbw = wfGetDB( DB_MASTER );
		$row = array(
			'lqt_current_version' => $this->currentVersion->getID(),
			'lqt_channel' => $this->getChannelID(),
			'lqt_replies' => $this->replyCount,
			'lqt_touched' => $dbw->timestamp( wfTimestampNow() ),
		);
		
		if ( !$this->id ) {
			$row['lqt_id'] = $dbw->nextSequenceValue( 'lqt_topic_lqt_id' );
		}
		
		return $row;
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
				LiquidThreadsTopicVersion::newFromID( $this->currentVersionID );
			return $this->currentVersion;
		} else {
			throw new MWException( "No current version to retrieve" );
		}
	}
	
	/**
	 * Returns the pending version object.
	 * The pending version is the version being affected by set*() functions.
	 * It is saved to the database when you call LiquidThreadsTopic::save()
	 * If it doesn't exist, it will be created.
	 */
	public function getPendingVersion() {
		if ( !$this->pendingVersion ) {
			$this->pendingVersion = LiquidThreadsTopicVersion::create( $this );
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
	
	/* Accessors for various properties. Mostly stored in the current version */
	
	/**
	 * @return String: The current subject text for this Topic.
	 */
	public function getSubject() {
		return $this->getCurrentVersion()->getSubject();
	}
	
	/**
	 * @return String: The current summary text for this Topic.
	 */
	public function getSummary() {
		return $this->getCurrentVersion()->getSummaryText();
	}
	
	/**
	 * Returns the ID of the channel that this topic belongs to.
	 */
	public function getChannelID() {
		return $this->getCurrentVersion()->getChannelID();
	}
	
	/**
	 * Returns the channel object that this topic belongs to.
	 */
	public function getChannel() {
		if ( $this->channel instanceof LiquidThreadsChannel ) {
			return $this->channel;
		} // else
		
		$this->channel = LiquidThreadsChannel::newFromId( $this->channelID );
		
		return $this->channel;
	}
	
	/**
	 * Retrieves this topic's posts
	 * @return Array of LiquidThreadsPost objects.
	 */
	public function getPosts() {
		if ( is_array( $this->posts ) ) {
			return $this->posts;
		} // else
		
		$conds = array( 'lqp_topic' => $this->getId() );
		
		$this->posts = LiquidThreadsPost::loadFromConditions( $conds );
		$this->replyCount = count($this->posts);
		
		return $this->posts;
	}
	
	/**
	 * Retrieves direct responses to this topic.
	 * Basically, all posts with no parentID set
	 * @return Array of LiquidThreadsPost objects.
	 */
	public function getDirectResponses() {
		if ( is_array( $this->directResponses ) ) {
			return $this->directResponses;
		} // else
		
		$posts = $this->getPosts();
		
		$this->directResponses = array();
		
		foreach( $posts as $post ) {
			if ( ! $post->getParentID() ) {
				$this->directResponses[$post->getID()] = $post;
			}
		}
		
		return $this->directResponses;
	}
	
	/**
	 * Retrieves the "touched time" of this Topic.
	 * The last time this topic was modified or replied to.
	 * @return MW format timestamp.
	 */
	public function getTouchedTime() {
		return wfTimestamp( TS_MW, $this->touchedTime );
	}
	
	/**
	 * @return The number of posts in this topic.
	 */
	public function getPostCount() {
		return count( $this->getPosts() );
	}
	
	
	/* PROPERTY SETTERS */
	
	/**
	 * Sets the subject of this topic.
	 * @param $text String: new subject.
	 */
	public function setSubject( $text ) {
		$this->getPendingVersion()->setSubject( $text );
	}
	
	/**
	 * Sets the summary text of this topic.
	 * @param $text String: new summary text.
	 */
	public function setSummary( $text ) {
		$this->getPendingVersion()->setSummaryText( $text );
	}
	
	/**
	 * Sets the channel ID for this topic (i.e. moves the topic to a new channel)
	 * @param $id Integer: The ID of the channel to move the topic to.
	 */
	public function setChannelID( $id ) {
		$this->getPendingVersion()->setChannelID( $id );
	}
	
	/**
	 * Moves this post to another topic.
	 * @param $topic LiquidThreadsTopic: Where to move this post.
	 */
	public function setChannel( LiquidThreadsChannel $channel ) {
		if ( !$channel || ! $channel instanceof LiquidThreadsChannel ) {
			throw new MWException( "Invalid argument to ".__METHOD__ );
		}
		
		$this->setChannelID( $channel->getID() );
	}
	
	/**
	 * Adds a post to the instance cache.
	 * Used when a post is saved to the database,
	 * only to be called by LiquidThreadsPost::insert
	 * @param $post The LiquidThreadsPost object
	 */
	public function addPost( $post ) {
		// Initialise initial array.
		$this->getPosts();
		
		if ( ! $post->getId() ) {
			throw new MWException( "You need to save the post first!" );
		}
		
		$this->posts[$post->getId()] = $post;
		
		$this->touch();
	}
	
	/**
	 * Gets a globally unique (for all objects) identifier for this object
	 * @return String
	 */
	public function getUniqueIdentifier() {
		return 'lqt-topic_'.$this->getID();
	}
	
	/**
	 * Update the last-modified date for this topic.
	 */
	public function touch() {
		$dbw = wfGetDB( DB_MASTER );
		
		$dbw->update( 'lqt_topic',
			array( 'lqt_touched' => $dbw->timestamp( wfTimestampNow() ) ),
			array( 'lqt_id' => $this->getID() ),
			__METHOD__ );
		
		$title = $this->getChannel()->getTitle();
		$title->invalidateCache();
	}
}

