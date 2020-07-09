<?php

require "../larry/Context.php";

use PHPUnit\Framework\TestCase;

use larry\model\User;

class TestUser extends TestCase {

	public function test_prepare(): PDO {
		$database = new PDO( 'sqlite::memory:' );
		// Raise exceptions for easier debugging:
		// $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$this->assertTrue( User::prepare_database( $database ) );

		return $database;
	}

	/**
	 * @depends test_prepare
	 */
	public function test_add_user( PDO $database ) {
		$user1 = new User( $database, 1, "Max Muster", 42 );
		$user2 = new User( $database, 2, "Marian Muster", 43 );

		$this->assertFalse( $user1->exists() );
		$this->assertTrue( $user1->create() );
		$this->assertTrue( $user1->exists() );

		$this->assertTrue( $user2->create() );

		return $database;
	}

	/**
	 * @depends test_add_user
	 */
	public function test_add_existing_user( PDO $database ) {
		$user = new User( $database, 1, "Larry Doe" );
		$this->assertFalse( $user->create() );
	}

	/**
	 * @depends test_add_user
	 */
	public function test_query( PDO $database ) {
		$data = User::load( $database, 1, 5, 2, 3 );
		$this->assertCount( 2, $data );
		$this->assertEquals( "Max Muster", $data[0]->name() );
		$this->assertEquals( 42, $data[0]->chat_id() );
		$this->assertEquals( "Marian Muster", $data[1]->name() );
		$this->assertEquals( 43, $data[1]->chat_id() );
	}
}