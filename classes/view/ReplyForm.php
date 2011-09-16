<?php

/**
 * Form for posting a new reply to a topic
 */
class LiquidThreadsReplyForm extends LiquidThreadsEditForm {

	protected $topic;
	protected $object;

	/**
	 * Initialises a LiquidThreadsNewTopicForm.
	 * @param $user The user viewing this form.
	 * @param $topic The topic to reply to
	 * @param $replyPost The post to reply to, or NULL
	 */
	public function __construct( $user, $topic, $replyPost = null ) {
		parent::__construct( $user );
		
		if ( ! $topic instanceof LiquidThreadsTopic ) {
			throw new MWException( "Invalid argument to ".__METHOD__ );
		}
		$this->topic = $topic;
		$this->replyPost = $replyPost;
	}

	/**
	 * Gets the HTML for the form fields, excluding buttons.
	 */
	protected function getFormFieldsHTML() {
		$html = '';
		
		$html .= $this->getTextbox('lqt-edit-content');
		$html .= $this->getSignatureEditor( LqtView::getUserSignature($this->user) );
		
		return $html;
	}
	
	public function submit( $request = null ) {
		$text = $request->getVal('lqt-edit-content');
		$sig = $request->getVal('lqt-signature');
		
		// Now add the first post
		$post = LiquidThreadsPost::create( $this->topic, $this->replyPost );
		$post->getPendingVersion()->setEditor( $this->user );
		$post->getPendingVersion()->setPoster( $this->user );
		$post->setText( $text );
		$post->setSignature( $sig );
		
		$post->save();
		
		$this->object = $post;
		
		return true;
	}
	
	public function validate( $request = null ) {
		if ( ! $request->getVal( 'lqt-edit-content' ) ) {
			return false;
		}
		
		return true;
	}
	
	public function getModifiedObject() {
		return $this->object;
	}
}
