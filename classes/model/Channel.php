<?php

class LiquidThreadsChannel extends LiquidThreadsObject {

	/** The ID of this channel **/
	protected $id;
	
	/** The Title that this Channel points to **/
	protected $title;

	/**
	 * Do not call directly, used internally
	 */
	protected function __construct() {
		
	}
	
	/**
	 * Create a new LiquidThreadsChannel object.
	 * Will throw an exception if there is already a Channel at this location.
	 * @param $title The Title to point this channel at.
	 * @return A new LiquidThreadsChannel object.
	 */
	public static function create( $title ) {
		$obj = new LiquidThreadsChannel;
		
		$obj->initialiseNew( $title );
		
		return $obj;
	}
	
	/**
	 * Initialise this object as a new LiquidThreadsChannel.
	 * @param $title Title to point to.
	 */
	protected function initialiseNew( $title ) {
		$this->title = $title;
		
		$this->insert();
	}
	
	/**
	 * Add this new object to the database.
	 */
	protected function insert() {
		$row = $this->getRow();
		$dbw = wfGetDB( DB_MASTER );
		
		$dbw->insert( 'lqt_channel', $row, __METHOD__ );
		
		$this->id = $dbw->insertId();
	}
	
	/**
	 * Retrieve a LiquidThreadsChannel object from its unique ID.
	 * Throws an exception if no object is found.
	 * @param $id Integer: The ID to retrieve
	 * @return The LiquidThreadsChannel object.
	 */
	public static function newFromID( $id ) {
		$dbr = wfGetDB( DB_SLAVE );
		
		$row = $dbr->selectRow( 'lqt_channel', '*', array( 'lqc_id' => intval($id) ),
					__METHOD__ );
		
		$channel = self::loadFromRow( $row );
		
		if ( $channel == null ) {
			throw new MWException( "Request for channel by ID returned no results" );
		}
		
		return $channel;
	}
	
	/**
	 * Retrieve a LiquidThreadsChannel object from the title it points to.
	 * Throws an exception if no object is found.
	 * @param $title The Title of the Channel to retrieve
	 * @return The LiquidThreadsChannel object.
	 */
	public static function newFromTitle( $title ) {
		$dbr = wfGetDB( DB_SLAVE );
		
		$row = $dbr->selectRow( 'lqt_channel', '*',
					array( 'lqc_page_namespace' => $title->getNamespace(),
						'lqc_page_title' => $title->getDBkey() ),
					__METHOD__ );
		
		$channel = self::loadFromRow( $row );
		
		if ( $channel == null ) {
			throw new MWException( "Request for channel by title returned  no results" );
		}
		
		return $channel;
	}
	
	/**
	 * Load a set of LiquidThreadsChannel objects from a result wrapper
	 * @param $res ResultWrapper: A database result wrapper containing the rows to load.
	 * @return Array of LiquidThreadsChannel objects.
	 */
	public static function loadFromResult( $res ) {
		$output = array();
		
		foreach( $res as $input ) {
			$output[$input->lqc_id] = self::loadFromRow( $input );
		}
		
		return $output;
	}
	
	/**
	 * Load a LiquidThreadsChannel object from a database row object.
	 * @param $row Object: Output from Database::selectRow or ResultWrapper::fetchObject
	 * @return The new LiquidThreadsChannel object
	 */
	public static function loadFromRow( $row ) {
		$obj = new LiquidThreadsChannel;
		$obj->initialiseFromRow( $row );
		
		return $obj;
	}
	
	/**
	 * Initialise a LiquidThreadsChannel object from a database row object.
	 * @param $row Object: Output from Database::selectRow or ResultWrapper::fetchObject
	 */
	protected function initialiseFromRow( $row ) {
		$this->id = $row->lqc_id;
		$this->title = Title::makeTitleSafe( $row->lqc_page_namespace, $row->lqc_page_title );
	}

	/**
	 * Returns an ID unique among LiquidThreadsChannel objects
	 * @return Integer: An ID.
	 */
	public function getID() {
		return $this->id;
	}
	
	/**
	 * Returns the Title that this channel points to.
	 * @return Title object.
	 */
	public function getTitle() {
		if ( is_null($this->title) ) {
			throw new MWException( "Missing Title" );
		}
		return $this->title;
	}
	
	/**
	 * Changes the Title that this channel points to.
	 * @param $title The new Title to point this Channel to.
	 */
	public function setTitle( $title ) {
		$this->title = $title;
		
		$this->save();
	}
	
	/**
	 * Saves changes to the database.
	 */
	protected function save() {
		$row = $this->getRow();
		
		$dbw = wfGetDB( DB_MASTER );
		
		$dbw->update( 'lqt_channel', $row, array( 'lqc_id' => $this->getID() ), __METHOD__ );
	}
	
	/**
	 * Retrieves a row to be saved to the database
	 * @return Row in Array for to be saved to the database.
	 */
	protected function getRow() {
		$dbw = wfGetDB( DB_MASTER );
		
		$row = array(
			'lqc_id' => $this->getID(),
			'lqc_page_namespace' => $this->getTitle()->getNamespace(),
			'lqc_page_title' => $this->getTitle()->getDBkey(),
		);
		
		if ( !$row['lqc_id'] ) {
			$row['lqc_id'] = $dbw->nextSequenceValue( 'lqt_channel_lqc_id' );
		}
		
		return $row;
	}
	
	/**
	 * Gets a globally unique (for all objects) identifier for this object
	 * @return String
	 */
	public function getUniqueIdentifier() {
		return 'lqt-channel_'.$this->getID();
	}
}
