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
	
	/** The Post that this version applies to **/
	protected $post;
	
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
	
	/** Actual text, parsed to HTML **/
	protected $contentHTML = null;
	
	/** Whether or not the text has been modified directly in $text
	 * (and therefore needs to be saved to ES). **/
	protected $textDirty = false;
	
	/** The ID of the topic that this post is in **/
	protected $topicID = null;
	
	/** The ID of the parent post for this post, if applicable **/
	protected $parentID = null;
	
	/** The signature attached to this post **/
	protected $signature = null;
	
	/** Attributed timestamp for this post **/
	protected $postTime = null;
	
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
	 * Throws an exception on failure.
	 * @param $post LiquidThreadsPost: The Post to retrieve a version for.
	 * @param $timestamp String: A timestamp for the point in time (optional)
	 * @return LiquidThreadsPostVersion: The version for that post at that point in time.
	 */
	public static function newPointInTime( $post, $timestamp = null ) {
		if ( ! $post instanceof LiquidThreadsPost ) {
			throw new MWException( "Invalid argument to ".__METHOD__ );
		}
		
		if ( $timestamp == null ) {
			return $post->getCurrentVersion();
		}
		
		$dbr = wfGetDB( DB_SLAVE );
		
		$conds = array( 'lpv_post' => $post->getID() );
		
		if ( $timestamp ) {
			$conds[] = 'lpv_timestamp < ' .
				$dbr->addQuotes( $dbr->timestamp( $timestamp ) );
		}
		
		$row = $dbr->selectRow( 'lqt_post_version', '*', $conds, __METHOD__,
				array( 'ORDER BY' => 'lpv_timestamp DESC' ) );
		
		if ( $row ) {
			$obj = self::newFromRow( $row );
			$obj->setPost( $post );
			return $obj;
		} else {
			throw new MWException( "No version available at this point in time" );
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
		$version->initialiseFromRow( $row );
		
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
	 * @param $post LiquidThreadsPost: The new post.
	 * @param $topic LiquidThreadsTopic: The topic that this post is in.
	 * @param $parent LiquidThreadsPost: (Optional) A parent Post for this one.
	 * @return LiquidThreadsPostVersion: A Version object for a new post.
	 */
	public static function createNewPost( LiquidThreadsPost $post,
		LiquidThreadsTopic $topic,
		$parent = null
	) {
		$version = new LiquidThreadsPostVersion;
		
		$version->initialiseNewPost( $post, $topic, $parent );
		
		return $version;
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
			$user = User::newFromName( $row->lpv_user_ip, false );
		}
		
		if ( is_null($user) ) {
			throw new MWException( "Invalid user found in lpv_user: {$row->lpv_user_id}/{$row->lpv_user_ip}" );
		}
		$this->versionUser = $user;
		
		// Other metadata
		$this->comment = $row->lpv_comment;
		$this->timestamp = wfTimestamp( TS_MW, $row->lpv_timestamp );
		$this->postID = $row->lpv_post;
		$this->postTime = $row->lpv_post_time;
		
		// Real version data loading
		$user = null;
		if ( $row->lpv_poster_id > 0 ) {
			if ( empty($row->user_name) ) {
				$user = User::newFromId( $row->lpv_poster_id );
			} else {
				$user = User::newFromRow( $row );
			}
		} elseif ( User::isIP( $row->lpv_poster_ip ) ) {
			$user = User::newFromName( $row->lpv_poster_ip, false );
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
		$this->textID = $baseVersion->textID;
		$this->poster = $baseVersion->getPoster();
		$this->topicID = $baseVersion->getTopicID();
		$this->parentID = $baseVersion->getParentID();
		$this->signature = $baseVersion->getSignature();
		$this->postTime = $baseVersion->getPostTime();
		
		global $wgUser;
		$this->versionUser = $wgUser;
		
		$this->id = 0;
		$this->postID = $post->getID();
	}
	
	/**
	 * Initialise a new version object for a new post.
	 * @param $post LiquidThreadsPost: The new post.
	 * @param $topic LiquidThreadsTopic: The topic that this post is in.
	 * @param $parent LiquidThreadsPost: (Optional) A parent Post for this one.
	 */
	protected function initialiseNewPost( LiquidThreadsPost $post,
			LiquidThreadsTopic $topic,
			$parent = null
	) {
		global $wgUser;
		
		$this->id = 0;
		$this->poster = $wgUser;
		$this->versionUser = $wgUser;
		$this->post = $post;
		$this->postID = 0; // Filled later
		$this->textID = 0;
		$this->textRow = null;
		$this->textDirty = true;
		$this->topicID = $topic->getID();
		$this->signature = '';
		$this->postTime = wfTimestampNow();
		
		if ( $parent ) {
			$this->parentID = $parent->getID();
		} else {
			$this->parentID = null;
		}
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
	 * @return true if this is the current version of the post, false otherwise.
	 */
	public function isCurrent() {
		$post = $this->getPost();
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
	 * Sets the timestamp attributed to the post in this version.
	 * @param $timestamp The new timestamp.
	 */
	public function setPostTime( $timestamp ) {
		$this->postTime = $timestamp;
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
			'lpv_topic' => $this->topicID,
			'lpv_signature' => $this->signature,
			'lpv_post_time' => $this->postTime,
			'lpv_parent_post' => $this->parentID,
		);
		
		$this->timestamp = $row['lpv_timestamp'];
		
		if ( $this->textDirty ) {
			$this->textID = LiquidThreadsObject::saveText($this->text);
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
		
		if ( $this->getPostID() == 0 ) {
			$this->getPost()->insert($this);
		} else {
			$this->getPost()->update($this);
		}
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
	 * Gets the parsed HTML for this Post Version's text.
	 */
	public function getContentHTML() {
		if ( !is_null($this->contentHTML) ) {
			return $this->contentHTML;
		}
		
		if ( !$this->isMutable() ) {	
			global $wgMemc;
			
			$memcKey = wfMemcKey( 'lqt', 'post-version', $this->getID(), 'html' );
			
			$html = $wgMemc->get( $memcKey );
			if ( $html ) {
				$this->contentHTML = $html;
				return $html;
			}
		}
		
		global $wgParser, $wgOut;

		$html = $wgOut->parse( $this->getText() );
		$this->contentHTML = $html;
		
		if ( ! $this->isMutable() ) {
			// 7 day cache
			$wgMemc->set( $memcKey, $html, 86400 * 7 );
		}
		
		return $html;
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
	
	/**
	 * Retrieves the timestamp attributed to the post in this version.
	 */
	public function getPostTime() {
		return $this->postTime;
	}
	
	/**
	 * Gets the ID of the post.
	 * @return The Post ID that this version is for.
	 */
	public function getPostID() {
		return $this->postID;
	}
	
	/**
	 * Gets the post that this version is for.
	 * @return The LiquidThreadsPost that this version applies to.
	 */
	public function getPost() {
		if ( is_null($this->post) ) {
			$this->post = LiquidThreadsPost::newFromID( $this->getPostID() );
		}
		
		return $this->post;
	}
	
	/**
	 * Lets you set the post ID, once.
	 * Only valid use is from LiquidThreadsPost::save(), for a new LiquidThreadsPost
	 * @param $id Integer: The post ID that this version applies to.
	 */
	public function setPostID( $id ) {
		if ( $this->postID ) {
			throw new MWException( "Post ID is already set" );
		}
		
		$this->postID = $id;
		
		$dbw = wfGetDB( DB_MASTER );
		$dbw->update( 'lqt_post_version', array( 'lpv_post' => $id ),
				array( 'lpv_id' => $this->getID() ),
				__METHOD__ );
	}
	
	/**
	 * Lets you provide the Post object to save loading
	 * Validity checking *is* done.
	 */
	public function setPost( $post ) {
		if ( $post->getID() != $this->getPostID() ) {
			throw new MWException( "Invalid argument to ".__METHOD__ );
		}
		
		$this->post = $post;
	}
	
	/**
	 * Gets a globally unique (for all objects) identifier for this object
	 * @return String
	 */
	public function getUniqueIdentifier() {
		return 'lqt-post-version:'.$this->getID();
	}
}
