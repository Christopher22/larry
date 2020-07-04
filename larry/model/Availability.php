<?php


namespace larry\model;

/**
 * The availability of an user at a specific meeting.
 *
 * @package larry\model
 */
class Availability {
	private User $user;
	private ?bool $is_available;

	/**
	 * Creates a new availability.
	 *
	 * @param   User       $user          The user.
	 * @param   bool|null  $is_available  TRUE if present, FALSE if absent, or NULL if confirmation is missing.
	 */
	public function __construct( User $user, ?bool $is_available ) {
		$this->user         = $user;
		$this->is_available = $is_available;
	}

	/**
	 * @return User The user.
	 */
	public function user(): User {
		return $this->user;
	}

	/**
	 * @return bool|null TRUE if present, FALSE if absent, or NULL if confirmation is missing.
	 */
	public function is_available(): ?bool {
		return $this->is_available;
	}
}