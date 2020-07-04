<?php


namespace larry\model;

use PDO;

/**
 * A registered user of larry.
 *
 * @package larry\model
 */
class User {
	public const TABLE_NAME = "users";

	private string $name;
	private int $id;
	private PDO $database;

	/**
	 * Creates a user which is not saved to the database.
	 *
	 * @param   PDO     $database  The database.
	 * @param   int     $id        The unique id of the user.
	 * @param   string  $name      The name of the user.
	 */
	public function __construct( PDO $database, int $id, string $name ) {
		$this->id       = $id;
		$this->name     = $name;
		$this->database = $database;
	}

	/**
	 * @return string The name of the user.
	 */
	public function name(): string {
		return $this->name;
	}

	/**
	 * @return int The unique id of the user.
	 */
	public function id(): int {
		return $this->id;
	}

	/**
	 * Create a new user. This will fail if there is already another with the same id.
	 *
	 * @return bool TRUE on success.
	 */
	public function create(): bool {
		$statement = $this->database->prepare( sprintf(
			'INSERT INTO %s (id, user_name) VALUES (:id, :user_name);',
			self::TABLE_NAME
		) );

		return $statement !== false
		       && $statement->bindValue( 'id', $this->id, PDO::PARAM_INT )
		       && $statement->bindValue( 'user_name',
				$this->name,
				PDO::PARAM_STR )
		       && $statement->execute();
	}

	/**
	 * Load and sort the users by their id from the database. Invalid ids are ignored.
	 *
	 * @param   PDO  $database  The database.
	 * @param   int  ...$ids    The unique user ids.
	 *
	 * @return User[] The existing users from the database.
	 */
	public static function load( PDO $database, int ...$ids ): array {
		$result = array();
		$query  = $database->prepare(
			sprintf( 'SELECT user_name FROM %s WHERE id == ? ORDER BY id ASC',
				self::TABLE_NAME )
		);
		if ( $query === false ) {
			return $result;
		}

		// Read all the user names corresponding to the ids
		foreach ( $ids as $id ) {
			$query->bindParam( 1, $id, PDO::PARAM_INT );
			$query->execute();
			$tmp = $query->fetch( PDO::FETCH_ASSOC );
			if ( $tmp !== false ) {
				$result[] = new User( $database, $id, $tmp["user_name"] );
			}
			$query->closeCursor();
		}

		return $result;
	}

	/**
	 * Create the tables suitable for managing users.
	 *
	 * @param   PDO  $database  The database.
	 *
	 * @return bool TRUE on success.
	 */
	public static function prepare_database( PDO $database ): bool {
		return $database->exec(
				sprintf(
					"CREATE TABLE %s (id INT PRIMARY KEY, user_name TEXT NOT NULL);",
					self::TABLE_NAME
				)
			) !== false;
	}
}