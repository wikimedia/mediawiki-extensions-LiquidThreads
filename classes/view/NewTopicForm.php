<?php

/**
 * Form for posting a new topic to a Channel
 */
class LiquidThreadsNewTopicForm extends LiquidThreadsEditForm {

	protected $channel;
	protected $object;

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
		$html .= $this->getSignatureEditor( LqtView::getUserSignature($this->user) );
		
		return $html;
	}
	
	public function submit( $request = null ) {
		$subject = $request->getVal('lqt-subject');
		$text = $request->getVal('lqt-edit-content');
		$sig = $request->getVal('lqt-signature');
		
		// Set up the topic
		$topic = LiquidThreadsTopic::create( $this->channel );
		$topic->setSubject( $subject );
		$topic->getPendingVersion()->setEditor( $this->user );
		$topic->save();
		
		// Now add the first post
		$post = LiquidThreadsPost::create( $topic );
		$post->getPendingVersion()->setEditor( $this->user );
		$post->getPendingVersion()->setPoster( $this->user );
		$post->setText( $text );
		$post->setSignature( $sig );
		
		$post->save();
		
		$this->object = $topic;
		
		return true;
	}
	
	public function validate( $request = null ) {
		if ( ! $request->getVal('lqt-subject') ) {
			return false;
		}
		
		if ( ! $request->getVal( 'lqt-edit-content' ) ) {
			return false;
		}
		
		return true;
	}
	
	public function getModifiedObject() {
		return $this->object;
	}
}
