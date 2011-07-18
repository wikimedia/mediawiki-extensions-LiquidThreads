<?php

/**
 * This class represents a single version of a specific Topic.
 */
class LiquidThreadsTopicVersion {
	/*** MEMBERS ***/
	
	/*** Metadata ***/
	
	/** ID of this version **/
	protected $id;
	
	/** The topic that this version applies to **/
	protected $topic;
	
	/** ID of the topic that this version applies to **/
	protected $topicID;
	
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
	
	/** Summary text ID -- get the row from the text table **/
	protected $summaryTextID = null;
	
	/** Summary text row -- use Revision::getRevisionText to convert to text **/
	protected $summaryTextRow = null;
	
	/** Summary text ES URL -- convert to text by retrieving from ES,
	 * substituting into $summaryTextRow and calling Revision::getRevisionText **/
	protected $summaryTextURL = null;
	
	/** Actual summary text **/
	protected $summaryText = null;
	
	/** Whether or not the text has been modified directly in $text
	 * (and therefore needs to be saved to ES). **/
	protected $summaryTextDirty = false;
	
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
	
	// Commented out due to misfires
// 	/**
// 	 * Default destructor
// 	 * If this Version has yet to be saved, throws an exception.
// 	 * To prevent this behaviour, call destroy()
// 	 */
// 	public function __destruct() {
// 		if ( $this->id == 0 && !$this->destroyed ) {
// 			throw new MWException( "Version object has not been saved nor destroyed. From: " . $this->source );
// 		}
// 	}
	
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
	public static function loadFromConditions( $conditions, $options = array() ) {
		$dbr = wfGetDB( DB_SLAVE );
		
		$tables = array( 'lqt_topic_version' );
		$fields = '*';
		
		$res = $dbr->select( $tables, $fields, $conditions, __METHOD__, array() );
		
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
	 * @param $topic The LiquidThreadsTopic being created
	 * @param $channel The LiquidThreadsChannel to put this topic in.
	 * @return LiquidThreadsTopicVersion: A Version object for a new topic.
	 */
	public static function createNewTopic( LiquidThreadsTopic $topic,
		LiquidThreadsChannel $channel )
	{
		$version = new LiquidThreadsTopicVersion;
		
		$version->initialiseNewTopic( $topic, $channel );
		
		return $version;
	}
	
	/* Initialisation functions. One of these has to be called on a new object */
	
	/**
	 * Initialise this object from a database row.
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
			$user = User::newFromName( $row->ltv_user_ip, false );
		}
		
		if ( is_null($user) ) {
			throw new MWException( "Invalid user found in ltv_user: {$row->ltv_user_id}/{$row->ltv_user_ip}" );
		}
		$this->versionUser = $user;
		
		// Other metadata
		$this->comment = $row->ltv_comment;
		$this->timestamp = wfTimestamp( TS_MW, $row->ltv_timestamp );
		$this->topicID = $row->ltv_topic;
		
		// Real version data loading
		$this->channelID = $row->ltv_channel;
		$this->subject = $row->ltv_subject;
		
		$this->summaryTextID = $row->ltv_summary_text_id;
	}
	
	/**
	 * Initialise a new version object for a LiquidThreadsTopic.
	 * If the base revision is not specified, it is based on the current version of the topic.
	 * @param $topic LiquidThreadsTopic: The topic that this version is for.
	 * @param $baseVersion LiquidThreadsTopicVersion: The base version for this version. \em{(optional)}
	 */
	protected function initialiseNew( LiquidThreadsTopic $topic, $baseVersion = null )
	{
		if ( ! $baseVersion ) {
			$baseVersion = $topic->getCurrentVersion();
		}
		
		// Copy all data members across.
		$this->channelID = $baseVersion->getChannelID();
		$this->subject = $baseVersion->getSubject();
		
		$this->summaryText = $baseVersion->getSummaryText();
		$this->summaryTextDirty = false;
		$this->summaryTextID = $baseVersion->summaryTextID;
		
		global $wgUser;
		$this->versionUser = $wgUser;
		
		$this->id = 0;
		$this->topicID = $topic->getID();
	}
	
	/**
	 * Initialise a new version object for a new topic.
	 * @param $topic The LiquidThreadsTopic being created
	 * @param $channel LiquidThreadsChannel: The channel that this topic is in.
	 */
	protected function initialiseNewTopic( LiquidThreadsTopic $topic,
		LiquidThreadsChannel $channel )
	{
		global $wgUser;
		
		$this->id = 0;
		$this->versionUser = $wgUser;
		$this->channelID = $channel->getID();
		$this->subject = '';
		$this->topicID = 0; // Filled later
		$this->summaryTextID = 0;
		$this->summaryTextRow = null;
		$this->summaryTextDirty = true;
		$this->summaryText = '';
		$this->topic = $topic;
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
	public function setSubject( $subject ) {
		$this->subject = $subject;
	}
	
	/**
	 * Set the summary text of this version.
	 * @param $newtext String: The new summary text for this version.
	 */
	public function setSummaryText( $newtext ) {
		if ( !$this->isMutable() ) {
			throw new MWException( "This Version object is not mutable." );
		}
		
		$this->summaryText = $newtext;
		$this->summaryTextDirty = true;
		$this->summaryTextID = $this->summaryTextRow = null;
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
		
		$this->timestamp = $row['ltv_timestamp'];
		
		if ( $this->summaryTextDirty && $this->summaryText != '' ) {
			$this->summaryTextID = LiquidThreadsObject::saveText($this->summaryText);
			$this->summaryTextDirty = false;
		} elseif ( $this->summaryText == '' ) {
			$this->summaryTextID = 0;
			$this->summaryTextDirty = false;
		}
		
		$row['ltv_summary_text_id'] = $this->summaryTextID;
		
		// Poster and user data
		$editor = $this->getEditor();
		
		if ( $editor->isAnon() ) {
			$row['ltv_user_ip'] = $editor->getName();
		} else {
			$row['ltv_user_id'] = $editor->getID();
		}
		
		$dbw->insert( 'lqt_topic_version', $row, __METHOD__ );
		
		$this->id = $dbw->insertId();
		
		if ( $this->getTopicID() == 0 ) {
			$this->getTopic()->insert( $this );
		} else {
			$this->getTopic()->update( $this );
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
	
	/**
	 * @return The ID of the topic that this version is of.
	 */
	public function getTopicID() {
		return $this->topicID;
	}
	
	/**
	 * @return The LiquidThreadsTopic that this version is of.
	 */
	public function getTopic() {
		$id = $this->getTopicID();
		if ( ! $this->topic ) {
			if ( ! $id ) {
				throw new MWException( "This Topic Version is not associated with a topic" );
			}
			
			$this->topic = LiquidThreadsTopic::newFromID( $id );
		}
		
		return $this->topic;
	}
	
	/**
	 * Lets you set the topic ID, once.
	 * Only valid use is from LiquidThreadsTopic::save(), for a new LiquidThreadsTopic
	 * @param $id Integer: The topic ID that this version applies to.
	 */
	public function setTopicID( $id ) {
		if ( $this->topicID ) {
			throw new MWException( "Topic ID is already set" );
		}
		
		$this->topicID = $id;
		
		$dbw = wfGetDB( DB_MASTER );
		$dbw->update( 'lqt_topic_version', array( 'ltv_topic' => $id ),
				array( 'ltv_id' => $this->getID() ),
				__METHOD__ );
	}
	
	/**
	 * Retrieves the summary text associated with this Post Version.
	 * @return The summary text if available, or false if none exists.
	 */
	public function getSummaryText() {
		if ( !is_null( $this->summaryText ) ) {
			// Already cached
			return $this->summaryText;
		} elseif ( 0 && !is_null( $this->summaryTextURL ) ) {
			// Not implemented
		} elseif ( !is_null( $this->summaryTextRow ) ) {
			$this->summaryText = Revision::getRevisionText( $this->summaryTextRow );
			return $this->summaryText;
		} elseif ( !is_null( $this->summaryTextID ) ) {
			$dbr = wfGetDB( DB_MASTER );
			
			$row = $dbr->selectRow( 'text', '*',
				array( 'old_id' => $this->summaryTextID ), __METHOD__ );
			
			if ( $row ) {
				$this->summaryText = Revision::getRevisionText( $row );
				return $this->summaryText;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}
	
	/**
	 * Gets a globally unique (for all objects) identifier for this object
	 * @return String
	 */
	public function getUniqueIdentifier() {
		return 'lqt-topic-version:'.$this->getID();
	}
}
