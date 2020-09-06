<?php


namespace larry\commands;


use DateTimeImmutable;

use larry\Context;
use larry\model\Meeting;
use larry\updates\Message;

class Summary extends DateCommand {

	public const NOBODY = 'Nobody said anything... 🤨';

	/**
	 * @inheritDoc
	 */
	public function name(): string {
		return '/summary';
	}

	/**
	 * @inheritDoc
	 */
	public function description(): string {
		return 'Shows the availability at a specific date.';
	}

	protected function execute_date(
		Context $context,
		Message $message,
		DateTimeImmutable $date
	): Result {
		$meeting        = new Meeting( $context->database(), $date );
		$availabilities = $meeting->availabilities( true );

		// Count numbers of known responses
		$num_known = 0;
		foreach ( $availabilities as $availability ) {
			$num_known += $availability->is_available() !== null ? 1 : 0;
		}

		if ( $num_known > 0 ) {
			array_unshift( $availabilities,
				"Meeting on {$date->format('d.m.Y')}:" );
			$result = implode( "\n", $availabilities );
		} else {
			$result = self::NOBODY;
		}

		return new Result( $result, true );
	}
}