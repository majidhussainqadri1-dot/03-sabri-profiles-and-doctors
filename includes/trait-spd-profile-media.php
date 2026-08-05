<?php
defined( 'ABSPATH' ) || exit;

trait SPD_Profile_Media {
	/**
	 * Standalone media mutation was retired because it could split profile text,
	 * visibility and image changes across separate commits. All media changes now
	 * go through update_profile(), which enforces idempotency, optimistic locking,
	 * privacy tightening and atomic outbox persistence.
	 */
	public function attach_media() {
		return new WP_Error(
			'spd_standalone_media_command_retired',
			__( 'Use the atomic profile update command for avatar or cover changes.', 'sabri-profiles-doctors' ),
			array( 'status' => 410 )
		);
	}
}
