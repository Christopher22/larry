<?php


use PHPUnit\Framework\TestCase;

use larry\Context;
use larry\updates\Message;
use larry\commands\Command;
use larry\commands\Yes;
use larry\commands\No;
use larry\commands\DateCommand;
use larry\commands\Ask;
use larry\commands\Result;
use larry\commands\Summary;
use larry\model\Meeting;
use larry\model\User;
use larry\model\Availability;


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
		$user = new User( $context->database(), 0, "John Doe" );
		// Create the user into the database to avoid foreign key problems
		$this->assertTrue( $user->create() );
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

	/**
	 * @depends   test_update
	 */
	public function test_summary_existing( Context $context ) {
		$user    = new User( $context->database(), 0, "John Doe" );
		$meeting = Meeting::from_date( $context->database(), 1997, 04, 22 );
		$this->assertFalse( $meeting->availability( $user )->is_available() );
		$this->assertCount( 1, $meeting->availabilities() );

		$message = new Message( Message::generate_json(
			$user,
			" /summary {$meeting->date()->format('d.m.Y')}"
		) );

		$result = Command::parse( $context,
			$message,
			array(
				new Summary(),
			) );

		$this->assertCount( 1, $result );
		$this->assertTrue( $result[0]->is_successful() );
		$this->assertEquals( "Meeting on {$meeting->date()->format('d.m.Y')}:\nJohn Doe\t\t❌",
			strval( $result[0] ) );
	}

	/**
	 * @depends   test_update
	 */
	public function test_summary_non_existing( Context $context ) {
		$user    = new User( $context->database(), 0, "John Doe" );
		$meeting = Meeting::from_date( $context->database(), 1998, 05, 23 );
		$this->assertNull( $meeting->availability( $user )->is_available() );

		$message = new Message( Message::generate_json(
			$user,
			" /summary {$meeting->date()->format('d.m.Y')}"
		) );

		$result = Command::parse( $context,
			$message,
			array(
				new Summary(),
			) );

		$this->assertCount( 1, $result );
		$this->assertTrue( $result[0]->is_successful() );
		$this->assertEquals( Summary::NOBODY, strval( $result[0] ) );
	}

	/**
	 * Checks the usage of a reply as argument.
	 *
	 * @depends   test_update
	 */
	public function test_reply( Context $context ) {
		$user             = new User( $context->database(), 0, "John Doe" );
		$original_message = new Message( Message::generate_json(
			$user,
			"Do you like to build a snowman? 22.04.1997 Cool date!"
		) );
		$message          = new Message( Message::generate_json(
			$user,
			"/yes",
			1,
			$original_message
		) );

		$meeting = Meeting::from_date( $context->database(), 1997, 04, 22 );
		$this->assertFalse( $meeting->availability( $user )->is_available() );

		$result = Command::parse( $context,
			$message,
			array(
				new Yes(),
				new No(),
			) );

		$this->assertCount( 1, $result );
		$this->assertTrue( $result[0]->is_successful() );
		$this->assertTrue( $meeting->availability( $user )->is_available() );
	}

	/**
	 * @depends   test_context
	 */
	public function test_ask( Context $context ) {
		$user1   = new User( $context->database(), 42, "Sender" );
		$user2   = new User( $context->database(), 43, "Receiver" );
		$user3   = new User( $context->database(), 44, "Receiver2" );
		$meeting = Meeting::from_date( $context->database(), 2012, 1, 1 );

		$this->assertTrue( $user1->create() );
		$this->assertTrue( $user2->create() );
		$this->assertTrue( $user3->create() );
		$this->assertTrue( $meeting->set_availability( new Availability( $user3,
			true ) ) );

		$message = new Message( Message::generate_json(
			$user1,
			"/ask 1.01.2012",
			0,
		) );

		// Overwrite the Result sending implementation
		$sent_messages = array();
		Result::overwrite_send( function ( ...$params ) use (
			&$sent_messages
		) {
			$sent_messages[] = strval( $params[0] );

			return true;
		} );

		$result = Command::parse( $context,
			$message,
			array(
				new Yes(),
				new Ask(),
			) );

		$this->assertCount( 1, $result );
		$this->assertCount( 3, $sent_messages );
		$this->assertTrue( $result[0]->is_successful() );
		$this->assertEquals(
			sprintf(
				Ask::CONFIRMATION,
				"- John Doe\n- Sender\n- Receiver",
				"01.01.2012"
			),
			strval( $result[0] )
		);
		$this->assertEquals(
			sprintf(
				Ask::QUESTION,
				"Receiver",
				"Sender",
				"01.01.2012"
			),
			$sent_messages[2]
		);
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