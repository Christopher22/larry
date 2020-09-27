<?php


namespace larry\api;

use DateInterval;
use DateTimeImmutable;
use larry\model\Availability;
use larry\model\Meeting;
use larry\model\User;

class Availabilities extends Api {

	public const DATE = 'date';
	public const AVAILABILITIES = 'availabilities';
	public const MIN_DATE = 'first';

	/**
	 * @inheritDoc
	 */
	public function api_id(): string {
		return 'availabilities';
	}

	/**
	 * @inheritDoc
	 */
	protected function handle_get( Request $request, ?int $id ): Response {
		$response = array();
		$database = $request->context()->database();
		if ( $id === null ) {
			// By default, meeting older than one day are excluded.
			$first_meeting = $request->filter( INPUT_GET,
				self::MIN_DATE,
				FILTER_VALIDATE_INT
			);
			if ( $first_meeting === false ) {
				return Response::from_error(
					400,
					'First valid date is not a UNIX timestamp'
				);
			} elseif ( $first_meeting === null ) {
				$first_meeting = new Meeting(
					$database,
					( new DateTimeImmutable() )->sub( new DateInterval( 'P1D' ) )
				);
			} else {
				$first_meeting = Meeting::from_timestamp(
					$database,
					$first_meeting
				);
			}

			// Load all meetings
			$meetings = Meeting::load_all(
				$database,
				$first_meeting );

			// Create the serialized availability data
			foreach ( $meetings as $meeting ) {
				$response[ $meeting->date()->getTimestamp() ]
					= $this->serialize_meeting( $meeting );
			}
		} else {
			$meeting  = Meeting::from_timestamp(
				$database,
				$id
			);
			$response = $this->serialize_meeting( $meeting );
		}

		return new Response( 200, $response );
	}

	/**
	 * @inheritDoc
	 */
	protected function handle_post(
		Request $request,
		int $id,
		string $key,
		$value
	): Response {
		if ( $value !== null && ! is_bool( $value ) ) {
			return Response::from_error( 400, 'Invalid availability' );
		}

		$user_id = filter_var( $key, FILTER_VALIDATE_INT );
		if ( $user_id === false ) {
			return Response::from_error( 400, 'Invalid user id' );
		}

		$user = User::load( $request->context()->database(), $user_id );
		if ( count( $user ) === 0 ) {
			return Response::from_error( 400, 'User not found' );
		}

		$availability = new Availability( $user[0], $value );
		$meeting      = Meeting::from_timestamp(
			$request->context()->database(),
			$id
		);
		if ( ! $meeting->set_availability( $availability ) ) {
			return Response::from_error( 400, 'Unable to set availability' );
		}

		return new Response( 200 );
	}

	private function serialize_meeting( Meeting $meeting ): array {
		$raw_availabilities = $meeting->availabilities( true );
		$availabilities     = array();
		foreach ( $raw_availabilities as $availability ) {
			$availabilities[ $availability->user()->id() ]
				= $availability->is_available();
		}

		return array(
			self::DATE           => $meeting->date()->format( DATE_ISO8601 ),
			self::AVAILABILITIES => $availabilities,
		);
	}
}