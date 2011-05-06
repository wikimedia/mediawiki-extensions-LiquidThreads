<?php

/**
 * Form for posting a new topic to a Channel
 */
class LiquidThreadsNewTopicForm extends LiquidThreadsEditForm {

	protected $channel;

	/**
	 * Initialises a LiquidThreadsNewTopicForm.
	 * @param $user The user viewing this form.
	 * @param $channel The channel to allow posting topics to.
	 */
	public function __construct( $user, $channel ) {
		parent::__construct( $user );
		
		if ( ! $channel instanceof LiquidThreadsChannel ) {
			throw new MWException( "Invalid argument to ".__METHOD__ );
		}
		$this->channel = $channel;
	}

	/**
	 * Gets the HTML for the form fields, excluding buttons.
	 */
	protected function getFormFieldsHTML() {
		$html = '';
		
		$html .= $this->getSubjectEditor();
		$html .= $this->getTextbox('lqt-edit-content');
		$html .= $this->getSignatureEditor();
		
		return $html;
	}
}
