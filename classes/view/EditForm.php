<?php

/**
 * This is an abstract class for all of the different types of edit forms that can
 * be shown for creating or editing a post (or a new topic).
 */
abstract class LiquidThreadsEditForm {

	protected $user;

	/**
	 * Constructor
	 * @param $user User: The user viewing this form.
	 */
	public function __construct( $user ) {
		$this->user = $user;
	}
	
	/**
	 * Shows the form, and submits it if necessary
	 * @param $request Web request
	 * @return String: HTML
	 */
	public function show( $request = null ) {
		if ( ! $request ) {
			global $wgRequest;
			$request = $wgRequest;
		}
		
		$tokenMatch = $this->user->matchEditToken( $request->getVal('edittoken') );
		
		if ( $request->wasPosted() && $tokenMatch ) {
			$result = $this->validate( $request );
			
			if ( $result === true ) {
				$result = $this->submit( $request );
				
				if ( $result ) {
					return $result;
				}
			} elseif ( $result !== false ) {
				return $result;
			}
		}
		
		return $this->getFormHTML();
	}
	
	/**
	 * Runs form submission
	 * @param $request: The WebRequest to submit.
	 * @return boolean: False to show the rest of the form, else HTML result
	 */
	abstract public function submit( $request = null );
	
	/**
	 * Validates form fields

	/**
	 * Gets the HTML of the form, in edit mode.
	 * @return String: HTML
	 */
	public function getFormHTML() {
		$fields = $this->getFormFieldsHTML();
		$buttons = $this->getButtons();
		$hidden = $this->getHiddenFields();
		$hiddenHTML = '';
		
		$buttons = join( "\n", $buttons );
		
		foreach( $hidden as $key => $value ) {
			$hiddenHTML .= Html::hidden( $key, $value );
		}
		
		$innerHTML = Html::rawElement('div',
				array('class' => 'lqt-edit-fields'),
				$fields );
		$innerHTML .= Html::rawElement('div',
				array('class' => 'lqt-edit-buttons'),
				$buttons );
		$innerHTML .= $hiddenHTML;
		
		$html = Html::rawElement( 'form',
				array(
					'class' => 'lqt-edit-form',
					'action' => '',
					'method' => 'post',
				), $innerHTML );
				
		return $html;
	}
	
	/**
	 * Gets the HTML for the form fields, excluding buttons.
	 */
	protected abstract function getFormFieldsHTML();
	
	/**
	 * Gets the hidden fields for this form
	 * @return Associative array of hidden field names and values.
	 */
	protected function getHiddenFields() {
		$fields = array();
		
		$fields['edittoken'] = $this->user->getEditToken();
		
		// TODO Testing
		global $wgTitle;
		$fields['title'] = $wgTitle->getPrefixedText();
		$action = LqtDispatch::getAction();
		
		if ( count($action) ) {
			$fields['lqt_action'] = $action[0];
		}
		
		if ( count($action) > 1 ) {
			$fields['lqt_target'] = $action[1];
		}
		
		return $fields;
	}
	
	/**
	 * Gets the buttons to show at the bottom.
	 * @return Array of Strings: Each entry is HTML for a button.
	 */
	protected function getButtons() {
		$buttons = array();
		
		$buttons[] = Html::input( 'save', wfMsg('savearticle'), 'submit',
				array(
					'class' => 'lqt-save',
				) );
				
		return $buttons;
	}
	
	/**
	 * Generates a textbox used to edit the subject line of a post.
	 * @param $subject String: The existing subject to preload with.
	 * @return String: HTML
	 */
	protected function getSubjectEditor( $subject = '' ) {
		$subject_label = wfMsg( 'lqt_subject' );

		return Xml::inputLabel( $subject_label, 'lqt-subject',
				'lqt-subject', 60, $subject ) .
			Xml::element( 'br' );
	}

	/**
	 * Generates an editing textbox.
	 * @param $content String: The content to put in the textbox.
	 * @return String: HTML
	 */
	protected function getTextbox( $name, $content = '' ) {
		$html = Html::textarea( $name, $content,
				array(
					'class' => 'lqt-editform-textbox',
					'rows' => 10,
					'cols' => 50,
				) );
				
		return $html;
	}
	
	/**
	 * Generates the signature portion of the edit form.
	 * @param $signature The existing signature to preload in the form.
	 * @return String: HTML
	 **/
	protected function getSignatureEditor( $signatureText = '' ) {
		// Signature edit box
		
		$signatureHTML = LiquidThreadsPostFormatter::parseSignature($signatureText);
		
		$signaturePreview = Html::rawElement(
			'span',
			array(
				'class' => 'lqt-signature-preview',
				'style' => 'display: none;'
			),
			$signatureHTML
		);
		$signatureEditBox = Xml::input(
			'lqt-signature', 45, $signatureText,
			array( 'class' => 'lqt-signature-edit' )
		);

		$signatureEditor = $signaturePreview . $signatureEditBox;
		
		$signatureEditor = Html::rawElement( 'div',
					array( 'class' => 'lqt-signature-editor' ),
					$signatureEditor );

		return $signatureEditor;
	}
	
	
	/**
	 * Call after submit() to get the object that you created or modified
	 * @return LiquidThreadsObject: The object created/modified
	 */
	public abstract function getModifiedObject();
}
