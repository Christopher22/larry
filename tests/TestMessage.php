<?php

require "../larry/Context.php";

use PHPUnit\Framework\TestCase;

use larry\commands\Command;
use larry\commands\Result;
use larry\Context;
use larry\model\User;
use larry\updates\Message;

/**
 * A mock command returning the given parameters as string.
 */
class MockCommand extends Command {
	private string $name;

	public function __construct( string $name ) {
		$this->name = $name;
	}

	public function name(): string {
		return $this->name;
	}

	public function description(): string {
		return 'Example method';
	}

	public function execute(
		Context $context,
		Message $message,
		string ...$parameters
	): Result {
		return new Result( implode( ' ', $parameters ), true );
	}
}

class TestMessage extends TestCase {

	public function test_context(): Context {
		$context = Context::create( 'sqlite::memory:' );
		$this->assertNotNull( $context );

		return $context;
	}

	/**
	 * @depends test_context
	 */
	public function test_parsing( Context $context ): Context {
		$example_user = new User( $context->database(), 1, "John" );
		$message      = new Message( Message::generate_json(
			$example_user,
			' d /c1 c2    dA /b /C3 1 '
		) );
		$this->assertTrue( $message->is_valid() );
		$this->assertEquals( $example_user->id(),
			$message->sender( $context )->id() );
		$this->assertEquals( $example_user->name(),
			$message->sender( $context )->name() );
		$this->assertEquals( array( 'd', '/c1', 'c2', 'dA', '/b', '/C3', '1' ),
			$message->tokens( false ) );
		$this->assertEquals( array( 'd', '/c1', 'c2', 'da', '/b', '/c3', '1' ),
			$message->tokens( true ) );

		return $context;
	}

	/**
	 * @depends test_parsing
	 */
	public function test_commands( Context $context ) {
		$message  = new Message( Message::generate_json(
			new User( $context->database(), 0, "John" ),
			"d /c1 c2    dA /b /C3 1"
		) );
		$commands = array(
			new MockCommand( "/c3" ),
			new MockCommand( "c2" ),
			new MockCommand( "/c1" ),
		);

		$this->assertTrue( $commands[0]->is_public() );
		$this->assertFalse( $commands[1]->is_public() );

		$result = Command::parse( $context, $message, $commands );
		$this->assertEquals( array(
			new Result( '', true ), // \c1 has no arguments
			new Result( 'dA /b', true ), // Only commands are always lower case
			new Result( '1', true ),
		),
			$result );
	}
}