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
		$user  = new User( $context->database(), 0, "John Doe" );
		$user2 = new User( $context->database(), 1, "Jane Doe" );
		$this->assertTrue( $user2->create() );

		$message = new Message( Message::generate_json(
			$user,
			" /no 22.04.1997"
		) );

		// Overwrite the Result sending implementation
		$sent_messages = array();
		Result::overwrite_send( function ( ...$params ) use (
			&$sent_messages
		) {
			$sent_messages[] = strval( $params[0] );

			return true;
		} );

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

		// Ensure a notification is send to the others
		$this->assertCount( 1, $sent_messages );
		$this->assertEquals(
			sprintf(
				No::CHANGE_NOTIFICATION,
				$user->name(),
				"22.04.1997"
			),
			strval( $sent_messages[0] )
		);

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
		$this->assertEquals( "Meeting on {$meeting->date()->format('d.m.Y')}:\nJohn Doe\t\t❌\nJane Doe\t\t❓",
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
	 * @depends   test_update
	 */
	public function test_ask( Context $context ) {
		$new_user = new User( $context->database(), 42, "Sender" );

		$this->assertTrue( $new_user->create() );
		$message = new Message( Message::generate_json(
			$new_user,
			"/ask 22.04.1997",
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
		$this->assertCount( 2, $sent_messages );
		$this->assertTrue( $result[0]->is_successful() );
		$this->assertEquals(
			sprintf(
				Ask::CONFIRMATION,
				"- Jane Doe\n- Sender",
				"22.04.1997"
			),
			strval( $result[0] )
		);
		$this->assertEquals(
			sprintf(
				Ask::QUESTION,
				"Jane Doe",
				"Sender",
				"22.04.1997"
			),
			$sent_messages[0]
		);
		$this->assertEquals(
			sprintf(
				Ask::QUESTION,
				"Sender",
				"Sender",
				"22.04.1997"
			),
			$sent_messages[1]
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