<?php


namespace larry\commands;


use DateTimeImmutable;

use larry\Context;
use larry\model\Meeting;
use larry\updates\Message;

class Ask extends DateCommand {

	public const QUESTION = 'Hey %s, %s asks if you are available on %s. Please reply with /yes or /no 🙃';
	public const CONFIRMATION = "I asked\n%s\n for their availability on %s. ☺";

	/**
	 * @inheritDoc
	 */
	public function name(): string {
		return '/ask';
	}

	/**
	 * @inheritDoc
	 */
	public function description(): string {
		return 'Ask all the users without a response regarding their availability at a specific date.';
	}

	protected function execute_date(
		Context $context,
		Message $message,
		DateTimeImmutable $date
	): Result {
		$meeting       = new Meeting( $context->database(), $date );
		$no_responders = $meeting->unknown_availabilities();

		$sender_name = $message->sender( $context )->name();
		$date_string = $date->format( 'd.m.Y' );
		foreach ( $no_responders as $user ) {
			( new Result( sprintf( self::QUESTION,
				$user->name(),
				$sender_name,
				$date_string ), true )
			)->send( $context, $user, true );
		}

		return new Result(
			sprintf( self::CONFIRMATION,
				implode( "\n",
					array_map( function ( $user ) {
						return "- {$user->name()}";
					},
						$no_responders ) ),
				$date_string ),
			true );
	}
}