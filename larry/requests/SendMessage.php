<?php


namespace larry\requests;


use larry\Context;
use larry\model\User;

class SendMessage extends Request {
	public function __construct(
		Context $context,
		User $user,
		string $message,
		bool $force_reply = false
	) {
		$request = array(
			'chat_id' => $user->chat_id(),
			'text'    => $message,
		);

		// Handle 'reply_markup'
		if ( $force_reply === true ) {
			$request['reply_markup'] = json_encode( array(
				'force_reply' => true,
			) );
		}

		parent::__construct( $context,
			'sendMessage',
			$request );
	}
}