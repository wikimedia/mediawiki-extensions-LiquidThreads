<?php

/**
 * This class represents a single version of a specific Post.
 */
class LiquidThreadsPostVersion {
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
	
	/** User object for the original poster of this comment **/
	protected $poster;
	
	/** Text ID -- get the row from the text table **/
	protected $textID = null;
	
	/** Text row -- use Revision::getRevisionText to convert to text **/
	protected $textRow = null;
	
	/** Text ES URL -- convert to text by retrieving from ES,
	 * substituting into $textRow and  calling Revision::getRevisionText **/
	protected $textURL = null;
	
	/** Actual text **/
	protected $text = null;
	
	/** Whether or not the text has been modified directly in $text
	 * (and therefore needs to be saved to ES). **/
	protected $textDirty = false;
	
	/** The ID of the topic that this post is in **/
	protected $topicID = null;
	
	/** The ID of the parent post for this post, if applicable **/
	protected $parentID = null;
	
	/** The signature attached to this post **/
	protected $signature = null;
	
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
	 * Factory method to retrieve PostVersions from Database conditions.
	 * Don't use this method externally, use one of the other factory methods.
	 * @param $conditions Array: Conditions to pass to Database::select
	 * @return Array: LiquidThreadsPostVersion objects with those conditions.
	 */
	public static function loadFromConditions( $conditions, $fetchText = false, $options = array() ) {
		$dbr = wfGetDB( DB_SLAVE );
		
		$tables = array( 'lqt_post_version' );
		$fields = '*';
		
		$res = $dbr->select( $tables, $fields, $conditions, __METHOD__, array(), $joins );
		
		$output = array();
		
		foreach( $res as $row ) {
			$version = new LiquidThreadsPostVersion;
			$version->initialiseFromRow( $row );
			
			$output[$version->getID()] = $version;
		}
		
		return $output;
	}
	
	/**
	 * Factory method to retrieve a PostVersion from a Post and point in time.
	 * If the point in time is blank, it retrieves the most recent one.
	 * @param $post LiquidThreadsPost: The Post to retrieve a version for.
	 * @param $timestamp String: A timestamp for the point in time (optional)
	 * @return LiquidThreadsPostVersion: The version for that post at that point in time, or null.
	 */
	public static function newPointInTime( $post, $timestamp = null ) {
		if ( ! $post instanceof LiquidThreadsPost ) {
			throw new MWException( "Invalid argument to ".__METHOD__ );
		}
		
		$dbr = wfGetDB( DB_SLAVE );
		
		$conds = array( 'lpv_post' => $post->getID() );
		
		if ( $timestamp ) {
			$conds[] = 'lpv_timestamp < ' .
				$dbr->addQuotes( $dbr->timestamp( $timestamp ) );
		}
		
		$row = $dbr->selectRow( 'lqt_post_version', '*', $conds, __METHOD__ );
		
		if ( $row ) {
			return self::newFromRow( $row );
		} else {
			return null;
		}
	}
	
	/**
	 * Factory method to retrieve a PostVersion by ID.
	 * Throws an exception if the version does not exist.
	 * @param $id Integer: The ID of the version to retrieve.
	 * @return LiquidThreadsPostVersion: The Version with that ID.
	 */
	public static function newFromID( $id ) {
		$condition = array( 'lpv_id' => $id );
		
		$versions = self::loadFromConditions( $condition );
		
		// Check that the version actually exists.
		if ( count($versions) < 1 ) {
			throw new MWException( "Attempt to load post version #$id, which does not exist" );
		}
		
		$version = array_shift( $versions );
		return $version;
	}
	
	/**
	 * Factory method to load a version from a row object.
	 * The row object may optionally contain the appropriate text data, too.
	 * @param $row Object: Row object returned from DatabaseResult::fetchObject()
	 * @return LiquidThreadsPostVersion: The new Post object.
	 */
	public static function newFromRow( $row ) {
		$version = new LiquidThreadsPostVersion;
		$post->initialiseFromRow( $row );
		
		return $version;
	}
	
	/**
	 * Factory method to create a new version of a post.
	 * @param $post LiquidThreadsPost: The Post to create a version of.
	 * @param $baseVersion LiquidThreadsPostVersion: The revision to base on. \em{(optional)}
	 * @return LiquidThreadsPostVersion: The new version object.
	 */
	public static function create( LiquidThreadsPost $post,
			LiquidThreadsPostVersion $baseVersion = null )
	{
		$version = new LiquidThreadsPostVersion;
		
		$version->initialiseNew( $post, $baseVersion );
		
		return $version;
	}
	
	/**
	 * Factory method to create a Version for a new Post.
	 * @return LiquidThreadsPostVersion: A Version object for a new post.
	 */
	public static function createNewPost( LiquidThreadsTopic $topic,
			LiquidThreadsPost $parent = null )
	{
		$post = new LiquidThreadsPostVersion;
		
		$post->initialiseNewPost( $topic, $parent );
		
		return $post;
	}
	
	/* Initialisation functions. One of these has to be called on a new object */
	
	/**
	 * Initialise this object from a database row.
	 * This "row" may be joined to the text row.
	 * @param $row Object: A row object containing the
	 * appropriate lqt_post_version and (optionally) text rows.
	 */
	protected function initialiseFromRow( $row ) {
		if ( empty($row->lpv_id) ) {
			throw new MWException( "Invalid input to ".__METHOD__ );
		}
		
		// Load members
		$this->id = $row->lpv_id;

		// Metadata members
		$user = null;
		if ( $row->lpv_user_id > 0 ) {
			if ( empty($row->user_name) ) {
				$user = User::newFromId( $row->lpv_user_id );
			} else {
				$user = User::newFromRow( $row );
			}
		} elseif ( User::isIP( $row->lpv_user_ip ) ) {
			$user = User::newFromName( $row->lpv_user_ip );
		}
		
		if ( is_null($user) ) {
			throw new MWException( "Invalid user found in lpv_user: {$row->lpv_user_id}/{$row->lpv_user_ip}" );
		}
		$this->versionUser = $user;
		
		// Other metadata
		$this->comment = $row->lpv_comment
		$this->timestamp = wfTimestamp( TS_MW, $row->lpv_timestamp );
		$this->postID = $row->lpv_post;
		
		// Real version data loading
		$user = null;
		if ( $row->lpv_poster_id > 0 ) {
			if ( empty($row->user_name) ) {
				$user = User::newFromId( $row->lpv_poster_id );
			} else {
				$user = User::newFromRow( $row );
			}
		} elseif ( User::isIP( $row->lpv_poster_ip ) ) {
			$user = User::newFromName( $row->lpv_poster_ip );
		}
		
		if ( is_null($user) ) {
			throw new MWException( "Invalid user found in lpv_user: {$row->lpv_poster_id}/{$row->lpv_poster_ip}" );
		}
		
		$this->poster = $user;
		
		$this->textID = $row->lpv_text_id;
		if ( isset($row->old_id) ) {
			$this->textRow = $row;
		}
		
		$this->topicID = $row->lpv_topic;
		
		$this->parentID = $row->lpv_parent_post;
		
		$this->signature = $row->lpv_signature;
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
		$this->text = $baseVersion->getText();
		$this->textDirty = false;
		$this->textID = $baseVersion->textiD;
		$this->poster = $baseVersion->getPoster();
		$this->topicID = $baseVersion->getTopicID();
		$this->parentID = $baseVersion->getParentID();
		$this->signature = $baseVersion->getSignature();
		
		global $wgUser;
		$this->editor = $wgUser;
		
		$this->id = 0;
		$this->postID = $post->getID();
	}
	
	/**
	 * Initialise a new version object for a new post.
	 * @param $topic LiquidThreadsTopic: The topic that this post is in.
	 * @param $parent LiquidThreadsPost: (Optional) A parent Post for this one.
	 */
	protected function initialiseNewPost( LiquidThreadsTopic $topic,
			LiquidThreadsPost $parent )
	{
		global $wgUser;
		
		$this->id = 0;
		$this->poster = $wgUser;
		$this->versionUser = $wgUser;
		$this->postID = 0;
		$this->textID = 0;
		$this->textRow = null;
		$this->textDirty = true;
		$this->topicID = $topic->getID();
		$this->parentID = $parent->getID();
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
	 * Set the text of this version.
	 * @param $newtext String: The new text for this version.
	 */
	public function setText( $newtext ) {
		if ( !$this->isMutable() ) {
			throw new MWException( "This Version object is not mutable." );
		}
		
		$this->text = $newtext;
		$this->textDirty = true;
		$this->textID = $this->textRow = null;
	}
	
	/**
	 * Set the "poster" for this post.
	 * @param $newPoster User: The user to attribute this post to.
	 */
	public function setPoster( User $newPoster ) {
		if ( !$this->isMutable() ) {
			throw new MWException( "This Version object is not mutable." );
		}
		
		$this->poster = $newPoster;
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
	 * Set the parent topic for this version.
	 * @param $topic LiquidThreadsTopic: The new parent topic.
	 */
	public function setTopic( LiquidThreadsTopic $topic ) {
		$this->topicID = $topic->getID();
	}
	
	/**
	 * Set the parent post for this version.
	 * @param $post LiquidThreadsPost: The new parent post.
	 */
	public function setParentPost( LiquidThreadsPost $post ) {
		$this->parentID = $post->getID();
	}
	
	/**
	 * Sets the signature associated with this version.
	 * @param $signature String: The new signature
	 */
	public function setSignature( $signature ) {
		$this->signature = $signature;
	}
	
	/**
	 * Saves this Version to the database.
	 * @param $comment String: (optional) The edit comment for this version.
	 */
	public function commit( $comment = null ) {
		if ( $this->id > 0 ) {
			throw new MWException( "Attempt to save a version already in the database." );
		}
		
		if ( $comment !== null ) {
			$this->comment = $comment;
		}
		
		$dbw = wfGetDB( DB_MASTER );
		
		$row = array(
			'lpv_id' => $dbw->nextSequenceValue( 'lqt_post_version_lpv_id' ),
			'lpv_post' => $this->postID,
			'lpv_timestamp' => $dbw->timestamp( wfTimestampNow() ),
			'lpv_comment' => $this->comment,
			'lpv_topic' => $this->
		);
		
		if ( $this->textDirty ) {
			$this->textID = self::saveText($this->text);
			$this->textDirty = false;
		}
		
		if ( $this->textID == 0 ) {
			throw new MWException( "Unable to store revision text" );
		}
		
		$row['lpv_text_id'] = $this->textID;
		
		// Poster and user data
		$poster = $this->getPoster();
		$editor = $this->getEditor();
		
		if ( $poster->isAnon() ) {
			$row['lpv_poster_ip'] = $poster->getName();
		} else {
			$row['lpv_poster_id'] = $poster->getID();
		}
		
		if ( $editor->isAnon() ) {
			$row['lpv_user_ip'] = $editor->getName();
		} else {
			$row['lpv_user_id'] = $editor->getID();
		}
		
		$dbw->insert( 'lqt_post_version', $row, __METHOD__ );
		
		$this->id = $dbw->insertId();
		
		// Update pointer
		$dbw->update( 'lqt_post', array( 'lqp_current_version', $this->id ),
				array( 'lqp_id' => $this->postID ), __METHOD__ );
	}
	
	/**
	 * Saves the text to the text table (or external storage).
	 * @param $data String: The text to save to the text table.
	 * @return Integer: an ID for the text table.
	 */
	protected static function saveText( $data ) {
		global $wgDefaultExternalStore;
		
		$dbw = wfGetDB( DB_MASTER );
		
		$flags = Revision::compressRevisionText( $data );

		# Write to external storage if required
		if( $wgDefaultExternalStore ) {
			// Store and get the URL
			$data = ExternalStore::insertToDefault( $data );
			
			if( !$data ) {
				throw new MWException( "Unable to store text to external storage" );
			}
			
			if( $flags ) {
				$flags .= ',';
			}
			$flags .= 'external';
		}

		# Record the text (or external storage URL) to the text table
		$old_id = $dbw->nextSequenceValue( 'text_old_id_seq' );
		
		$dbw->insert( 'text',
			array(
				'old_id'    => $old_id,
				'old_text'  => $data,
				'old_flags' => $flags,
			), __METHOD__
		);
		
		return $dbw->insertId();
	}

	/* PROPERTY ACCESSORS */
	
	/**
	 * Returns the unique ID assigned to this Post Versions. This ID is unique among Post Versions.
	 */
	public function getID() {
		if ( ! $this->id ) {
			throw new MWException( "This Post Version does not have an ID" );
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
	 * Returns the user to which this comment is attributed
	 */
	public function getPoster() {
		if ( ! $this->poster || ! $this->poster instanceof User ) {
			throw new MWException( "Missing or invalid poster" );
		}
		return $this->poster;
	}
	
	/**
	 * Retrieves the text associated with this Post Version.
	 */
	public function getText() {
		if ( !is_null( $this->text ) ) {
			// Already cached
			return $this->text;
		} elseif ( 0 && !is_null( $this->textURL ) ) {
			// Not implemented
		} elseif ( !is_null( $this->textRow ) ) {
			$this->text = Revision::getRevisionText( $this->textRow );
			return $this->text;
		} elseif ( !is_null( $this->textID ) ) {
			$dbr = wfGetDB( DB_MASTER );
			
			$row = $dbr->selectRow( 'text', '*',
				array( 'old_id' => $this->textID ), __METHOD__ );
			
			if ( $row ) {
				$this->text = Revision::getRevisionText( $row );
				return $this->text;
			} else {
				throw new MWException( "Unable to load text #{$this->textID}" );
			}
		} else {
			throw new MWException( "Unable to load revision text: none found." );
		}
	}
	
	/**
	 * Retrieves the ID of the parent topic associated with this Post Version.
	 */
	public function getTopicID() {
		return $this->topicID;
	}
	
	/**
	 * Retrieves the ID of the parent post, if any, associated with this Post Version.
	 */
	public function getParentID() {
		return $this->parentID;
	}
	
	/**
	 * Retrieves the signature shown for this post.
	 */
	public function getSignature() {
		return $this->signature;
	}
}
