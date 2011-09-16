<?php

abstract class LiquidThreadsObject {
	/**
	 * Returns a unique identifier for this object instance.
	 * It should be unique for this class.
	 * You should return NULL or zero if this instance does not have any
	 * unique identifier.
	 */
	public abstract function getID();
	
	/**
	 * Saves the text to the text table (or external storage).
	 * @param $data String: The text to save to the text table.
	 * @return Integer: an ID for the text table.
	 */
	public static function saveText( $data ) {
		if ( $data === null ) {
			throw new MWException( "Attempt to save NULL text" );
		}
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
	
	/**
	 * Gets a globally unique (for all objects) identifier for this object
	 * @return String
	 */
	abstract public function getUniqueIdentifier();
	
	/**
	 * Retrieves an object by unique identifier
	 * @param $id String: Unique identifier returned by getUniqueIdentifier()
	 * @return LiquidThreadsObject
	 */
	public static function retrieve($id) {
		static $classes = array(
			'lqt-post' => 'LiquidThreadsPost',
			'lqt-topic' => 'LiquidThreadsTopic',
			'lqt-channel' => 'LiquidThreadsChannel',
		);
		
		list($type, $id) = explode('_', $id);
		
		$class = $classes[$type];
		
		return $class::newFromID( $id );
	}
}
