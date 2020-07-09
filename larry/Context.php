<?php


namespace larry;


use PDO;
use PDOException;

/**
 * The context of a execution specifying runtime information like the database.
 *
 * @package larry
 */
class Context {
	private PDO $database;
	private string $bot_token;
	private string $password;

	private static $is_registered = false;

	private function __construct(
		PDO $database,
		string $bot_token,
		string $password
	) {
		$this->database  = $database;
		$this->bot_token = $bot_token;
		$this->password  = $password;
	}

	/**
	 * Create a new context. Changing the default arguments allows to overwrite the default settings.
	 *
	 * @param   string  $database_string  The database string used for PDO.
	 * @param   string  $bot_token        The unique token of the bot.
	 * @param   string  $password         The password to start an interaction with the bot.
	 * @param   string  $db_user          The database user.
	 * @param   string  $db_password      The database password.
	 *
	 * @return Context|null The Context object or NULL on error.
	 */
	public static function create(
		string $database_string = '',
		string $bot_token = '',
		string $password = '',
		string $db_user = '',
		string $db_password = ''
	): ?Context {
		try {
			$database = new PDO( $database_string, $db_user, $db_password );
		}
		catch ( PDOException $e ) {
			return null;
		}

		return new Context( $database, $bot_token, $password );
	}

	/**
	 * @return PDO A handle to the database.s
	 */
	public function database(): PDO {
		return $this->database;
	}

	/**
	 * Return the URL to the Telegram API.
	 *
	 * @param $method string The name of the method.
	 *
	 * @return string The complete URL.
	 */
	public function api_url( string $method ): string {
		return "https://api.telegram.org/bot$this->bot_token/$method";
	}

	/**
	 * Checks if a provided password is correct.
	 *
	 * @param   string  $password  The password
	 *
	 * @return bool TRUE, if the user entered the correct password.
	 */
	public function is_user_allowed( string $password ): bool {
		return $this->password == $password;
	}

	/**
	 * Register all the classes of Larry by an custom autoloader.
	 * It is safe to call this function multiple times.
	 *
	 * @return bool TRUE on success.
	 */
	public static function register_autoloader(): bool {
		if ( self::$is_registered ) {
			return true;
		}
		self::$is_registered = spl_autoload_register( function ( $class ) {
			$file = dirname( __DIR__ )
			        . DIRECTORY_SEPARATOR
			        . str_replace( '\\', DIRECTORY_SEPARATOR, $class ) . '.php';
			if ( file_exists( $file ) ) {
				require $file;

				return true;
			}

			return false;
		} );

		return self::$is_registered;
	}
}

// Register the autoloader for everything else
Context::register_autoloader();