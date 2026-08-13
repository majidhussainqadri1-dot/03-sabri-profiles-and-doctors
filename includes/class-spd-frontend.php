<?php
defined( 'ABSPATH' ) || exit;

final class SPD_Frontend {
	use SPD_Frontend_Profile;
	use SPD_Frontend_Timeline;
	use SPD_Frontend_Edit;
	use SPD_Frontend_Helpers;
	use SPD_Frontend_Future;
	use SPD_Frontend_Report, SPD_Frontend_Central {
		SPD_Frontend_Central::structured_data insteadof SPD_Frontend_Report;
		SPD_Frontend_Central::structured_data as private central_structured_data;
		SPD_Frontend_Central::personal_site_settings as private central_personal_site_settings;
		SPD_Frontend_Central::grant_delegate_post as private legacy_grant_delegate_post;
		SPD_Frontend_Central::revoke_delegate_post as private legacy_revoke_delegate_post;
		SPD_Frontend_Report::structured_data as legacy_structured_data;
	}

	public function hooks() {
		add_shortcode( 'sabri_founder_profile', array( $this, 'founder' ) );
		add_shortcode( 'sabri_member_profile', array( $this, 'profile' ) );
		add_shortcode( 'sabri_profile_router', array( $this, 'profile_router' ) );
		add_shortcode( 'sabri_edit_profile', array( $this, 'edit' ) );
		add_shortcode( 'sabri_profile_personal_site', array( $this, 'personal_site_settings' ) );
		add_shortcode( 'sabri_profile_private_preview', array( $this, 'private_preview' ) );
		add_shortcode( 'sabri_doctor_directory', array( $this, 'directory_compatibility' ) );
		add_action( 'admin_post_spd_save_profile', array( $this, 'save' ) );
		add_action( 'admin_post_spd_submit_professional_fields', array( $this, 'save_professional' ) );
		add_action( 'admin_post_spd_save_central_profile', array( $this, 'save_central_profile' ) );
		add_action( 'admin_post_spd_grant_delegate', array( $this, 'grant_delegate_post' ) );
		add_action( 'admin_post_spd_revoke_delegate', array( $this, 'revoke_delegate_post' ) );
		add_action( 'admin_post_spd_rotate_share', array( $this, 'rotate_share_post' ) );
		add_action( 'admin_post_spd_report_profile', array( $this, 'report' ) );
		add_action( 'admin_post_nopriv_spd_report_profile', array( $this, 'reject_anonymous' ) );
		add_action( 'wp_head', array( $this, 'structured_data' ), 20 );
	}

	/**
	 * Preserve the plan-owned Central renderer while adding one unique replay key
	 * to every browser delegation mutation. This keeps the compatibility trait
	 * untouched and gives non-JavaScript form submissions the same idempotency
	 * contract as REST/browser-script mutations.
	 */
	public function personal_site_settings() {
		$html = $this->central_personal_site_settings();
		if ( ! is_string( $html ) || '' === $html ) { return $html; }
		$grant = '<input type="hidden" name="action" value="spd_grant_delegate">';
		if ( false !== strpos( $html, $grant ) ) {
			$html = str_replace( $grant, $grant . '<input type="hidden" name="idempotency_key" value="' . esc_attr( wp_generate_uuid4() ) . '">', $html );
		}
		$revoke_pattern = '/(<input type="hidden" name="action" value="spd_revoke_delegate">)/';
		$html = preg_replace_callback(
			$revoke_pattern,
			static function( $match ) {
				return $match[1] . '<input type="hidden" name="idempotency_key" value="' . esc_attr( wp_generate_uuid4() ) . '">';
			},
			$html
		);
		return is_string( $html ) ? $html : $this->central_personal_site_settings();
	}

	public function grant_delegate_post() {
		if ( ! is_user_logged_in() || empty( $_POST['spd_delegate_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['spd_delegate_nonce'] ) ), 'spd_grant_delegate' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'sabri-profiles-doctors' ), '', array( 'response' => 403 ) );
		}
		$scopes = isset( $_POST['scopes'] ) && is_array( $_POST['scopes'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['scopes'] ) ) : array();
		$result = SPD_Profile_Repository::instance()->grant_delegate(
			get_current_user_id(),
			absint( $_POST['delegate_user_id'] ?? 0 ),
			$scopes,
			sanitize_text_field( wp_unslash( $_POST['expires_at'] ?? '' ) ),
			sanitize_text_field( wp_unslash( $_POST['idempotency_key'] ?? '' ) )
		);
		if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => $this->error_status( $result ), 'back_link' => true ) ); }
		wp_safe_redirect( home_url( '/account/profile/personal-site/' ) );
		exit;
	}

	public function revoke_delegate_post() {
		$delegate = absint( $_POST['delegate_user_id'] ?? 0 );
		if ( ! is_user_logged_in() || ! $delegate || empty( $_POST['spd_revoke_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['spd_revoke_nonce'] ) ), 'spd_revoke_delegate_' . $delegate ) ) {
			wp_die( esc_html__( 'Security check failed.', 'sabri-profiles-doctors' ), '', array( 'response' => 403 ) );
		}
		$result = SPD_Profile_Repository::instance()->revoke_delegate(
			get_current_user_id(),
			$delegate,
			sanitize_text_field( wp_unslash( $_POST['idempotency_key'] ?? '' ) )
		);
		if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => $this->error_status( $result ), 'back_link' => true ) ); }
		wp_safe_redirect( home_url( '/account/profile/personal-site/' ) );
		exit;
	}

	public function structured_data() {
		try {
			$this->central_structured_data();
		} catch ( Throwable $exception ) {
			do_action( 'sabri_file24_profile_provider_failure', array(
				'owner' => 'file03',
				'surface' => 'frontend_structured_data',
				'exception_class' => sanitize_key( get_class( $exception ) ),
				'at' => SPD_Helpers::now(),
			) );
		}
	}
}
