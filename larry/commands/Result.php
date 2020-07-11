<?php


namespace larry\commands;

use larry\Context;
use larry\model\User;
use larry\requests\SendMessage;

/**
 * The result of an operation
 *
 * @package larry\commands
 */
class Result {
	private ?string $message;
	private bool $success;

	private static $sending_implementation = null;

	/**
	 * Create a new result with an message and a status.
	 *
	 * @param   string  $message     The message.
	 * @param   bool    $successful  The status of the corresponding command.
	 */
	public function __construct( string $message, bool $successful ) {
		$this->message = $message;
		$this->success = $successful;
	}

	/**
	 * The result of the command.
	 *
	 * @return bool TRUE, if the command was executed successfully.
	 */
	public function is_successful(): bool {
		return $this->success;
	}

	/**
	 * Check if the result should be communicated to the user.
	 *
	 * @return bool TRUE, if it should be communicated.
	 */
	public function should_respond(): bool {
		return ! empty( $this->message );
	}

	/**
	 * Sends the result to a user.
	 *
	 * @param   Context  $context          The context.
	 * @param   User     $target           The user.
	 * @param   bool     $expect_response  Indicates that a response is expected.
	 *
	 * @return bool TRUE on success.
	 */
	public function send(
		Context $context,
		User $target,
		bool $expect_response = false
	): bool {
		if ( ! $this->should_respond() ) {
			return true;
		}

		if ( self::$sending_implementation === null ) {
			$message = new SendMessage( $context,
				$target,
				$this->message,
				$expect_response );

			return $message->is_valid();
		} else {
			return call_user_func( self::$sending_implementation,
				$this,
				$context,
				$target,
				$expect_response );
		}
	}

	/**
	 * Overwrite the default sending implementation with a custom one, i.e. for testing.
	 *
	 * @param   callable|null  $implementation  The implementation.
	 */
	public static function overwrite_send( ?callable $implementation ) {
		self::$sending_implementation = $implementation;
	}

	public function __toString(): string {
		return $this->message;
	}
}