<?php


namespace larry\commands;


use DateTimeImmutable;

use larry\Context;
use larry\model\Availability;
use larry\model\Meeting;
use larry\updates\Message;

class Yes extends DateCommand {

	private const SUCCESS_MESSAGE = "%s: ✔";

	/**
	 * @inheritDoc
	 */
	public function name(): string {
		return '/yes';
	}

	/**
	 * @inheritDoc
	 */
	public function description(): string {
		return 'Indicate that you will be present at a specific meeting. Example: /yes 11.11.2020';
	}

	protected function execute_date(
		Context $context,
		Message $message,
		DateTimeImmutable $date
	): Result {
		$meeting      = new Meeting( $context->database(),
			$date,
			false );
		$availability = new Availability( $message->sender( $context ), true );

		// Try to save the meeting to the database
		if ( $meeting->set_availability( $availability ) ) {
			return new Result(
				sprintf( self::SUCCESS_MESSAGE, $date->format( 'd.m.Y' ) ),
				true );
		} else {
			return new Result( self::ERROR_MESSAGE, false );
		}
	}
}