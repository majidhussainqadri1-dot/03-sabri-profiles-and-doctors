<?php
defined( 'ABSPATH' ) || exit;

trait SPD_Frontend_Report {
	public function report_form() {
		if ( ! is_user_logged_in() ) {
			return $this->notice( __( 'Log in to report a profile.', 'sabri-profiles-doctors' ), 'warning' );
		}
		$public_id = sanitize_text_field( (string) get_query_var( 'spd_public_id' ) );
		$dto = SPD_Profile_Repository::instance()->public_dto( $public_id, get_current_user_id() );
		if ( is_wp_error( $dto ) ) {
			return $this->notice( $dto->get_error_message(), 'error' );
		}
		ob_start(); ?>
		<main class="spd" aria-labelledby="spd-report-title"><header class="spd-page-header"><h1 id="spd-report-title"><?php echo esc_html( sprintf( __( 'Report %s', 'sabri-profiles-doctors' ), $dto['display_name'] ) ); ?></h1><p><?php esc_html_e( 'Reports require a specific reason and are reviewed proportionately. False or abusive reports are prohibited.', 'sabri-profiles-doctors' ); ?></p></header><?php if ( isset( $_GET['submitted'] ) ) : ?><div class="spd-notice spd-notice--success" role="status"><?php esc_html_e( 'Your report was submitted.', 'sabri-profiles-doctors' ); ?></div><?php endif; ?><form class="spd-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="spd_report_profile"><input type="hidden" name="public_id" value="<?php echo esc_attr( $public_id ); ?>"><input type="hidden" name="idempotency_key" value="<?php echo esc_attr( wp_generate_uuid4() ); ?>"><?php wp_nonce_field( 'spd_report_profile', 'spd_nonce' ); ?><label><?php esc_html_e( 'Reason', 'sabri-profiles-doctors' ); ?><select name="reason" required><option value=""><?php esc_html_e( 'Choose a reason', 'sabri-profiles-doctors' ); ?></option><option value="impersonation"><?php esc_html_e( 'Impersonation', 'sabri-profiles-doctors' ); ?></option><option value="harassment"><?php esc_html_e( 'Harassment', 'sabri-profiles-doctors' ); ?></option><option value="false_qualification"><?php esc_html_e( 'False qualification', 'sabri-profiles-doctors' ); ?></option><option value="unsafe_media"><?php esc_html_e( 'Unsafe media', 'sabri-profiles-doctors' ); ?></option><option value="privacy_breach"><?php esc_html_e( 'Privacy breach', 'sabri-profiles-doctors' ); ?></option><option value="other"><?php esc_html_e( 'Other', 'sabri-profiles-doctors' ); ?></option></select></label><label><?php esc_html_e( 'Details', 'sabri-profiles-doctors' ); ?><textarea name="details" rows="6" maxlength="3000" required></textarea></label><button class="spd-btn" type="submit"><?php esc_html_e( 'Submit report', 'sabri-profiles-doctors' ); ?></button></form></main>
		<?php return ob_get_clean();
	}

	public function report() {
		if ( ! is_user_logged_in() || empty( $_POST['spd_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['spd_nonce'] ) ), 'spd_report_profile' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'sabri-profiles-doctors' ), '', array( 'response' => 403 ) );
		}
		$public_id = sanitize_text_field( wp_unslash( $_POST['public_id'] ?? '' ) );
		$result = SPD_Profile_Repository::instance()->create_report( $public_id, get_current_user_id(), wp_unslash( $_POST['reason'] ?? '' ), wp_unslash( $_POST['details'] ?? '' ), sanitize_text_field( wp_unslash( $_POST['idempotency_key'] ?? '' ) ) );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => $this->error_status( $result ), 'back_link' => true ) );
		}
		wp_safe_redirect( add_query_arg( 'submitted', '1', SPD_Helpers::report_url( $public_id ) ) );
		exit;
	}

	public function reject_anonymous() {
		wp_die( esc_html__( 'Log in to report a profile.', 'sabri-profiles-doctors' ), '', array( 'response' => 401 ) );
	}

	public function directory_compatibility() {
		$html = apply_filters( 'sabri_file07_doctor_directory_html_v1', '', array( 'profile_provider' => 'spd_get_public_profile', 'contract_version' => SPD_CONTRACT_VERSION ) );
		return $html ? wp_kses_post( $html ) : $this->notice( __( 'The Doctors Directory is provided by File 07 and is currently unavailable. File 03 does not duplicate directory search or ranking.', 'sabri-profiles-doctors' ), 'warning' );
	}

	public function structured_data() {
		$public_id = sanitize_text_field( (string) get_query_var( 'spd_public_id' ) );
		if ( ! $public_id || 'report' === get_query_var( 'spd_view' ) ) {
			return;
		}
		$dto = SPD_Profile_Repository::instance()->public_dto( $public_id, 0 );
		if ( is_wp_error( $dto ) ) {
			return;
		}
		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => 'doctor' === $dto['profile_type'] ? 'Physician' : 'Person',
			'name'     => $dto['display_name'],
			'url'      => $dto['canonical_url'],
		);
		if ( ! empty( $dto['media']['avatar']['url'] ) ) {
			$schema['image'] = $dto['media']['avatar']['url'];
		}
		if ( ! empty( $dto['fields']['country'] ) ) {
			$schema['address'] = array( '@type' => 'PostalAddress', 'addressCountry' => $dto['fields']['country'], 'addressLocality' => $dto['fields']['city'] ?? '' );
		}
		echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . '</script>' . "\n";
	}

}
