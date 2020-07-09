<?php


namespace larry\commands;


use larry\Context;
use larry\updates\Message;

class Start extends Command {

	private const WELCOME_MESSAGE = 'Hey %s! Nice to see you. 😘';
	private const AGAIN_MESSAGE = 'We already talked, right?! 🤔';
	private const ERROR_MESSAGE = 'I was unable to add you to the club. Please talk to my boss.';

	/**
	 * @inheritDoc
	 */
	public function name(): string {
		return '/start';
	}

	/**
	 * @inheritDoc
	 */
	public function description(): string {
		return 'Starts a new communication with the bot.';
	}

	/**
	 * @inheritDoc
	 */
	public function execute(
		Context $context,
		Message $message,
		string ...$parameters
	): Result {
		if ( count( $parameters ) !== 1
		     || $context->is_user_allowed( $parameters[0] ) === false ) {
			return new Result( '', false );
		}

		$sender = $message->sender( $context );
		if ( $sender->exists() ) {
			return new Result( self::AGAIN_MESSAGE, true );
		} elseif ( $sender->create() ) {
			return new Result( sprintf( self::WELCOME_MESSAGE,
				$sender->name() ), true );
		} else {
			return new Result( self::ERROR_MESSAGE, false );
		}
	}
}