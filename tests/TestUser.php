<?php

require "../larry/model/User.php";

use PHPUnit\Framework\TestCase;
use larry\model\User;

class TestUser extends TestCase {

	public function testPrepare(): PDO {
		$database = new PDO( 'sqlite::memory:' );
		// Raise exceptions for easier debugging:
		// $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$this->assertTrue( User::prepare_database( $database ) );

		return $database;
	}

	/**
	 * @depends testPrepare
	 */
	public function testAddUser( PDO $database ) {
		$user1 = new User( $database, 1, "Max Muster" );
		$user2 = new User( $database, 2, "Marian Muster" );
		$this->assertTrue( $user1->create() );
		$this->assertTrue( $user2->create() );

		return $database;
	}

	/**
	 * @depends testAddUser
	 */
	public function testAddExistingUser( PDO $database ) {
		$user = new User( $database, 1, "Larry Doe" );
		$this->assertFalse( $user->create() );
	}

	/**
	 * @depends testAddUser
	 */
	public function testQuery( PDO $database ) {
		$data = User::load( $database, 1, 5, 2, 3 );
		$this->assertCount( 2, $data );
		$this->assertEquals( "Max Muster", $data[0]->name() );
		$this->assertEquals( "Marian Muster", $data[1]->name() );
	}
}