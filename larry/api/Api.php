<?php


namespace larry\api;

use JsonException;

/**
 * A public API to access Larry.
 *
 * @package larry\api
 */
abstract class Api {
	public const KEY_NAME = 'attribute';
	public const VALUE_NAME = 'value';

	/**
	 * The entry point used to access the API.
	 *
	 * @return string The name of the entry point.
	 */
	public abstract function api_id(): string;

	function version(): int {
		return 1;
	}

	/**
	 * Process the otherwise validated request.
	 *
	 * @param   Request   $request  The request.
	 * @param   int|null  $id       The id of the entity. If it is null, all entities are queried.
	 *
	 * @return Response The response to the request.
	 */
	protected abstract function handle_get(
		Request $request,
		?int $id
	): Response;

	/**
	 * Process a update request to an entity with a specific id.
	 *
	 * @param   Request  $request  The request.
	 * @param   int      $id       The ID of the entity.
	 * @param   string   $key      The key of the property to change.
	 * @param   mixed    $value    The new value of the property.
	 *
	 * @return Response The response to the request.
	 */
	protected abstract function handle_post(
		Request $request,
		int $id,
		string $key,
		$value
	): Response;

	/**
	 * Parse a request with the available APIs.
	 *
	 * @param   Request  $request  The request.
	 * @param   array    $apis     The available APIs.
	 *
	 * @return Response The corresponding response to the request.
	 */
	public static function parse( Request $request, array $apis ): Response {
		// Check the password of the request
		if ( ! $request->is_allowed() ) {
			$response = Response::from_error( 401, 'Authentication invalid' );
			$response->add_header( 'WWW-Authenticate: Basic realm="Please enter a valid API key."' );

			return $response;
		}

		// Extract the API of interest
		$api_name = $request->get( INPUT_GET, 'api' );
		if ( $api_name === null ) {
			return Response::from_error( 400, 'API not specified' );
		}

		// Check for the HTTP method
		$method = $request->get( INPUT_SERVER, 'REQUEST_METHOD' );
		// ToDo: Support HEAD
		if ( $method !== 'GET' and $method !== 'POST' ) {
			return Response::from_error( 405,
				'The API does not support this method' );
		}

		// Extract the ID
		$id = $request->filter( INPUT_GET, "id", FILTER_VALIDATE_INT );
		if ( $id === false ) {
			return Response::from_error( 400, 'ID is not a valid integer' );
		}

		foreach ( $apis as $api ) {
			if ( $api_name !== $api->api_id() ) {
				continue;
			}

			if ( $method === 'GET' ) {
				return $api->handle_get( $request, $id );
			} elseif ( $method === 'POST' ) {
				$key   = $request->get( INPUT_POST, self::KEY_NAME );
				$value = $request->get( INPUT_POST, self::VALUE_NAME );
				if ( $id === null || $key === null || $value === null ) {
					return Response::from_error( 400,
						'Id, key, or value missing' );
				}

				// Try to parse the value as JSON
				try {
					$value = json_decode( $value, JSON_THROW_ON_ERROR );
				}
				catch ( JsonException $ex ) {
					return Response::from_error( 400, 'Value is invalid JSON' );
				}

				return $api->handle_post( $request, $id, $key, $value );
			}
		}

		return Response::from_error( 404, 'API not found' );
	}
}