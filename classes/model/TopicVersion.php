<?php

/**
 * This class represents a single version of a specific Topic.
 */
class LiquidThreadsTopicVersion {
	/*** MEMBERS ***/
	
	/*** Metadata ***/
	
	/** ID of this version **/
	protected $id;
	
	/** ID of the post that this version applies to **/
	protected $postID;
	
	/** User object for the person who created this *VERSION* **/
	protected $versionUser;
	
	/** Timestamp that this version was created **/
	protected $timestamp;
	
	/** Edit comment associated with this version **/
	protected $comment;
	
	/*** Version data ***/
	
	/** The ID of the channel that this topic is in **/
	protected $channelID = null;
	
	/** This topic's subject **/
	protected $subject = null;
	
	/* Ancestry information, for not-saved errors */
	
	/** Where this object was instantiated. Used for not-saved errors **/
	protected $source;
	
	/** Whether or not this object has been destroyed **/
	protected $destroyed = false;
	
	/*** FACTORY FUNCTIONS ***/
	
	/**
	 * Default constructor.
	 * Don't use this externally! Use one of the other factory methods.
	 */
	protected function __construct() {
		$this->source = wfGetAllCallers();
	}
	
	/**
	 * Default destructor
	 * If this Version has yet to be saved, throws an exception.
	 * To prevent this behaviour, call destroy()
	 */
	public function __destruct() {
		if ( $this->id == 0 && !$this->destroyed ) {
			throw new MWException( "Version object has not been saved nor destroyed. From: " . $this->source );
		}
	}
	
	/**
	 * Disables the "not saved" error message.
	 * This MUST be called if you do not plan to save this version.
	 */
	public function destroy() {
		$this->destroyed = true;
	}
	
	/**
	 * Factory method to retrieve TopicVersions from Database conditions.
	 * Don't use this method externally, use one of the other factory methods.
	 * @param $conditions Array: Conditions to pass to Database::select
	 * @return Array: LiquidThreadsTopicVersion objects with those conditions.
	 */
	public static function loadFromConditions( $conditions, $fetchText = false, $options = array() ) {
		$dbr = wfGetDB( DB_SLAVE );
		
		$tables = array( 'lqt_topic_version' );
		$fields = '*';
		
		$res = $dbr->select( $tables, $fields, $conditions, __METHOD__, array(), $joins );
		
		$output = array();
		
		foreach( $res as $row ) {
			$version = new LiquidThreadsTopicVersion;
			$version->initialiseFromRow( $row );
			
			$output[$version->getID()] = $version;
		}
		
		return $output;
	}
	
	/**
	 * Factory method to retrieve a TopicVersion from a Topic and point in time.
	 * If the point in time is blank, it retrieves the most recent one.
	 * @param $post The LiquidThreadsTopic to retrieve a version for.
	 * @param $timestamp String: A timestamp for the point in time (optional)
	 * @return The LiquidThreadsTopicVersion object for that topic at that point in time, or null.
	 */
	public static function newPointInTime( $topic, $timestamp = null ) {
		if ( ! $topic instanceof LiquidThreadsTopic ) {
			throw new MWException( "Invalid argument to ".__METHOD__ );
		}
		
		$dbr = wfGetDB( DB_SLAVE );
		
		$conds = array( 'ltv_topic' => $topic->getID() );
		
		if ( $timestamp ) {
			$conds[] = 'ltv_timestamp < ' .
				$dbr->addQuotes( $dbr->timestamp( $timestamp ) );
		}
		
		$row = $dbr->selectRow( 'lqt_topic_version', '*', $conds, __METHOD__ );
		
		if ( $row ) {
			return self::newFromRow( $row );
		} else {
			return null;
		}
	}
	
	/**
	 * Factory method to retrieve a TopicVersion by ID.
	 * Throws an exception if the version does not exist.
	 * @param $id Integer: The ID of the version to retrieve.
	 * @return LiquidThreadsTopicVersion: The Version with that ID.
	 */
	public static function newFromID( $id ) {
		$condition = array( 'ltv_id' => $id );
		
		$versions = self::loadFromConditions( $condition );
		
		// Check that the version actually exists.
		if ( count($versions) < 1 ) {
			throw new MWException( "Attempt to load topic version #$id, which does not exist" );
		}
		
		$version = array_shift( $versions );
		return $version;
	}
	
	/**
	 * Factory method to load a version from a row object.
	 * @param $row Object: Row object returned from DatabaseResult::fetchObject()
	 * @return LiquidThreadsTopicVersion: The new TopicVersion object.
	 */
	public static function newFromRow( $row ) {
		$version = new LiquidThreadsTopicVersion;
		$version->initialiseFromRow( $row );
		
		return $version;
	}
	
	/**
	 * Factory method to create a new version of a topic.
	 * @param $post The LiquidThreadsTopic to create a version of.
	 * @param $baseVersion LiquidThreadsTopicVersion: The revision to base on. \em{(optional)}
	 * @return LiquidThreadsTopicVersion: The new version object.
	 */
	public static function create( LiquidThreadsTopic $topic,
			LiquidThreadsTopicVersion $baseVersion = null )
	{
		$version = new LiquidThreadsTopicVersion;
		
		$version->initialiseNew( $topic, $baseVersion );
		
		return $version;
	}
	
	/**
	 * Factory method to create a Version for a new LiquidThreadsTopic.
	 * @param $channel The LiquidThreadsChannel to put this topic in.
	 * @return LiquidThreadsTopicVersion: A Version object for a new topic.
	 */
	public static function createNewTopic( LiquidThreadsChannel $channel )
	{
		$version = new LiquidThreadsTopicVersion;
		
		$version->initialiseNewTopic( $channel );
		
		return $post;
	}
	
	/* Initialisation functions. One of these has to be called on a new object */
	
	/**
	 * Initialise this object from a database row.
	 * This "row" may be joined to the text row.
	 * @param $row Object: A row object containing the
	 * appropriate lqt_topic_version row.
	 */
	protected function initialiseFromRow( $row ) {
		if ( empty($row->ltv_id) ) {
			throw new MWException( "Invalid input to ".__METHOD__ );
		}
		
		// Load members
		$this->id = $row->ltv_id;

		// Metadata members
		$user = null;
		if ( $row->ltv_user_id > 0 ) {
			if ( empty($row->user_name) ) {
				$user = User::newFromId( $row->ltv_user_id );
			} else {
				$user = User::newFromRow( $row );
			}
		} elseif ( User::isIP( $row->ltv_user_ip ) ) {
			$user = User::newFromName( $row->ltv_user_ip );
		}
		
		if ( is_null($user) ) {
			throw new MWException( "Invalid user found in ltv_user: {$row->ltv_user_id}/{$row->ltv_user_ip}" );
		}
		$this->versionUser = $user;
		
		// Other metadata
		$this->comment = $row->ltv_comment
		$this->timestamp = wfTimestamp( TS_MW, $row->ltv_timestamp );
		$this->topicID = $row->ltv_topic;
		
		// Real version data loading
		$this->channelID = $row->ltv_channel;
		$this->subject = $row->ltv_subject;
	}
	
	/**
	 * Initialise a new version object for a post.
	 * If the base revision is not specified, it is based on the current version of the Post.
	 * @param $post LiquidThreadsPost: The post that this version is for.
	 * @param $baseVersion LiquidThreadsPostVersion: The base version for this version. \em{(optional)}
	 */
	protected function initialiseNew( LiquidThreadsPost $post,
			LiquidThreadsPostVersion $baseVersion = null )
	{
		if ( ! $baseVersion ) {
			$baseVersion = $post->getCurrentVersion();
		}
		
		// Copy all data members across.
		$this->channelID = $baseVersion->getChannelID();
		$this->subject = $baseVersion->getSubject();
		
		global $wgUser;
		$this->editor = $wgUser;
		
		$this->id = 0;
		$this->topicID = $topic->getID();
	}
	
	/**
	 * Initialise a new version object for a new topic.
	 * @param $channel LiquidThreadsChannel: The channel that this topic is in.
	 */
	protected function initialiseNewTopic( LiquidThreadsChannel $channel )
	{
		global $wgUser;
		
		$this->id = 0;
		$this->versionUser = $wgUser;
		$this->channelID = $channel->getID();
		$this->subject = '';
	}
	
	/* SETTING AND SAVING */
	
	/**
	 * Returns true if you're allowed to change properties.
	 * Currently, this is only if the version hasn't been saved to the DB.
	 */
	protected function isMutable() {
		return $this->id == 0;
	}
	
	/**
	 * Set the editor for this version.
	 * @param $editor User: The user who created this version.
	 */
	public function setEditor( User $newEditor ) {
		if ( !$this->isMutable() ) {
			throw new MWException( "This Version object is not mutable." );
		}
		
		$this->editor = $newEditor;
	}
	
	/**
	 * Set the edit comment for this version.
	 * @param $comment String: The edit comment for this version
	 */
	public function setComment( $comment ) {
		if ( !$this->isMutable() ) {
			throw new MWException( "This Version object is not mutable." );
		}
		
		$this->comment = $comment;
	}
	
	/**
	 * Set the parent channel for this version.
	 * @param $channel LiquidThreadsChannel: The new parent channel.
	 */
	public function setChannel( LiquidThreadsChannel $channel ) {
		$this->channelID = $channel->getID();
	}
	
	/**
	 * Sets the subject associated with this version.
	 * @param $subject String: The new subject
	 */
	public function setSignature( $subject ) {
		$this->subject = $subject;
	}
	
	/**
	 * Saves this Version to the database.
	 * @param $comment String: (optional) The edit comment for this version.
	 */
	public function commit( $comment = null ) {
		if ( $this->id != 0 ) {
			throw new MWException( "Attempt to save a version already in the database." );
		}
		
		if ( $comment !== null ) {
			$this->comment = $comment;
		}
		
		$dbw = wfGetDB( DB_MASTER );
		
		$row = array(
			'ltv_id' => $dbw->nextSequenceValue( 'lqt_topic_version_ltv_id' ),
			'ltv_topic' => $this->topicID,
			'ltv_timestamp' => $dbw->timestamp( wfTimestampNow() ),
			'ltv_comment' => $this->comment,
			'ltv_channel' => $this->channelID,
			'ltv_subject' => $this->subject,
		);
		
		// Poster and user data
		$editor = $this->getEditor();
		
		if ( $editor->isAnon() ) {
			$row['ltv_user_ip'] = $editor->getName();
		} else {
			$row['ltv_user_id'] = $editor->getID();
		}
		
		$dbw->insert( 'lqt_topic_version', $row, __METHOD__ );
		
		$this->id = $dbw->insertId();
		
		// Update pointer
		if ( $this->topicID ) {
			$dbw->update( 'lqt_topic', array( 'lqt_current_version', $this->id ),
					array( 'lqt_id' => $this->topicID ), __METHOD__ );
		}
	}

	/* PROPERTY ACCESSORS */
	
	/**
	 * Returns the unique ID assigned to this Topic Version.
	 * This ID is unique among Topic Versions.
	 */
	public function getID() {
		if ( ! $this->id ) {
			throw new MWException( "This Topic Version does not have an ID" );
		}
		
		return $this->id;
	}
	
	/**
	 * Returns the user who created this version.
	 */
	public function getEditor() {
		if ( ! $this->versionUser ) {
			throw new MWException( "Invalid or missing editor" );
		}
		
		return $this->versionUser;
	}
	
	/**
	 * Returns the timestamp for this version.
	 */
	public function getEditTime() {
		if ( ! $this->timestamp ) {
			throw new MWException( "Missing timestamp" );
		}
		
		return $this->timestamp;
	}
	
	/**
	 * Returns the edit comment for this version
	 */
	public function getEditComment() {
		return $this->comment;
	}
	
	/* Data */
	
	/**
	 * Retrieves the ID of the channel associated with this Topic Version.
	 */
	public function getChannelID() {
		return $this->channelID;
	}
	
	/**
	 * Retrieves the subject shown for this post.
	 */
	public function getSubject() {
		return $this->subject;
	}
}
