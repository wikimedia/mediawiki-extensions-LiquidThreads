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
}
