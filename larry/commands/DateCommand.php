<?php


namespace larry\commands;

use DateTime;
use DateTimeImmutable;

use larry\Context;
use larry\updates\Message;

abstract class DateCommand extends Command {

	protected const INVALID_ARGUMENT = "Sorry, I do not understand your message. Please try again with something like: %s 22.04.1997";
	protected const ERROR_MESSAGE = "I was unable to safe your response. Please ask my boss...";

	protected abstract function execute_date(
		Context $context,
		Message $message,
		DateTimeImmutable $date
	): Result;

	public static function parse_date( string $user_input
	): ?DateTimeImmutable {
		if ( preg_match( '/(?P<day>\d{1,2})\.\s*(?P<month>\d{1,2})(\.\s*(?P<year>\d{4}))?/',
				$user_input,
				$matches ) !== 1 ) {
			return null;
		}

		$date = new DateTime();
		$date->setTime( 0, 0 );
		if ( $date->setDate(
				array_key_exists( 'year', $matches ) ? $matches['year']
					: date( "Y" ),
				$matches['month'],
				$matches['day']
			) === false ) {
			return null;
		}

		return DateTimeImmutable::createFromMutable( $date );
	}

	/**
	 * @inheritDoc
	 */
	public final function execute(
		Context $context,
		Message $message,
		string ...$parameters
	): Result {

		$date = count( $parameters ) === 1
			? self::parse_date( $parameters[0] )
			: null;

		if ( $date === null ) {
			return new Result(
				sprintf( self::INVALID_ARGUMENT, $this->name() ),
				false
			);
		}

		return $this->execute_date( $context, $message, $date );
	}
}