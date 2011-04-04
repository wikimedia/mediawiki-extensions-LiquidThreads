<?php

/**
 * Top level class for objects which have versions.
 */
abstract class LiquidThreadsVersionedObject extends LiquidThreadsObject {
// 
// 	/** The current LiquidThreadsVersionObject. **/
// 	protected $currentVersion;
// 	/** The ID of the current version **/
// 	protected $currentVersionID;
// 	
// 	/** The version that is being worked on by set methods **/
// 	protected $pendingVersion;
// 
// 	/**
// 	 * @return String: The name of the class that version objects have.
// 	 */
// 	abstract public static function getVersionClass();
// 	
// 	/**
// 	 * @return String: The name of the database field that stores the current version ID
// 	 */
// 	abstract public static function getVersionField();
	
}

/**
 * Top-level class for version objects.
 */
abstract class LiquidThreadsVersionObject extends LiquidThreadsObject {
	
}
