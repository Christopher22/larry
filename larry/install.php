<?php

namespace larry;

use larry\model\Meeting;
use larry\model\User;
use larry\requests\GetMe;

require "Context.php";

$context = Context::create();
if ( $context === null ) {
	http_response_code( 500 );
	exit( "Unable to create the context" );
}

if ( User::prepare_database( $context->database() ) === false ) {
	http_response_code( 500 );
	exit( "Unable to create the User database" );
}

if ( Meeting::prepare_database( $context->database() ) === false ) {
	http_response_code( 500 );
	exit( "Unable to create the Meeting database" );
}

$test_command = new GetMe( $context );
if ( ! $test_command->is_valid() ) {
	http_response_code( 500 );
	exit( "Unable to run the test command: "
	      . json_encode( $test_command->response ) );
} else {
	exit( "Successfully installed! :)" );
}
