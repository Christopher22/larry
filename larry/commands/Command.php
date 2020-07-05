<?php


namespace larry\commands;


use larry\Context;
use larry\updates\Message;

/**
 * A command which can be parsed from the bot.
 *
 * @package larry\commands
 */
abstract class Command {

	/**
	 * @return string The name of the command.
	 */
	public abstract function name(): string;

	/**
	 * @return string The detailed description of the command.
	 */
	public abstract function description(): string;

	/**
	 * Executes the command with the given arguments.
	 *
	 * @param   Context  $context        The runtime context.
	 * @param   string   ...$parameters  The arguments.
	 *
	 * @return Result The status of the command.
	 */
	public abstract function execute(
		Context $context,
		string ...$parameters
	): Result;

	/**
	 * Checks if the function is documented in public, indicated by '/' at the beginning.
	 *
	 * @return bool TRUE if it is documented.
	 */
	public function is_public(): bool {
		return substr( $this->name(), 0, 1 ) === '/';
	}

	public function __toString(): string {
		return "{$this->name()}: {$this->description()}";
	}

	/**
	 * Parses a message and runs all the commands in it.
	 *
	 * @param   Context  $context   The runtime context.
	 * @param   Message  $message   The message.
	 * @param   array    $commands  The commands available.
	 *
	 * @return Result[] The results of the operation.
	 */
	public static function parse(
		Context $context,
		Message $message,
		array $commands
	): array {
		// Get all the command names
		$command_names = array_map( function ( $command ) {
			return $command->name();
		},
			$commands
		);

		$results   = array();
		$command   = null;
		$arguments = array();
		foreach ( $message->tokens( false ) as $token ) {
			$command_index = array_search(
				strtolower( $token ),
				$command_names,
				true
			);

			// Check if the token is a valid command
			if ( $command_index !== false ) {
				// Execute the last command
				if ( $command !== null ) {
					$results[] = $command->execute( $context, ...$arguments );
				}
				$command   = $commands[ $command_index ];
				$arguments = array();
			} else {
				$arguments[] = $token;
			}
		}

		// Execute the last command, if present.
		if ( $command !== null ) {
			$results[] = $command->execute( $context, ...$arguments );
		}

		return $results;
	}
}