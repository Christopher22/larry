<?php


namespace larry\requests;


use larry\Context;
use larry\model\User;

class SendMessage extends Request {
	public function __construct(
		Context $context,
		User $user,
		string $message
	) {
		parent::__construct( $context,
			'sendMessage',
			array(
				'chat_id' => $user->chat_id(),
				'text'    => $message,
			) );
	}
}