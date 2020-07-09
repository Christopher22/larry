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

	private static $is_registered = false;

	private function __construct( PDO $database, string $bot_token ) {
		$this->database  = $database;
		$this->bot_token = $bot_token;
	}

	/**
	 * Create a new context. Changing the default arguments allows to overwrite the default settings.
	 *
	 * @param   string  $database_string  The database string used for PDO.
	 * @param   string  $bot_token        The unique token of the bot.
	 *
	 * @return Context|null The Context object or NULL on error.
	 */
	public static function create(
		string $database_string = '',
		string $bot_token = ''
	): ?Context {
		try {
			$database = new PDO( $database_string );
		}
		catch ( PDOException $e ) {
			return null;
		}

		return new Context( $database, $bot_token );
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