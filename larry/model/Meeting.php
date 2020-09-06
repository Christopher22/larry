<?php


namespace larry\model;

use DateTimeImmutable;
use PDO;

/**
 * A meeting at a specific point in time.
 *
 * @package larry\model
 */
class Meeting {
	public const TABLE_NAME = "dates";

	private DateTimeImmutable $date;
	private PDO $database;

	/**
	 * Create a new meeting without any attending users.
	 *
	 * @param   PDO                $database    The database.
	 * @param   DateTimeImmutable  $date        The date of the meeting.
	 * @param   bool               $reset_time  If TRUE, the time is ignored.
	 */
	public function __construct(
		PDO $database,
		DateTimeImmutable $date,
		bool $reset_time = true
	) {
		$this->date     = ( $reset_time ? $date->setTime( 0, 0 ) : $date );
		$this->database = $database;
	}

	/**
	 * A shortcut for creating a meeting at a specific date.
	 *
	 * @param   PDO  $database  The database.
	 * @param   int  $year      The year of the meeting.
	 * @param   int  $month     The month of the meeting.
	 * @param   int  $day       The day of the meeting.
	 *
	 * @return Meeting The created meeting without attending users.
	 */
	public static function from_date(
		PDO $database,
		int $year,
		int $month,
		int $day
	) {
		$date = ( new DateTimeImmutable() )->setDate( $year, $month, $day );

		return new Meeting( $database, $date, true );
	}

	/**
	 * @return DateTimeImmutable The date of the meeting.
	 */
	public function date(): DateTimeImmutable {
		return $this->date;
	}

	/**
	 * Set the availability of a user.
	 *
	 * @param   Availability  $availability  Availability of a user.
	 *
	 * @return bool True if the availability was successfully updated.
	 */
	public function set_availability( Availability $availability ): bool {
		$statement = $this->database->prepare( sprintf(
			( $availability->is_available() === null
				?
				'DELETE FROM %s WHERE id = :id AND user_id = :user_id'
				:
				'REPLACE INTO %s (id, user_id, is_available) VALUES (:id, :user_id, :is_available)'
			),
			self::TABLE_NAME
		) );

		// Bind the timestamp and the user id
		if ( $statement === false
		     || ! $statement->bindValue( 'id',
				$this->date->getTimestamp(),
				PDO::PARAM_INT )
		     || ! $statement->bindValue( 'user_id',
				$availability->user()->id(),
				PDO::PARAM_INT )
		) {
			return false;
		}

		// Bind available only on update
		if ( $availability->is_available() !== null ) {
			$statement->bindValue( 'is_available',
				$availability->is_available() === true ? 1 : 0,
				PDO::PARAM_INT );
		}

		return $statement->execute();
	}

	/**
	 * Check the availability of a single user.
	 *
	 * @param   User  $user  The user.
	 *
	 * @return Availability|null The availability or NULL on error.
	 */
	public function availability( User $user ): ?Availability {
		$statement = $this->database->prepare( sprintf(
			'SELECT is_available FROM %s WHERE id = :id and user_id = :user_id',
			self::TABLE_NAME
		) );

		if ( $statement === false
		     || ! $statement->bindValue( 'id',
				$this->date->getTimestamp(),
				PDO::PARAM_INT )
		     || ! $statement->bindValue( 'user_id',
				$user->id(),
				PDO::PARAM_INT )
		     || ! $statement->execute() ) {
			return null;
		}

		$value = $statement->fetch( pdo::FETCH_NUM );
		if ( $value === false ) {
			return new Availability( $user, null );
		} elseif ( $value[0] == 0 ) {
			return new Availability( $user, false );
		} else {
			return new Availability( $user, true );
		}
	}

	/**
	 * Return availabilities ordered by the ids of the users.
	 *
	 * @param   bool  $include_unknown  Include the users without information.
	 *
	 * @return Availability[] An array of availabilities.
	 */
	public function availabilities( bool $include_unknown = false ): array {
		$raw_users = array();
		$statement = $this->database->prepare( sprintf(
			'SELECT user_id, is_available FROM %s WHERE id = ? ORDER BY user_id ASC',
			self::TABLE_NAME
		) );

		// Return an empty array if we were unable to query the user
		if ( $statement === false
		     || ! $statement->bindValue( 1,
				$this->date->getTimestamp(),
				PDO::PARAM_INT )
		     || ! $statement->execute() ) {
			return $raw_users;
		}

		$raw_users = $statement->fetchAll( PDO::FETCH_ASSOC );
		if ( $raw_users === false ) {
			return array();
		}

		$raw_users = array_combine(
			array_column( $raw_users, 'user_id' ),
			array_column( $raw_users, 'is_available' )
		);

		// Select all users or only the queried ones as selection
		$user_selection = $include_unknown ? User::load_all( $this->database )
			: User::load( $this->database, ...array_keys( $raw_users ) );

		// Map each user to its availability
		return array_map(
			function ( $user ) use ( $raw_users ) {
				# Map the raw integers or missing information to proper values
				$raw_availability = $raw_users[ $user->id() ] ?? null;
				if ( ! is_null( $raw_availability )
				     && $raw_availability == 0 ) {
					$raw_availability = false;
				} elseif ( $raw_availability == 1 ) {
					$raw_availability = true;
				}

				return new Availability(
					$user,
					$raw_availability
				);
			},
			$user_selection
		);
	}

	/**
	 * Returns all the user which have not indicated if they are available.
	 *
	 * @return User[] The users without a response.
	 */
	public function unknown_availabilities(): array {
		$statement = $this->database->prepare( sprintf(
			"SELECT id, user_name, chat_id FROM %s WHERE NOT EXISTS (SELECT is_available FROM %s WHERE %s.user_id = %s.id and %s.id = ?) ORDER BY %s.id ASC ",
			User::TABLE_NAME,
			self::TABLE_NAME,
			self::TABLE_NAME,
			User::TABLE_NAME,
			self::TABLE_NAME,
			User::TABLE_NAME
		) );

		// Check if preparation and execution was successful
		if ( $statement === false
		     || ! $statement->bindValue( 1,
				$this->date->getTimestamp(),
				PDO::PARAM_INT )
		     || ! $statement->execute() ) {
			return array();
		}

		$database = $this->database;

		return array_map( function ( $user ) use ( $database ) {
			return new User( $database,
				$user['id'],
				$user['user_name'],
				$user['chat_id'] );
		},
			$statement->fetchAll( PDO::FETCH_ASSOC )
		);
	}

	/**
	 * Load all meetings from the database in ascending order.s
	 *
	 * @param   PDO  $database  The database.
	 *
	 * @return Meeting[] Sorted meetings in the database.
	 */
	public static function load_all( PDO $database ): array {
		$statement = $database->query( sprintf(
			'SELECT DISTINCT id FROM %s ORDER BY id ASC',
			self::TABLE_NAME
		) );
		if ( $statement === false ) {
			return array();
		}

		$dates = $statement->fetchAll( PDO::FETCH_NUM );

		// Create a date object for each timestamp
		return array_map( function ( $row ) use ( $database ) {
			$date = ( new DateTimeImmutable() )->setTimestamp( $row[0] );

			return new Meeting( $database, $date );
		},
			( $dates !== false ? $dates : array() ) );
	}

	/**
	 * Create the tables suitable for managing meetings.
	 *
	 * @param   PDO  $database  The database.
	 *
	 * @return bool TRUE on success.
	 */
	public static function prepare_database( PDO $database ): bool {
		return $database->exec( sprintf(
					'CREATE TABLE %s (
    							id INT NOT NULL, 
    							user_id INT NOT NULL, 
    							is_available BOOLEAN NOT NULL,
    							CONSTRAINT dates_primary PRIMARY KEY(id, user_id),
    							CONSTRAINT dates_person_foreign FOREIGN KEY (user_id) REFERENCES %s (id)
                );',
					self::TABLE_NAME,
					User::TABLE_NAME
				)
			) !== false;
	}
}