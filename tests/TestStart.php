<?php

require "../larry/Context.php";

use PHPUnit\Framework\TestCase;

use larry\Context;
use larry\model\User;
use larry\commands\Command;
use larry\commands\Start;
use larry\updates\Message;

class TestStart extends TestCase {
	private const PASSWORD = 'SecreT!';

	public function test_context(): Context {
		$context = Context::create( 'sqlite::memory:', '', self::PASSWORD );
		$this->assertNotNull( $context );
		$this->assertTrue( User::prepare_database( $context->database() ) );

		return $context;
	}

	/**
	 * @depends   test_context
	 */
	public function test_wrong( Context $context ) {
		$user    = new User( $context->database(), 1, "John Doe", 42 );
		$message = Message::generate_json( $user, "/start wrong_password" );

		$this->assertFalse( $user->exists() );
		$result = Command::parse( $context,
			new Message( $message ),
			array( new Start() ) );
		$this->assertCount( 1, $result );
		$this->assertFalse( $result[0]->is_successful() );
		$this->assertFalse( $result[0]->should_respond() );
		$this->assertFalse( $user->exists() );
	}

	/**
	 * @depends   test_context
	 */
	public function test_empty( Context $context ) {
		$user    = new User( $context->database(), 1, "John Doe", 42 );
		$message = Message::generate_json( $user, "/start" );

		$this->assertFalse( $user->exists() );
		$result = Command::parse( $context,
			new Message( $message ),
			array( new Start() ) );
		$this->assertCount( 1, $result );
		$this->assertFalse( $result[0]->is_successful() );
		$this->assertFalse( $result[0]->should_respond() );
		$this->assertFalse( $user->exists() );
	}

	/**
	 * @depends   test_context
	 */
	public function test_correct( Context $context ) {
		$user    = new User( $context->database(), 2, "John Doe", 42 );
		$message = Message::generate_json( $user, "/start " . self::PASSWORD );

		$this->assertFalse( $user->exists() );
		$result = Command::parse( $context,
			new Message( $message ),
			array( new Start() ) );
		$this->assertCount( 1, $result );
		$this->assertTrue( $result[0]->is_successful() );
		$this->assertTrue( $result[0]->should_respond() );
		$this->assertTrue( $user->exists() );

		return $context;
	}

	/**
	 * @depends   test_correct
	 */
	public function test_multiple_times( Context $context ) {
		$user    = new User( $context->database(), 2, "John Doe", 42 );
		$message = Message::generate_json( $user, "/start " . self::PASSWORD );

		$this->assertTrue( $user->exists() );
		$result = Command::parse( $context,
			new Message( $message ),
			array( new Start() ) );
		$this->assertCount( 1, $result );
		$this->assertTrue( $result[0]->is_successful() );
		$this->assertTrue( $result[0]->should_respond() );
		$this->assertTrue( $user->exists() );
	}
}