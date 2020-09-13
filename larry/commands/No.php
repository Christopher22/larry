<?php


namespace larry\commands;


use DateTimeImmutable;

use larry\Context;
use larry\model\Availability;
use larry\model\Meeting;
use larry\model\User;
use larry\updates\Message;

class No extends DateCommand {

	public const SUCCESS_MESSAGE = "%s: ❌";
	public const CHANGE_NOTIFICATION = "%s will not attend the meeting at %s 😢";

	/**
	 * @inheritDoc
	 */
	public function name(): string {
		return '/no';
	}

	/**
	 * @inheritDoc
	 */
	public function description(): string {
		return 'Indicate that you will be absent at a specific meeting. Example: /no 11.11.2020';
	}

	protected function notify_other(
		Context $context,
		User $sending_user,
		Meeting $missed_meeting
	) {
		$users = User::load_all( $context->database() );
		foreach ( $users as $user ) {
			// Do not send the message to the same user.
			if ( $user->id() === $sending_user->id() ) {
				continue;
			}

			( new Result( sprintf( self::CHANGE_NOTIFICATION,
				$sending_user->name(),
				$missed_meeting->date()->format( 'd.m.Y' ) ), true )
			)->send( $context, $user, false );
		}
	}

	protected function execute_date(
		Context $context,
		Message $message,
		DateTimeImmutable $date
	): Result {
		$meeting          = new Meeting( $context->database(),
			$date,
			false );
		$user             = $message->sender( $context );
		$old_availability = $meeting->availability( $user );

		// Try to save the meeting to the database
		$availability = new Availability( $user, false );
		if ( $meeting->set_availability( $availability ) ) {
			// Notify others the user is missing
			if ( $old_availability !== null
			     && $old_availability->is_available() === true ) {
				$this->notify_other( $context, $user, $meeting );
			}

			return new Result(
				sprintf( self::SUCCESS_MESSAGE, $date->format( 'd.m.Y' ) ),
				true );
		} else {
			return new Result( self::ERROR_MESSAGE, false );
		}
	}
}