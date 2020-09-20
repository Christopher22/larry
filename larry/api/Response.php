<?php


namespace larry\api;


/**
 * The response sent back to the user.
 *
 * @package larry\api
 */
class Response {
	private int $code;
	private array $response;
	private array $headers;

	/**
	 * Create a new response with a status code and an optional payload.
	 *
	 * @param   int    $status_code  The HTTP status code.
	 * @param   array  $response     The payload.
	 */
	public function __construct( int $status_code, array $response = array() ) {
		$this->code     = $status_code;
		$this->response = $response;
		$this->headers  = array();
	}

	/**
	 * Add a header which is send with the response.
	 *
	 * @param   string  $header  The HTTP header.
	 */
	public function add_header( string $header ) {
		$this->headers[] = $header;
	}

	/**
	 * Return a array of headers send to the client.
	 *
	 * @return array Headers.
	 */
	public function headers(): array {
		return $this->headers;
	}

	/**
	 * Return the status code.
	 *
	 * @return int The HTTP status code.
	 */
	public function status(): int {
		return $this->code;
	}

	/**
	 * The payload, which will be transmitted as a JSON value.
	 *
	 * @return array The payload.
	 */
	public function payload(): array {
		return $this->response;
	}

	/**
	 * Check if the request was successful.
	 *
	 * @return bool True, if the request was successful.
	 */
	public function is_successful(): bool {
		return $this->code === 200;
	}

	/**
	 * Send the response to the client.
	 */
	public function send() {
		// Set the HTTP response code
		http_response_code( $this->code );

		// Send all the headers
		foreach ( $this->headers as $header ) {
			header( $header );
		}

		// Send the serialized payload, iff available
		if ( count( $this->response ) !== 0 ) {
			$content = json_encode( $this->response );
			header( "Content-Type: application/json" );
			print( $content );
		}
	}
}