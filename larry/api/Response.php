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

	/**
	 * Create a new response with a status code and an optional payload.
	 *
	 * @param   int    $status_code  The HTTP status code.
	 * @param   array  $response     The payload.
	 */
	public function __construct( int $status_code, array $response = array() ) {
		$this->code     = $status_code;
		$this->response = $response;
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
		http_response_code( $this->code );
		if ( count( $this->response ) !== 0 ) {
			$content = json_encode( $this->response );
			header( "Content-Type: application/json" );
			fwrite( STDOUT, $content );
		}
	}
}