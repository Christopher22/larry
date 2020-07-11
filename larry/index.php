<?php

namespace larry;

use larry\commands\No;
use larry\commands\Start;
use larry\commands\Yes;
use larry\commands\Summary;
use larry\commands\Command;
use larry\updates\Message;

require "Context.php";

# Read the request send
$content = file_get_contents( "php://input" );
if ( $content === false ) {
	exit();
}

// Parse the message or abort if invalid
$message = new Message( $content );
if ( ! $message->is_valid() ) {
	exit();
}

// Create the context and delete if invalid
$context = Context::create();
if ( $context === null ) {
	http_response_code( 500 );
	error_log( "Larry: Context was NULL in index.php" );
	exit();
}

// Parse the commands in the message
$sender  = $message->sender( $context );
$results = Command::parse(
	$context,
	$message,
	$sender->exists() ? array( new Yes(), new No(), new Start(), new Summary() )
		: array( new Start() )
);

// Report all the results
foreach ( $results as $result ) {
	if ( ! $result->send( $context, $sender ) ) {
		error_log( "Larry: Unable to send message" );
	}
}
