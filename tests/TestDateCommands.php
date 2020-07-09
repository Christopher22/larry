<?php

require "../larry/Context.php";

use PHPUnit\Framework\TestCase;

use larry\commands\Command;
use larry\commands\Yes;
use larry\commands\No;
use larry\Context;
use larry\commands\DateCommand;
use larry\model\Meeting;
use larry\model\User;
use larry\updates\Message;


class TestDateCommands extends TestCase {

	public function test_context(): Context {
		$context = Context::create( 'sqlite::memory:' );
		$this->assertNotNull( $context );
		$this->assertTrue( User::prepare_database( $context->database() ) );
		$this->assertTrue( Meeting::prepare_database( $context->database() ) );

		return $context;
	}

	/**
	 * @depends   test_context
	 */
	public function test_set( Context $context ): Context {
		$user    = new User( $context->database(), 0, "John Doe" );
		$message = new Message( Message::generate_json(
			$user,
			" /yes 22.04.1997"
		) );

		$meeting = Meeting::from_date( $context->database(), 1997, 04, 22 );
		$this->assertNull( $meeting->availability( $user )->is_available() );

		$result = Command::parse( $context,
			$message,
			array(
				new Yes(),
				new No(),
			) );

		$this->assertCount( 1, $result );
		$this->assertTrue( $result[0]->is_successful() );
		$this->assertTrue( $meeting->availability( $user )->is_available() );

		return $context;
	}

	/**
	 * @depends   test_set
	 */
	public function test_update( Context $context ): Context {
		$user    = new User( $context->database(), 0, "John Doe" );
		$message = new Message( Message::generate_json(
			$user,
			" /no 22.04.1997"
		) );

		$meeting = Meeting::from_date( $context->database(), 1997, 04, 22 );
		$this->assertTrue( $meeting->availability( $user )->is_available() );

		$result = Command::parse( $context,
			$message,
			array(
				new Yes(),
				new No(),
			) );

		$this->assertCount( 1, $result );
		$this->assertTrue( $result[0]->is_successful() );
		$this->assertFalse( $meeting->availability( $user )->is_available() );

		return $context;
	}

	public function test_parsing() {
		$current_year = date( 'Y' );
		$ground_truth = DateTimeImmutable::createFromFormat( 'd.m.Y H:i:s',
			"01.02.{$current_year} 00:00:00" );

		$this->assertEquals(
			$ground_truth,
			DateCommand::parse_date( "    01.02.$current_year " )
		);
		$this->assertEquals(
			$ground_truth,
			DateCommand::parse_date( "1.02.$current_year" )
		);
		$this->assertEquals(
			$ground_truth,
			DateCommand::parse_date( '01. 2' )
		);
	}
}