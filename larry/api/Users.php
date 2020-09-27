<?php


namespace larry\api;


use larry\model\User;

/**
 * A API to access the users.
 *
 * @package larry\api
 */
class Users extends Api {

	public const USER_NAME = 'name';
	public const CHAT_ID = 'chat';

	public function api_id(): string {
		return 'users';
	}

	protected function handle_get(
		Request $request,
		?int $id
	): Response {
		$result = array();
		if ( $id === null ) {
			foreach (
				User::load_all( $request->context()->database() ) as $user
			) {
				$result[ $user->id() ] = array(
					self::USER_NAME => $user->name(),
					self::CHAT_ID   => $user->chat_id(),
				);
			}
		} else {
			$user = User::load( $request->context()->database(), $id );
			if ( count( $user ) == 1 ) {
				$result[ self::USER_NAME ] = $user[0]->name();
				$result[ self::CHAT_ID ]   = $user[0]->chat_id();
			} else {
				return new Response( 404 );
			}
		}

		return new Response( 200, $result );
	}

	protected function handle_post(
		Request $context,
		int $id,
		string $key,
		$value
	): Response {
		return Response::from_error( 501, 'Updating users is not supported' );
	}


}