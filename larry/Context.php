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
	private static $is_registered = false;

	private function __construct( PDO $database ) {
		$this->database = $database;
	}

	/**
	 * Create a new context. Changing the default arguments allows to overwrite the default settings.
	 *
	 * @param   string  $database_string  The database string used for PDO.
	 *
	 * @return Context|null The Context object or NULL on error.
	 */
	public static function create(
		string $database_string = ''
	): ?Context {
		try {
			$database = new PDO( $database_string );
		}
		catch ( PDOException $e ) {
			return null;
		}

		return new Context( $database );
	}

	/**
	 * @return PDO A handle to the database.s
	 */
	public function database(): PDO {
		return $this->database;
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