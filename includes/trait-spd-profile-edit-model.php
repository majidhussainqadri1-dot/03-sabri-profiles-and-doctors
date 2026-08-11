<?php
defined( 'ABSPATH' ) || exit;

trait SPD_Profile_Edit_Model {
	public function edit_model( $actor_id, $target_user_id = 0 ) {
		$actor_id = absint( $actor_id );
		$target_user_id = $target_user_id ? absint( $target_user_id ) : $actor_id;
		if ( $target_user_id !== $actor_id && ! SPD_Membership_Adapter::guardian_can_manage( $actor_id, $target_user_id ) ) {
			return new WP_Error( 'spd_profile_unavailable', __( 'This profile is unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 404 ) );
		}
		$profile = $this->find_by_user_id( $target_user_id, $target_user_id === $actor_id );
		if ( is_wp_error( $profile ) ) {
			return $profile;
		}
		if ( ! SPD_Authorization::can_edit_profile( $profile, $actor_id ) ) {
			return new WP_Error( 'spd_forbidden', __( 'You cannot edit this profile.', 'sabri-profiles-doctors' ), array( 'status' => 403 ) );
		}
		$audience_map = array();
		$values = array_intersect_key( $profile, array_flip( array( 'bio', 'country', 'city', 'languages', 'studied_books', 'locale' ) ) );
		foreach ( $profile['fields'] as $field_key => $field_row ) {
			$audience_map[ $field_key ] = $field_row['audience'];
			if ( in_array( $field_key, self::founder_fields(), true ) ) {
				$values[ $field_key ] = (string) $field_row['field_value'];
			}
		}
		$professional_submission = 'doctor' === $profile['profile_type'] ? $this->latest_professional_submission( $profile['id'] ) : array();
		if ( is_wp_error( $professional_submission ) ) { return $professional_submission; }
		return array(
			'public_id'        => $profile['public_id'],
			'target_user_id'   => $target_user_id,
			'actor_user_id'    => $actor_id,
			'version'          => $profile['version'],
			'profile_type'     => $profile['profile_type'],
			'state'            => $profile['state'],
			'values'           => $values,
			'audiences'        => $audience_map,
			'internal_message' => $profile['fields']['internal_message']['field_value'] ?? '0',
			'completeness'     => $this->completeness( $profile, SPD_Membership_Adapter::claims( $target_user_id ), SPD_Verification_Adapter::approved_fields( $target_user_id ) ),
			'professional'     => SPD_Verification_Adapter::approved_fields( $target_user_id ),
			'professional_submission' => $professional_submission,
			'professional_provider' => SPD_Verification_Adapter::health(),
		);
	}
}
