<?php
defined( 'ABSPATH' ) || exit;

trait SPD_Profile_Public_DTO {
	public function public_dto( $identity, $viewer_id = 0 ) {
		$profile = is_numeric( $identity ) ? $this->find_by_user_id( absint( $identity ) ) : $this->find_by_public_id( (string) $identity );
		if ( is_wp_error( $profile ) ) {
			return $profile;
		}
		if ( $profile && 'tombstoned' === ( $profile['state'] ?? '' ) ) {
			return new WP_Error( 'spd_profile_gone', __( 'This profile has been removed.', 'sabri-profiles-doctors' ), array( 'status' => 410 ) );
		}
		if ( ! $profile || ! SPD_Authorization::profile_visibility_allows( $profile, $viewer_id ) ) {
			return new WP_Error( 'spd_profile_unavailable', __( 'This profile is private or unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 404 ) );
		}
		$user_id = absint( $profile['user_id'] );
		$claims = SPD_Membership_Adapter::claims( $user_id );
		$verification = SPD_Verification_Adapter::projection( $user_id );
		$professional = ( 'doctor' === $profile['profile_type'] && SPD_Verification_Adapter::is_verified( $user_id ) ) ? ( $verification['approved_fields'] ?? array() ) : array();
		$dto = array(
			'contract_version' => SPD_CONTRACT_VERSION,
			'public_id'        => $profile['public_id'],
			'canonical_url'    => SPD_Helpers::canonical_profile_url( $profile['public_id'] ),
			'timeline_url'     => SPD_Helpers::timeline_url( $profile['public_id'] ),
			'report_url'       => SPD_Helpers::report_url( $profile['public_id'] ),
			'profile_type'     => $profile['profile_type'],
			'state'            => $profile['state'],
			'version'          => $profile['version'],
			'display_name'     => $claims['display_name'] ?? '',
			'locale'           => $profile['locale'],
			'badge'            => $this->badge( $profile, $verification ),
			'fields'           => array(),
			'media'            => $this->public_media( $profile ),
			'contacts'         => array(),
			'professional'     => $professional,
			'founder'          => array(),
			'clinic'           => $this->normalize_clinic( apply_filters( 'sabri_file08_public_clinic_projection_v1', array(), $user_id, $viewer_id ) ),
			'completeness'     => $viewer_id === $user_id ? $this->completeness( $profile, $claims, $professional ) : null,
		);

		foreach ( array( 'bio', 'country', 'city', 'languages', 'studied_books' ) as $key ) {
			$field = $profile['fields'][ $key ] ?? array( 'audience' => 'private' );
			if ( SPD_Authorization::audience_allows( $field['audience'], $user_id, $viewer_id ) ) {
				$dto['fields'][ $key ] = (string) ( $profile[ $key ] ?? '' );
			}
		}
		if ( 'founder' === $profile['profile_type'] ) {
			foreach ( self::founder_fields() as $key ) {
				$field = $profile['fields'][ $key ] ?? array( 'audience' => 'public', 'field_value' => '' );
				if ( SPD_Authorization::audience_allows( $field['audience'], $user_id, $viewer_id ) && '' !== trim( (string) $field['field_value'] ) ) {
					$dto['founder'][ $key ] = (string) $field['field_value'];
				}
			}
		}
		foreach ( array( 'phone', 'email', 'whatsapp' ) as $key ) {
			$field = $profile['fields'][ $key ] ?? array( 'audience' => 'private' );
			if ( SPD_Membership_Adapter::is_minor( $user_id ) ) {
				continue;
			}
			if ( SPD_Authorization::audience_allows( $field['audience'], $user_id, $viewer_id ) ) {
				$value = SPD_Membership_Adapter::contact( $user_id, $key );
				if ( $value ) {
					$dto['contacts'][ $key ] = $value;
				}
			}
		}
		$internal = $profile['fields']['internal_message'] ?? array( 'audience' => 'private', 'field_value' => '0' );
		if ( '1' === (string) $internal['field_value'] && SPD_Authorization::audience_allows( $internal['audience'], $user_id, $viewer_id ) && ! SPD_Membership_Adapter::is_minor( $user_id ) ) {
			$dto['contacts']['internal_message_url'] = (string) apply_filters( 'sabri_network_message_profile_url', '', $user_id );
		}
		$dto = apply_filters( 'spd_public_profile_dto_v1', $dto, $profile, $viewer_id );
		return $dto;
	}

	private function normalize_clinic( $clinic ) {
		if ( ! is_array( $clinic ) ) {
			return array();
		}
		$out = array();
		foreach ( array( 'name', 'country', 'city', 'consultation_modes', 'languages' ) as $key ) {
			if ( isset( $clinic[ $key ] ) ) {
				$out[ $key ] = sanitize_text_field( (string) $clinic[ $key ] );
			}
		}
		if ( ! empty( $clinic['url'] ) ) {
			$out['url'] = esc_url_raw( $clinic['url'] );
		}
		return $out;
	}

	private function public_media( array $profile ) {
		$out = array();
		foreach ( array( 'avatar' => 'avatar_id', 'cover' => 'cover_id' ) as $purpose => $column ) {
			$id = absint( $profile[ $column ] ?? 0 );
			if ( ! $id ) {
				continue;
			}
			$state = SPD_Media::state( $profile['id'], $purpose );
			if ( 'active' !== $state ) {
				continue;
			}
			$out[ $purpose ] = array(
				'attachment_id' => $id,
				'url'           => wp_get_attachment_image_url( $id, 'large' ),
				'alt'           => get_post_meta( $id, '_wp_attachment_image_alt', true ),
				'focal_x'       => (float) $profile[ $purpose . '_focal_x' ],
				'focal_y'       => (float) $profile[ $purpose . '_focal_y' ],
			);
		}
		return $out;
	}

	private function badge( array $profile, array $verification ) {
		if ( 'founder' === $profile['profile_type'] && SPD_Membership_Adapter::is_founder( $profile['user_id'] ) ) {
			return array( 'key' => 'official_founder', 'label' => __( 'Official Founder', 'sabri-profiles-doctors' ), 'verified' => true );
		}
		if ( 'doctor' === $profile['profile_type'] && SPD_Verification_Adapter::is_verified( $profile['user_id'] ) ) {
			return array(
				'key'           => 'verified_doctor',
				'label'         => __( 'Verified Doctor', 'sabri-profiles-doctors' ),
				'verified'      => true,
				'claim_version' => $verification['claim_version'] ?? '',
			);
		}
		return array( 'key' => 'member', 'label' => __( 'Member', 'sabri-profiles-doctors' ), 'verified' => false );
	}

}
