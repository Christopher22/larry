<?php


namespace larry\requests;


use larry\commands\Command;
use larry\Context;

class SetCommands extends Request {

	/**
	 * A request for registering some commands to Telegram
	 *
	 * @param   Context    $context   The context of the operation.
	 * @param   Command[]  $commands  The commands.
	 */
	public function __construct(
		Context $context,
		array $commands
	) {
		$encoded_commands = json_encode( array_map(
			function ( $command ) {
				return array(
					'command'     => $command->name(),
					'description' => $command->description(),
				);
			},
			$commands ) );

		parent::__construct( $context,
			'setMyCommands',
			array( $encoded_commands ) );
	}
}