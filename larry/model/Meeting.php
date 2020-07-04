<?php


namespace larry\model;

require "User.php";
require "Availability.php";

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
				'DELETE FROM %s WHERE id == :id AND user_id == :user_id'
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
	 * Return all the known availabilities ordered by the ids of the users.
	 *
	 * @return Availability[] An array of known(!) availabilities.
	 */
	public function availabilities(): array {
		$raw_users = array();
		$statement = $this->database->prepare( sprintf(
			'SELECT user_id, is_available FROM %s WHERE id == ? ORDER BY user_id ASC',
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

		// Map each user to its availability
		return array_map(
			function ( $user ) use ( $raw_users ) {
				return new Availability(
					$user,
					$raw_users[ $user->id() ] == 1
				);
			},
			User::load( $this->database, ...array_keys( $raw_users ) )
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