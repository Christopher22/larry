<?php
/**
 * This script might be used to register the available commands on Telegram.
 */

namespace larry;

use larry\commands\Yes;
use larry\commands\No;
use larry\commands\Summary;
use larry\requests\SetCommands;

require 'Context.php';

$context = Context::create();
if ( $context === null ) {
	http_response_code( 500 );
	exit( "Unable to create the context" );
}

$update_command = new SetCommands( $context, array(
	new Yes(),
	new No(),
	new Summary(),
) );
if ( ! $update_command->is_valid() ) {
	http_response_code( 500 );
	exit( 'Unable to run the update commands' );
} else {
	exit( "Updated commands successfully! :)" );
}