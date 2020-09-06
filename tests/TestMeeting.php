<?php

use PHPUnit\Framework\TestCase;

use larry\model\Meeting;
use larry\model\User;
use larry\model\Availability;

class TestMeeting extends TestCase {

	public function test_prepare(): PDO {
		$database = new PDO( 'sqlite::memory:' );
		// Raise exceptions for easier debugging:
		// $database->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
		$this->assertTrue( User::prepare_database( $database ) );
		$this->assertTrue( Meeting::prepare_database( $database ) );

		return $database;
	}

	/**
	 * @depends test_prepare
	 */
	public function test_add( PDO $database ) {
		$user1 = new User( $database, 1, "John Doe" );
		$user2 = new User( $database, 2, "Jane Doe" );
		$this->assertTrue( $user1->create() );
		$this->assertTrue( $user2->create() );

		// The insertion order is not of interest
		$date3 = Meeting::from_date( $database, 1997, 5, 1 );
		$date1 = Meeting::from_date( $database, 1997, 4, 22 );
		$date2 = Meeting::from_date( $database, 1997, 4, 29 );

		// Check that availability returns the correct value if nothing is inserted.
		$this->assertNull( $date1->availability( $user1 )->is_available() );

		$this->assertTrue( $date3->set_availability( new Availability( $user1,
			false ) ) );
		$this->assertTrue( $date1->set_availability( new Availability( $user1,
			true ) ) );
		$this->assertTrue( $date1->set_availability( new Availability( $user2,
			false ) ) );
		$this->assertTrue( $date2->set_availability( new Availability( $user1,
			true ) ) );
		$this->assertTrue( $date2->set_availability( new Availability( $user2,
			true ) ) );

		return $database;
	}

	/**
	 * @depends test_add
	 */
	public function test_loading_all( PDO $database ) {
		$dates = Meeting::load_all( $database );
		$this->assertCount( 3, $dates );

		// The order of dates is ascending
		$this->assertEquals( '1997-04-22',
			$dates[0]->date()->format( 'Y-m-d' ) );
		$this->assertEquals( '1997-04-29',
			$dates[1]->date()->format( 'Y-m-d' ) );
		$this->assertEquals( '1997-05-01',
			$dates[2]->date()->format( 'Y-m-d' ) );
	}

	/**
	 * @depends test_add
	 */
	public function test_query_all( PDO $database ) {
		$availabilities1 = ( Meeting::from_date( $database,
			1997,
			4,
			22 ) )->availabilities();
		$availabilities2 = ( Meeting::from_date( $database,
			1997,
			4,
			29 ) )->availabilities();
		$availabilities3 = ( Meeting::from_date( $database,
			1997,
			5,
			1 ) )->availabilities();

		$this->assertCount( 2, $availabilities1 );
		$this->assertCount( 2, $availabilities2 );
		$this->assertCount( 1, $availabilities3 );

		// The order is defined as ascending
		$this->assertTrue( $availabilities1[0]->is_available() );
		$this->assertFalse( $availabilities1[1]->is_available() );
		$this->assertTrue( $availabilities2[0]->is_available() );
		$this->assertTrue( $availabilities2[1]->is_available() );
		$this->assertFalse( $availabilities3[0]->is_available() );

		return $database;
	}

	/**
	 * @depends test_query_all
	 */
	public function test_query_all_with_unknown( PDO $database ) {
		$availabilities = ( Meeting::from_date( $database,
			1997,
			5,
			1 ) )->availabilities( true );

		$this->assertCount( 2, $availabilities );
		$this->assertFalse( $availabilities[0]->is_available() );
		$this->assertNull( $availabilities[1]->is_available() );
	}

	/**
	 * @depends test_add
	 */
	public function test_query_single( PDO $database ) {
		$users = User::load( $database, 1, 2 );

		$date2 = Meeting::from_date( $database, 1997, 4, 29 );
		$date3 = Meeting::from_date( $database, 1997, 5, 1 );

		$this->assertTrue( $date2->availability( $users[1] )->is_available() );
		$this->assertFalse( $date3->availability( $users[0] )->is_available() );
	}

	/**
	 * @depends test_query_all
	 */
	public function test_update( PDO $database ) {
		$users = User::load( $database, 1, 2 );
		$date1 = Meeting::from_date( $database, 1997, 4, 22 );
		$date2 = Meeting::from_date( $database, 1997, 4, 29 );
		$date3 = Meeting::from_date( $database, 1997, 5, 1 );

		$date1->set_availability( new Availability( $users[1], true ) );
		$date2->set_availability( new Availability( $users[0], false ) );
		$date3->set_availability( new Availability( $users[0], null ) );

		$availabilities1 = $date1->availabilities();
		$availabilities2 = $date2->availabilities();

		$this->assertTrue( $availabilities1[0]->is_available() );
		$this->assertTrue( $availabilities1[1]->is_available() );
		$this->assertFalse( $availabilities2[0]->is_available() );
		$this->assertTrue( $availabilities2[1]->is_available() );
		$this->assertCount( 0, $date3->availabilities() );

		return $database;
	}

	/**
	 * @depends test_update
	 */
	public function test_missing( PDO $database ) {
		// Create a new user
		$user3 = new User( $database, 3, "Max Mustermann" );
		$this->assertTrue( $user3->create() );

		// Check only the new user has no response on date 1
		$date1         = Meeting::from_date( $database, 1997, 4, 22 );
		$unknown_date1 = $date1->unknown_availabilities();
		$this->assertCount( 1, $unknown_date1 );
		$this->assertEquals( 3, $unknown_date1[0]->id() );

		$date3         = Meeting::from_date( $database, 1997, 5, 1 );
		$unknown_date3 = $date3->unknown_availabilities();
		$this->assertCount( 3, $unknown_date3 );
	}
}