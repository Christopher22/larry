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

		// This contexts allows the parsing of the body even if the request status code was non-200.
		$context = stream_context_create( array(
			'http' => array(
				'ignore_errors' => true,
			),
		) );
		$result  = @file_get_contents( $url, false, $context );
		if ( $result === false ) {
			$this->response = array(
				'ok'          => false,
				'description' => 'Connection to API failed',
			);
		} else {
			$this->response = json_decode( $result, true );
			if ( ! is_array( $this->response ) ) {
				$this->response = array(
					'ok'          => false,
					'description' => 'Not an array',
				);
			}
		}
	}

	/**
	 * Checks if the command executed successfully.
	 *
	 * @return bool TRUE if the was an success.
	 */
	public function is_valid(): bool {
		return $this->response['ok'] === true;
	}

	public function __toString(): string {
		return $this->is_valid() ?
			json_encode( $this->response['result'] )
			: $this->response['description'];
	}
}