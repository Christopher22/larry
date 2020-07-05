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
}