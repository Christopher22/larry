<?php

use PHPUnit\Framework\TestCase;

use larry\Context;
use larry\api\Api;
use larry\api\Availabilities;
use larry\api\Request;
use larry\api\Users;
use larry\model\User;
use larry\model\Availability;
use larry\model\Meeting;

class TestApi extends TestCase {
	private const PASSWORD = 'gundler';
	private const AUTH = ' Basic Y2hyaXN0b3BoZXI6Z3VuZGxlcg==';

	public function test_context(): Context {
		$context = Context::create( 'sqlite::memory:', '', self::PASSWORD );
		$this->assertNotNull( $context );
		$this->assertTrue( User::prepare_database( $context->database() ) );
		$this->assertTrue( Meeting::prepare_database( $context->database() ) );

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
				'HTTP_AUTHORIZATION' => self::AUTH,
				'REQUEST_METHOD'     => 'GET',
			)
		);

		$this->assertEquals( 'users', $request->get( INPUT_GET, 'api' ) );
		$this->assertEquals( 1,
			$request->filter( INPUT_POST, 'post_param', FILTER_VALIDATE_INT ) );
		$this->assertEquals( self::AUTH,
			$request->get( INPUT_SERVER, 'HTTP_AUTHORIZATION' ) );

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
			array( 'HTTP_AUTHORIZATION' => ' Basic Y2hyaXN0b3BoZXI6ZG9l' )
		);
		$this->assertFalse( $request3->is_allowed() );
	}

	/**
	 * @depends test_request
	 */
	public function test_user_api( Request $request ): Request {
		// Create example users
		$user1 = new User( $request->context()->database(),
			1,
			'John Doe',
			42 );
		$user2 = new User( $request->context()->database(),
			2,
			'Jane Doe',
			43 );
		$user3 = new User( $request->context()->database(),
			3,
			'Johnny Doe',
			44 );
		$this->assertTrue( $user1->create() );
		$this->assertTrue( $user2->create() );
		$this->assertTrue( $user3->create() );

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

		return $request;
	}

	/**
	 * @depends test_user_api
	 */
	public function test_availability_api_get( Request $request ) {
		$request->set( INPUT_GET, 'api', 'availabilities' );
		$request->remove( INPUT_GET, 'id' );

		// Load the users of the previous test and create meeting with them
		$users       = User::load_all( $request->context()->database() );
		$meeting_old = Meeting::from_date( $request->context()->database(),
			1997,
			04,
			22 );

		$meeting_now = new Meeting( $request->context()->database(),
			new DateTimeImmutable() );
		$this->assertTrue( $meeting_old->set_availability( new Availability( $users[0],
			true ) ) );
		$this->assertTrue( $meeting_now->set_availability( new Availability( $users[1],
			true ) ) );
		$this->assertTrue( $meeting_now->set_availability( new Availability( $users[2],
			false ) ) );

		// The old meeting should be filtered ...
		$response_data = $this->simulate_request( $request,
			new Availabilities() );
		$this->assertCount( 1, $response_data );
		$this->assertEquals( array(
			Availabilities::DATE           => $meeting_now->date()
			                                              ->format( DATE_ISO8601 ),
			Availabilities::AVAILABILITIES => array(
				$users[0]->id() => null,
				$users[1]->id() => true,
				$users[2]->id() => false,
			),
		),
			$response_data[ $meeting_now->date()->getTimestamp() ] );

		// ... but not, if explicitly included.
		$request->set( INPUT_GET,
			Availabilities::MIN_DATE,
			$meeting_old->date()->getTimestamp() );
		$response_data = $this->simulate_request( $request,
			new Availabilities() );
		$this->assertCount( 2, $response_data );

		return $request;
	}

	/**
	 * @depends test_availability_api_get
	 */
	public function test_availability_api_post( Request $request ) {
		$users          = User::load_all( $request->context()->database() );
		$meeting        = Meeting::load_all( $request->context()
		                                             ->database() )[1];
		$availabilities = $meeting->availabilities( true );
		$this->assertNull( $availabilities[0]->is_available() );
		$this->assertTrue( $availabilities[1]->is_available() );
		$this->assertFalse( $availabilities[2]->is_available() );

		$post_inputs = array(
			$users[0]->id() => 'true',
			$users[1]->id() => 'false',
			$users[2]->id() => 'null',
		);
		$request->set( INPUT_SERVER, 'REQUEST_METHOD', 'POST' );
		$request->set( INPUT_GET,
			'id',
			strval( $meeting->date()->getTimestamp() ) );
		foreach ( $post_inputs as $key => $value ) {
			$request->set( INPUT_POST, Api::KEY_NAME, strval( $key ) );
			$request->set( INPUT_POST, Api::VALUE_NAME, $value );

			$result = Api::parse( $request, array( new Availabilities() ) );
			$this->assertTrue( $result->is_successful(), $result );
		}

		// Check availabilities were updated
		$availabilities = $meeting->availabilities( true );
		$this->assertTrue( $availabilities[0]->is_available() );
		$this->assertFalse( $availabilities[1]->is_available() );
		$this->assertNull( $availabilities[2]->is_available() );
	}

	private function simulate_request( Request $request, Api $api ): array {
		$response = Api::parse( $request, array( $api ) );
		$this->assertTrue( $response->is_successful() );

		return $response->payload();
	}
}