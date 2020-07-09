<?php


namespace larry\commands;

/**
 * The result of an operation
 *
 * @package larry\commands
 */
class Result {
	private ?string $message;
	private bool $success;

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

	public function __toString(): string {
		return $this->message;
	}
}