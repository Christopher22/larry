<?php

namespace larry\updates;

/**
 * An update sent by the Telegram server.
 *
 * @package larry\updates
 */
abstract class Update {
	protected ?array $content;
	protected ?int $id;

	/**
	 * Creates a new update.
	 *
	 * @param   string  $update  The JSON of the update.
	 * @param   string  $type    The type of update a subclass will implement.
	 */
	protected function __construct( string $update, string $type ) {
		$content = json_decode( $update, true );

		if ( is_array( $content ) && array_key_exists( $type, $content )
		     && array_key_exists( "update_id", $content ) ) {
			$this->content = $content[ $type ];
			$this->id      = $content["update_id"];
		} else {
			$this->content = null;
			$this->id      = null;
		}
	}

	/**
	 * @return bool TRUE, if the update is valid.
	 */
	public function is_valid(): bool {
		return $this->content !== null;
	}

	/**
	 * Mark this update as invalid.
	 */
	protected function invalidate() {
		$this->content = null;
	}
}