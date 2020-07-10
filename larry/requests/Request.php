<?php

namespace larry\requests;

use larry\Context;

class Request {
	public array $response;

	function __construct( Context $context, string $command, array $data ) {
		$url = $context->api_url( $command );
		if ( count( $data ) > 0 ) {
			$parameters = http_build_query( $data );
			$url        = "$url?$parameters";
		}

		$result = file_get_contents( $url, false );
		if ( $result === false ) {
			$this->response = array( 'ok' => false );
		} else {
			$this->response = json_decode( $result, true );
			if ( ! is_array( $this->response ) ) {
				$this->response = array( 'ok' => false );
			}
		}
	}

	/**
	 * Checks if the command executed successfully.
	 *
	 * @return bool TRUE if the was an success.
	 */
	function is_valid(): bool {
		return $this->response['ok'] === true;
	}
}