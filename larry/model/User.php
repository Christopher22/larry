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
	private int $chat_id;
	private PDO $database;

	/**
	 * Creates a user which is not saved to the database.
	 *
	 * @param   PDO     $database  The database.
	 * @param   int     $id        The unique id of the user.
	 * @param   string  $name      The name of the user.
	 * @param   int     $chat_id   The unique id of the user.
	 */
	public function __construct(
		PDO $database,
		int $id,
		string $name,
		int $chat_id = 0
	) {
		$this->id       = $id;
		$this->name     = $name;
		$this->database = $database;
		$this->chat_id  = $chat_id;
	}

	/**
	 * @return string The name of the user.
	 */
	public function name(): string {
		return $this->name;
	}

	/**
	 * @return bool True, if the user exists in the database.
	 */
	public function exists(): bool {
		$statement = $this->database->prepare( sprintf(
			'SELECT EXISTS(SELECT 1 FROM %s WHERE id = :id)',
			self::TABLE_NAME
		) );

		return $statement !== false
		       && $statement->bindValue( 'id', $this->id, PDO::PARAM_INT )
		       && $statement->execute()
		       && $statement->fetchColumn() == 1;
	}

	/**
	 * @return int The unique id of the user.
	 */
	public function id(): int {
		return $this->id;
	}

	/**
	 * @return int The unique ID of the current chat.
	 */
	public function chat_id(): int {
		return $this->chat_id;
	}

	/**
	 * Create a new user. This will fail if there is already another with the same id.
	 *
	 * @return bool TRUE on success.
	 */
	public function create(): bool {
		$statement = $this->database->prepare( sprintf(
			'INSERT INTO %s (id, user_name, chat_id) VALUES (:id, :user_name, :chat_id);',
			self::TABLE_NAME
		) );

		return $statement !== false
		       && $statement->bindValue( 'id', $this->id, PDO::PARAM_INT )
		       && $statement->bindValue( 'chat_id',
				$this->chat_id,
				PDO::PARAM_INT )
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
			sprintf( 'SELECT user_name, chat_id FROM %s WHERE id = ? ORDER BY id ASC',
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
				$result[] = new User( $database,
					$id,
					$tmp["user_name"],
					$tmp["chat_id"] );
			}
			$query->closeCursor();
		}

		return $result;
	}

	/**
	 * Load all users from the database sorted by their ID.
	 *
	 * @param   PDO  $database  The database.
	 *
	 * @return User[] All existing users in the database.
	 */
	public static function load_all( PDO $database ): array {
		$result = array();
		$query  = $database->prepare(
			sprintf( 'SELECT id, user_name, chat_id FROM %s ORDER BY id ASC',
				self::TABLE_NAME )
		);
		if ( $query === false ) {
			return $result;
		}

		$query->execute();
		foreach ( $query->fetchAll( PDO::FETCH_ASSOC ) as $raw_user ) {
			$result[] = new User( $database,
				$raw_user["id"],
				$raw_user["user_name"],
				$raw_user["chat_id"] );
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
					"CREATE TABLE %s (id INT PRIMARY KEY, user_name TEXT NOT NULL, chat_id INT NOT NULL);",
					self::TABLE_NAME
				)
			) !== false;
	}
}