<?php


namespace larry\api;


use larry\Context;

/**
 * A request sent to a Larry API.
 *
 * @package larry\api
 */
class Request {

	private array $get_args;
	private array $post_args;
	private array $server_args;
	private Context $context;

	/**
	 * Create a new request.
	 *
	 * @param   Context  $context  The context of the request.
	 * @param   array    $get      The GET variables.
	 * @param   array    $post     The POST variables.
	 * @param   array    $server   The SERVER variables.
	 */
	public function __construct(
		Context $context,
		array $get,
		array $post,
		array $server
	) {
		$this->context     = $context;
		$this->get_args    = $get;
		$this->post_args   = $post;
		$this->server_args = $server;
	}

	/**
	 * Create a request from the current server session.
	 *
	 * @param   Context  $context  The context.
	 *
	 * @return Request The matching request.
	 */
	public static function from_server( Context $context ): Request {
		return new Request(
			$context,
			$_GET,
			$_POST,
			$_SERVER
		);
	}

	/**
	 * Get the context of the current request.
	 *
	 * @return Context The context of the request.
	 */
	public function context(): Context {
		return $this->context;
	}

	/**
	 * Query the value of a specific source of variables.
	 *
	 * @param   int     $type  The type of variables. Currently: INPUT_SERVER, INPUT_GET, INPUT_POST
	 * @param   string  $name  The name of the variable.
	 *
	 * @return string|null The value of the variable or null if it is missing.
	 */
	public function get( int $type, string $name ): ?string {
		switch ( $type ) {
			case INPUT_SERVER:
				return $this->server_args[ $name ] ?? null;
			case INPUT_GET:
				return $this->get_args[ $name ] ?? null;
			case INPUT_POST:
				return $this->post_args[ $name ] ?? null;
			default:
				die( "Unsupported type of argument" );
		}
	}

	/**
	 * Set or modify the value of a specific source of variables.
	 *
	 * @param   int     $type   The type of variables. Currently: INPUT_SERVER, INPUT_GET, INPUT_POST
	 * @param   string  $name   The name of the variable.
	 * @param   string  $value  The value of the variable.
	 */
	public function set( int $type, string $name, string $value ) {
		switch ( $type ) {
			case INPUT_SERVER:
				$this->server_args[ $name ] = $value;
				break;
			case INPUT_GET:
				$this->get_args[ $name ] = $value;
				break;
			case INPUT_POST:
				$this->post_args[ $name ] = $value;
				break;
			default:
				die( "Unsupported type of argument" );
		}
	}

	/**
	 * Remove a variable from the existing request.
	 *
	 * @param   int     $type  The type of variables. Currently: INPUT_SERVER, INPUT_GET, INPUT_POST
	 * @param   string  $name  The name of the variable.
	 */
	public function remove( int $type, string $name ) {
		switch ( $type ) {
			case INPUT_SERVER:
				unset( $this->server_args[ $name ] );
				break;
			case INPUT_GET:
				unset( $this->get_args[ $name ] );
				break;
			case INPUT_POST:
				unset( $this->post_args[ $name ] );
				break;
			default:
				die( "Unsupported type of argument" );
		}
	}

	/**
	 * Filter a variable.
	 *
	 * @param   int     $type    The type of variables. Currently: INPUT_SERVER, INPUT_GET, INPUT_POST
	 * @param   string  $name    The name of the variable.
	 * @param   int     $filter  The type of filter.
	 *
	 * @return mixed|null The filtered value, FALSE if the filter was not successful or NULL on missing variable.
	 */
	public function filter( int $type, string $name, int $filter ) {
		$value = $this->get( $type, $name );
		if ( $value === null ) {
			return null;
		}

		return filter_var( $value, $filter );
	}

	/**
	 * Check if the request ships the correct password in the HTTP Authorization variable.
	 *
	 * @return bool True, if the request is allowed.
	 */
	public function is_allowed(): bool {
		$password_header = $this->get( INPUT_SERVER, "HTTP_AUTHORIZATION" );
		if ( is_null( $password_header ) or
		     preg_match( '/\\s*Basic\\s+([A-Za-z0-9\\+\\\\=]+)/is',
			     $password_header,
			     $password ) !== 1 ) {
			return false;
		}

		$password = base64_decode( $password[1], true );
		if ( $password !== false ) {
			$index = strrpos( $password, ':' );
			if ( $index !== false ) {
				$password = substr( $password, $index + 1 );
			} else {
				$password = false;
			}
		}

		return $password !== false
		       and $this->context->is_user_allowed( $password );
	}
}