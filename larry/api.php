<?php

namespace larry;

use larry\api\Api;
use larry\api\Availabilities;
use larry\api\Request;
use larry\api\Users;

require "Context.php";

// Create the context and fail if it is invalid
$context = Context::create();
if ( $context === null ) {
	http_response_code( 500 );
	error_log( "Larry: Context was NULL in index.php" );
	exit();
}

// Create, parse, and respond the request
$request  = Request::from_server( $context );
$response = Api::parse( $request, array( new Users(), new Availabilities() ) );
$response->send();
