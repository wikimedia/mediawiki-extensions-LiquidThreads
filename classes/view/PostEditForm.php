<?php

/**
 * LiquidThreadsEditForm to edit a post's contents.
 */
class LiquidThreadsPostEditForm extends LiquidThreadsEditForm {
	protected $post;

	/**
	 * Initialises a LiquidThreadsPostEditForm.
	 * @param $user The user viewing this form.
	 * @param $post The post to be edited.
	 */
	public function __construct( $user, $post ) {
		parent::__construct( $user );
		
		if ( ! $post instanceof LiquidThreadsPost ) {
			throw new MWException( "Invalid argument to ".__METHOD__ );
		}
		$this->post = $post;
	}
	
	/**
	 * Gets the HTML for the form fields, excluding buttons.
	 */
	protected function getFormFieldsHTML() {
		$html = '';
		
		$html .= $this->getTextbox('lqt-edit-content', $this->post->getText() );
		$html .= $this->getSignatureEditor( $this->post->getSignature() );
		$html .= $this->getSummaryBox();
		
		return $html;
	}
	
	/**
	 * Returns a textbox used to enter the summary for this change.
	 */
	protected function getSummaryBox() {
		$label = wfMsg( 'summary' );
		return Xml::inputLabel( $label, 'lqt-summary',
				'lqt-summary', 60 ) .
			Xml::element( 'br' );
	}
	
	/**
	 * Submits the form
	 */
	public function submit( $request = null ) {
		$text = $request->getVal('lqt-edit-content');
		$sig = $request->getVal('lqt-signature');
		$summary = $request->getVal('lqt-summary');
		
		$this->post->setText( $text );
		$this->post->setSignature( $sig );
		$this->post->save( $summary );
		
		return true;
	}
	
	public function validate( $request = null ) {
		return true;
	}
	
	public function getModifiedObject() {
		return $this->post;
	}
}
