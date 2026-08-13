<?php
defined( 'ABSPATH' ) || exit;

trait SPD_Profile_Public_DTO {
	public function public_dto( $identity, $viewer_id = 0 ) {
		global $wpdb;
		$viewer_id = absint( $viewer_id );
		$wpdb->last_error = '';
		$profile = is_numeric( $identity ) ? $this->find_by_user_id( absint( $identity ), false ) : $this->find_by_public_id( (string) $identity );
		if ( is_wp_error( $profile ) ) { return $profile; }
		if ( $wpdb->last_error || ( is_array( $profile ) && ! empty( $profile['_fields_read_failed'] ) ) ) {
			return new WP_Error( 'spd_profile_store_unavailable', __( 'The profile store is temporarily unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) );
		}
		if ( $profile && 'tombstoned' === ( $profile['state'] ?? '' ) ) {
			return new WP_Error( 'spd_profile_gone', __( 'This profile has been removed.', 'sabri-profiles-doctors' ), array( 'status' => 410 ) );
		}
		if ( ! $profile || ! SPD_Authorization::profile_visibility_allows( $profile, $viewer_id ) ) {
			return new WP_Error( 'spd_profile_unavailable', __( 'This profile is private or unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 404 ) );
		}
		if ( 0 === $viewer_id ) {
			$cached = $this->get_anonymous_public_dto_cache( $profile );
			if ( is_array( $cached ) ) { return $cached; }
		}

		$user_id = absint( $profile['user_id'] );
		$claims = SPD_Membership_Adapter::claims( $user_id );
		if ( ! $claims ) {
			return new WP_Error( 'spd_identity_dependency_unavailable', __( 'Current identity assertions are temporarily unavailable.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) );
		}
		$verification = SPD_Verification_Adapter::projection( $user_id );
		$is_verified_doctor = 'doctor' === $profile['profile_type'] && 'doctor' === ( $claims['account_type'] ?? '' ) && ! empty( $claims['approved'] ) && ! empty( $claims['eligible'] ) && ! empty( $claims['professional_verified'] ) && empty( $claims['suspended'] ) && $verification && ! empty( $verification['trusted_contract'] ) && 'verified' === ( $verification['status'] ?? '' );
		$professional = $is_verified_doctor ? ( $verification['approved_fields'] ?? array() ) : array();
		$clinic_raw = null;
		if ( $is_verified_doctor ) {
			try {
				$clinic_raw = apply_filters( 'sabri_file08_public_clinic_projection_v1', null, $user_id, $viewer_id, SPD_CONTRACT_VERSION );
			} catch ( Throwable $exception ) {
				try {
					do_action( 'sabri_file24_profile_provider_failure', array(
						'owner'           => 'file03',
						'provider'        => 'file08_public_clinic',
						'surface'         => 'public_profile_dto',
						'exception_class' => sanitize_key( get_class( $exception ) ),
						'at'              => SPD_Helpers::now(),
					) );
				} catch ( Throwable $ignored ) {}
				$clinic_raw = null;
			}
		}
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
			'badge'            => $this->badge( $profile, $verification, $claims, $is_verified_doctor ),
			'fields'           => array(),
			'media'            => $this->public_media( $profile ),
			'contacts'         => array(),
			'professional'     => $professional,
			'founder'          => array(),
			'clinic'           => $this->normalize_clinic( $clinic_raw, $user_id ),
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
		$is_minor = ! empty( $claims['is_minor'] );
		foreach ( array( 'phone', 'email', 'whatsapp' ) as $key ) {
			$field = $profile['fields'][ $key ] ?? array( 'audience' => 'private' );
			if ( $is_minor ) { continue; }
			if ( SPD_Authorization::audience_allows( $field['audience'], $user_id, $viewer_id ) ) {
				$value = SPD_Membership_Adapter::contact( $user_id, $key );
				if ( $value ) { $dto['contacts'][ $key ] = $value; }
			}
		}
		$internal = $profile['fields']['internal_message'] ?? array( 'audience' => 'private', 'field_value' => '0' );
		if ( '1' === (string) $internal['field_value'] && SPD_Authorization::audience_allows( $internal['audience'], $user_id, $viewer_id ) && ! $is_minor ) {
			$url = (string) apply_filters( 'sabri_network_message_profile_url', '', $user_id, $viewer_id, SPD_CONTRACT_VERSION );
			if ( SPD_Helpers::same_origin_url( $url ) ) { $dto['contacts']['internal_message_url'] = esc_url_raw( $url ); }
		}
		if ( 0 === $viewer_id ) { $this->set_anonymous_public_dto_cache( $profile, $dto ); }
		return $dto;
	}

	private function normalize_clinic( $clinic, $user_id ) {
		if ( ! SPD_Helpers::current_contract_claim( $clinic, '1.0.0', 600 ) || absint( $clinic['doctor_user_id'] ?? 0 ) !== absint( $user_id ) || empty( $clinic['owner_version'] ) || 'active' !== sanitize_key( (string) ( $clinic['status'] ?? '' ) ) || 'public' !== sanitize_key( (string) ( $clinic['visibility'] ?? '' ) ) ) { return array(); }
		$out = array();
		foreach ( array( 'name', 'country', 'city', 'consultation_modes', 'languages' ) as $key ) {
			if ( ! isset( $clinic[ $key ] ) ) { continue; }
			if ( is_array( $clinic[ $key ] ) ) {
				$values = array();
				foreach ( array_slice( $clinic[ $key ], 0, 50 ) as $value ) {
					if ( is_scalar( $value ) ) {
						$value = sanitize_text_field( (string) $value );
						if ( '' !== $value ) { $values[] = $value; }
					}
				}
				if ( $values ) { $out[ $key ] = implode( ', ', array_values( array_unique( $values ) ) ); }
			} elseif ( is_scalar( $clinic[ $key ] ) ) {
				$value = sanitize_text_field( (string) $clinic[ $key ] );
				if ( '' !== $value ) { $out[ $key ] = $value; }
			}
		}
		if ( ! empty( $clinic['url'] ) && SPD_Helpers::same_origin_url( (string) $clinic['url'] ) ) { $out['url'] = esc_url_raw( $clinic['url'] ); }
		$out['owner_version'] = sanitize_text_field( (string) ( $clinic['owner_version'] ?? '' ) );
		return $out;
	}

	private function public_media( array $profile ) {
		if ( 'public' !== ( $profile['profile_visibility'] ?? 'private' ) ) { return array(); }
		$out = array();
		foreach ( array( 'avatar' => 'avatar_id', 'cover' => 'cover_id' ) as $purpose => $column ) {
			$id = absint( $profile[ $column ] ?? 0 );
			if ( ! $id || 'active' !== SPD_Media::state( $profile['id'], $purpose ) ) { continue; }
			if ( absint( get_post_meta( $id, SPD_Media::OWNER_META, true ) ) !== absint( $profile['user_id'] ) || sanitize_key( (string) get_post_meta( $id, SPD_Media::PURPOSE_META, true ) ) !== $purpose || 'active' !== sanitize_key( (string) get_post_meta( $id, SPD_Media::STATE_META, true ) ) ) { continue; }
			$url = wp_get_attachment_image_url( $id, 'large' );
			if ( ! $url || ! SPD_Helpers::same_origin_url( $url ) ) { continue; }
			$out[ $purpose ] = array(
				'url'           => esc_url_raw( $url ),
				'alt'           => sanitize_text_field( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ),
				'focal_x'       => (float) $profile[ $purpose . '_focal_x' ],
				'focal_y'       => (float) $profile[ $purpose . '_focal_y' ],
			);
		}
		return $out;
	}

	private function badge( array $profile, array $verification, array $claims, $is_verified_doctor ) {
		if ( 'founder' === $profile['profile_type'] && ! empty( $claims['is_founder'] ) ) { return array( 'key' => 'official_founder', 'label' => __( 'Official Founder', 'sabri-profiles-doctors' ), 'verified' => true ); }
		if ( $is_verified_doctor ) { return array( 'key' => 'verified_doctor', 'label' => __( 'Verified Doctor', 'sabri-profiles-doctors' ), 'verified' => true, 'claim_version' => $verification['claim_version'] ?? '', 'contract_version' => $verification['contract_version'] ?? '' ); }
		return array( 'key' => 'member', 'label' => __( 'Member', 'sabri-profiles-doctors' ), 'verified' => false );
	}
}
