<?php

/**
 * This is the abstract base class for a LiquidThreads "Formatter".
 * A formatter takes a LiquidThreads object and converts it to HTML for display.
 */
abstract class LiquidThreadsFormatter {
	
	/**
	 * Convert an object to HTML.
	 * @param $object Object: The object to convert.
	 * @param $context LiquidThreadsFormatterContext object appropriate to this class.
	 *  See subclass documentation for valid fields.
	 * @return String: HTML representing this object, for display to users.
	 */
	abstract public function getHTML( $object, $context = null );
	
	/**
	 * Checks arguments to getHTML. Throws an exception if there is a problem.
	 * @param $object The object being formatted.
	 * @param $context The context object.
	 */
	protected function checkArguments( &$object, &$context ) {
		if ( $context == null ) {
			$contextClass = $this->getContextClass();
			$context = new $contextClass;
		} elseif ( ! is_a( $context, $this->getContextClass() ) ) {
			throw new MWException( "Invalid context argument" );
		}
		
		if ( ! is_a( $object, $this->getObjectClass() ) ) {
			throw new MWException( "Invalid object for this formatter" );
		}
	}
	
	/**
	 * Runs the callback hooks in the $context object.
	 * @param $object The LiquidThreadsObject to run hooks for.
	 * @param $contest The LiquidThreadsFormatterContext object.
	 * @return Similar to a MediaWiki hook:
	 * true if no callback is run and the method should continue as usual.
	 * false if a callback was run and no further processing is necessary.
	 */
	protected function runCallback( $object, $context ) {
		$callbacks = $context->get('post-callbacks');
		
		if ( !is_array($callbacks) ) {
			return true;
		}
		
		if ( isset( $callbacks[$object->getUniqueIdentifier()] ) ) {
			$callback = $callbacks[$object->getUniqueIdentifier()];
			call_user_func_array( $callback, array( $object, $context ) );
			return false;
		} else {
			return true;
		}
	}
	
	/**
	 * Returns the correct class for the context object
	 */
	abstract public function getContextClass();
	
	/**
	 * Returns the class that can be formatted by this object.
	 */
	abstract public function getObjectClass();
	
	/**
	 * Returns whether or not this class can format the given object.
	 */
	public function canFormat( $object ) {
		return is_a( $object, $this->getObjectClass() );
	}
	
	/**
	 * Generates an anchor name for a LiquidThreads object
	 * @param $object Mixed: A LiquidThreadsObject.
	 */
	public static function getAnchor( $object, $type = null ) {
		if ( $object instanceof LiquidThreadsObject ) {
			return Sanitizer::escapeId( $object->getUniqueIdentifier() );
		} else {
			throw new MWException( "Invalid argument to getAnchor()" );
		}
	}
}

abstract class LiquidThreadsFormatterContext {
	protected $data;
	protected $requiredFields = array();
	protected $validFields = array();
	
	/**
	 * Returns true if the field name is valid for this context class.
	 * @param $field The name of the field.
	 * @return Boolean: True if the field name is valid, false otherwise.
	 */
	protected function isValidField( $field ) {
		return in_array( $field, $this->validFields );
	}
	
	/**
	 * Returns the value of a field, or null if it is not set.
	 * @param $field String: The field to retrieve.
	 * @return Mixed: The value of the field.
	 */
	public function get( $field ) {
		if ( ! $this->isValidField( $field ) ) {
			throw new MWException( "Attempt to retrieve invalid item" );
		}
		
		if ( isset( $this->data[$field] ) ) {
			return $this->data[$field];
		} else {
			return null;
		}
	}
	
	/**
	 * Sets the value of a field.
	 * @param $field String: The field to set.
	 * @param $value Mixed: The new value for the field.
	 */
	public function set( $field, $value ) {
		if ( ! $this->isValidField( $field ) ) {
			throw new MWException( "Attempt to set invalid item" );
		}
		
		$this->data[$field] = $value;
	}
	
	/**
	 * Increments a field.
	 * @param $field String: The field to increment.
	 */
	public function increment( $field ) {
		$this->set( $field, $this->get($field) + 1);
	}
	
	/**
	 * Decrements a field.
	 * @param $field String: The field to decrement.
	 */
	public function decrement( $field ) {
		$this->set( $field, $this->get($field) - 1);
	}
	
	/**
	 * Gets the action for a given object.
	 * @param $object LiquidThreadsObject: The object to search for.
	 * @return String: Either an action or false.
	 */
	public function getActionFor( $object ) {
		if ( $this->isValidField('action') && $this->get( 'action' ) ) {
			$actionStruct = $this->get('action');
			$match = $actionStruct[1] == $object->getUniqueIdentifier();
			
			if ( count($actionStruct) > 0 && $match ) {
				return $actionStruct[0];
			}
		} elseif ( $this->isValidField('parent-context') && $this->get('parent-context') ) {
			return $this->get('parent-context')->getActionFor($object);
		}
		
		return false;
	}
}
