<?php

namespace larry\updates;

use larry\Context;
use larry\model\User;

/**
 * An incoming message from a user to the bot.
 *
 * @package larry\updates
 */
class Message extends Update {

	/**
	 * Creates a new message.
	 *
	 * @param   string  $update  The JSON of the update.
	 */
	public function __construct( string $update ) {
		parent::__construct( $update, 'message' );
		if ( $this->is_valid()
		     && ( ! array_key_exists( 'from', $this->content )
		          || ! array_key_exists( 'chat', $this->content )
		          || ! array_key_exists( 'id', $this->content['from'] )
		          || ! array_key_exists( 'first_name',
					$this->content['from'] )
		          || ! array_key_exists( 'id', $this->content['chat'] ) ) ) {
			$this->invalidate();
		}
	}

	/**
	 * Creates the corresponding JSON for a message, mainly for testing reasons.
	 *
	 * @param   User    $user     The user of interest
	 * @param   string  $message  The message.
	 * @param   int     $id       The id of the message.
	 *
	 * @return string The message.
	 */
	public static function generate_json(
		User $user,
		string $message,
		int $id = 0
	): string {
		return json_encode( array(
			'update_id' => $id,
			'message'   => array(
				'from' => array(
					'id'         => $user->id(),
					'first_name' => $user->name(),
				),
				'text' => $message,
				'chat' => array(
					'id' => $user->chat_id(),
				),
			),
		) );
	}


	/**
	 * Query the sender of the message.
	 *
	 * @param   Context  $config  The runtime context.
	 *
	 * @return User The sender.
	 */
	public function sender( Context $config ): User {
		return new User( $config->database(),
			$this->content['from']['id'],
			$this->content['from']['first_name'],
			$this->content['chat']['id'] );
	}

	/**
	 * Split the text at its whitespace into tokens.
	 *
	 * @param   bool  $to_lowercase  Map all tokens to their lowercase version.
	 *
	 * @return string[] An array of tokens without any whitespace.
	 */
	public function tokens( bool $to_lowercase ): array {
		$data = array_key_exists( 'text', $this->content )
			? $this->content['text'] : '';

		$tokens = preg_split( '/\s+/', $data, - 1, PREG_SPLIT_NO_EMPTY );
		if ( $to_lowercase ) {
			$tokens = array_map( function ( string $x ) {
				return strtolower( $x );
			},
				$tokens );
		}

		return $tokens;
	}
}