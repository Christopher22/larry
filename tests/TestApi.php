<?php

use PHPUnit\Framework\TestCase;

use larry\Context;
use larry\api\Api;
use larry\api\Request;
use larry\api\Users;
use larry\model\User;

class TestApi extends TestCase {
	private const PASSWORD = 'gundler';
	private const AUTH = ' Basic Y2hyaXN0b3BoZXI6Z3VuZGxlcg==';

	public function test_context(): Context {
		$context = Context::create( 'sqlite::memory:', '', self::PASSWORD );
		$this->assertNotNull( $context );
		$this->assertTrue( User::prepare_database( $context->database() ) );

		return $context;
	}

	/**
	 * @depends test_context
	 */
	public function test_request( Context $context ) {
		$request = new Request(
			$context,
			array( 'api' => 'users' ),
			array( 'post_param' => '1' ),
			array(
				'HTTP_Authorization' => self::AUTH,
				'REQUEST_METHOD'     => 'GET',
			)
		);

		$this->assertEquals( 'users', $request->get( INPUT_GET, 'api' ) );
		$this->assertEquals( 1,
			$request->filter( INPUT_POST, 'post_param', FILTER_VALIDATE_INT ) );
		$this->assertEquals( self::AUTH,
			$request->get( INPUT_SERVER, 'HTTP_Authorization' ) );

		return $request;
	}

	/**
	 * @depends test_request
	 */
	public function test_security( Request $request ) {
		// Check valid request
		$this->assertTrue( $request->is_allowed() );

		// Check missing password
		$request2 = new Request(
			$request->context(),
			array(),
			array(),
			array()
		);
		$this->assertFalse( $request2->is_allowed() );

		// Check wrong password
		$request3 = new Request(
			$request->context(),
			array(),
			array(),
			array( 'HTTP_Authorization' => ' Basic Y2hyaXN0b3BoZXI6ZG9l' )
		);
		$this->assertFalse( $request3->is_allowed() );
	}

	/**
	 * @depends test_request
	 */
	public function test_user_api( Request $request ) {
		// Create example users
		$user1 = new User( $request->context()->database(),
			1,
			'John Doe',
			42 );
		$user2 = new User( $request->context()->database(),
			2,
			'Jane Doe',
			43 );
		$this->assertTrue( $user1->create() );
		$this->assertTrue( $user2->create() );

		// Query all users
		$response = Api::parse( $request, array( new Users() ) );
		$this->assertTrue( $response->is_successful() );
		$data = $response->payload();
		$this->assertEquals( array(
			Users::USER_NAME => $user1->name(),
			Users::CHAT_ID   => $user1->chat_id(),
		),
			$data[ $user1->id() ] );
		$this->assertEquals( array(
			Users::USER_NAME => $user2->name(),
			Users::CHAT_ID   => $user2->chat_id(),
		),
			$data[ $user2->id() ] );

		// Query a specific user
		$request->set( INPUT_GET, 'id', '2' );
		$response = Api::parse( $request, array( new Users() ) );
		$this->assertTrue( $response->is_successful() );
		$this->assertEquals( array(
			Users::USER_NAME => $user2->name(),
			Users::CHAT_ID   => $user2->chat_id(),
		),
			$response->payload() );

		// Query a non-existing user
		$request->set( INPUT_GET, 'id', '66' );
		$response = Api::parse( $request, array( new Users() ) );
		$this->assertFalse( $response->is_successful() );
	}
}