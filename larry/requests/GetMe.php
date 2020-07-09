<?php


namespace larry\requests;


use larry\Context;

class GetMe extends Request {

	public function __construct(
		Context $context
	) {
		parent::__construct( $context, 'getMe', array() );
	}
}