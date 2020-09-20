<?php


namespace larry\api;

/**
 * A public API to access Larry.
 *
 * @package larry\api
 */
abstract class Api {
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
			$response = new Response( 401 );
			$response->add_header( 'WWW-Authenticate: Basic realm="Please enter a valid API key."' );

			return $response;
		}

		// Extract the API of interest
		$api_name = $request->get( INPUT_GET, "api" );
		if ( $api_name === null ) {
			return new Response( 400 );
		}

		// Check for the HTTP method
		$method = $request->get( INPUT_SERVER, 'REQUEST_METHOD' );
		// ToDo: Support HEAD
		if ( $method !== 'GET' and $method !== 'POST' ) {
			return new Response( 405 );
		}

		// Extract the ID
		$id = $request->filter( INPUT_GET, "id", FILTER_VALIDATE_INT );
		if ( $id === false ) {
			return new Response( 400 );
		}

		foreach ( $apis as $api ) {
			if ( $api_name !== $api->api_id() ) {
				continue;
			}

			if ( $method === 'GET' ) {
				return $api->handle_get( $request, $id );
			} elseif ( $method === 'POST' ) {
				if ( $id === null ) {
					return new Response( 400 );
				}

				$key   = $request->get( INPUT_POST, "attribute" );
				$value = $request->get( INPUT_POST, "value" );
				if ( ! is_null( $value ) ) {
					$value = @json_decode( $value );
				}
				if ( is_null( $key ) or is_null( $value ) ) {
					return new Response( 400 );
				}

				return $api->handle_post( $request, $id, $key, $value );
			}
		}

		return new Response( 404 );
	}
}