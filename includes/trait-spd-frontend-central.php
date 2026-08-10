<?php
defined( 'ABSPATH' ) || exit;

trait SPD_Frontend_Central {
	public function personal_site_settings() {
		if ( ! is_user_logged_in() ) { return $this->notice( __( 'Please log in to manage your professional profile.', 'sabri-profiles-doctors' ), 'warning' ); }
		$model = SPD_Profile_Repository::instance()->central_edit_model( get_current_user_id() );
		if ( is_wp_error( $model ) ) { return $this->notice( $model->get_error_message(), 'error' ); }
		$labels = SPD_Central_Profile::extended_labels();
		ob_start(); ?>
		<main class="spd" aria-labelledby="spd-personal-site-title">
			<header class="spd-page-header">
				<h1 id="spd-personal-site-title"><?php esc_html_e( 'Personal Website Profile', 'sabri-profiles-doctors' ); ?></h1>
				<p><?php esc_html_e( 'Manage File 03 presentation fields only. Verification, appointments, reviews, directory ranking and search ranking remain with their canonical owners.', 'sabri-profiles-doctors' ); ?></p>
			</header>
			<?php if ( isset( $_GET['spd_central_updated'] ) ) : ?><div class="spd-notice spd-notice--success" role="status"><?php esc_html_e( 'Personal website profile saved.', 'sabri-profiles-doctors' ); ?></div><?php endif; ?>
			<div class="spd-actions">
				<a class="spd-btn spd-btn--secondary" href="<?php echo esc_url( $model['canonical_url'] ); ?>"><?php esc_html_e( 'Open public profile', 'sabri-profiles-doctors' ); ?></a>
				<a class="spd-btn spd-btn--secondary" href="<?php echo esc_url( home_url( '/account/profile/preview/' ) ); ?>"><?php esc_html_e( 'Private preview', 'sabri-profiles-doctors' ); ?></a>
			</div>
			<section class="spd-card"><h2><?php esc_html_e( 'Share and QR', 'sabri-profiles-doctors' ); ?></h2><p class="spd-share-url" dir="ltr"><code><?php echo esc_html( $model['share_url'] ); ?></code></p><div class="spd-actions"><button type="button" class="spd-btn spd-btn--secondary" data-spd-share data-url="<?php echo esc_url( $model['share_url'] ); ?>"><?php esc_html_e( 'Share profile', 'sabri-profiles-doctors' ); ?></button><button type="button" class="spd-btn spd-btn--secondary" data-spd-copy data-url="<?php echo esc_url( $model['share_url'] ); ?>"><?php esc_html_e( 'Copy link', 'sabri-profiles-doctors' ); ?></button></div><div class="spd-qr" data-spd-qr data-url="<?php echo esc_url( $model['share_url'] ); ?>" aria-label="<?php esc_attr_e( 'QR code for this profile', 'sabri-profiles-doctors' ); ?>"></div></section>
			<form class="spd-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="spd_save_central_profile">
				<input type="hidden" name="target_user_id" value="<?php echo esc_attr( $model['target_user_id'] ); ?>">
				<input type="hidden" name="version" value="<?php echo esc_attr( $model['version'] ); ?>">
				<input type="hidden" name="idempotency_key" value="<?php echo esc_attr( wp_generate_uuid4() ); ?>">
				<?php wp_nonce_field( 'spd_save_central_profile', 'spd_central_nonce' ); ?>
				<label><?php esc_html_e( 'Custom public profile alias', 'sabri-profiles-doctors' ); ?><input name="custom_slug" value="<?php echo esc_attr( $model['custom_slug'] ); ?>" maxlength="160" pattern="[A-Za-z0-9-]+" aria-describedby="spd-slug-help"></label><p id="spd-slug-help" class="spd-meta"><?php esc_html_e( 'This creates a memorable /u/… alias while the immutable profile UUID remains canonical.', 'sabri-profiles-doctors' ); ?></p>
				<?php foreach ( $labels as $key => $label ) : ?>
					<?php echo $this->textarea_with_audience( $key, $label, $model['values'][ $key ] ?? '', $model['audiences'][ $key ] ?? 'private', 3000 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endforeach; ?>
				<button class="spd-btn" type="submit"><?php esc_html_e( 'Save personal website profile', 'sabri-profiles-doctors' ); ?></button>
			</form>
			<?php if ( ! $model['delegated'] ) : ?>
			<section class="spd-card" aria-labelledby="spd-delegation-title"><h2 id="spd-delegation-title"><?php esc_html_e( 'Delegated management', 'sabri-profiles-doctors' ); ?></h2><p><?php esc_html_e( 'A verified adult doctor may authorize an eligible assistant to manage presentation fields. Credentials and medical messages are never delegated here; clinic schedule authority remains subject to File 08 revalidation.', 'sabri-profiles-doctors' ); ?></p>
				<form class="spd-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post"><input type="hidden" name="action" value="spd_grant_delegate"><?php wp_nonce_field( 'spd_grant_delegate', 'spd_delegate_nonce' ); ?><label><?php esc_html_e( 'Delegate user ID', 'sabri-profiles-doctors' ); ?><input name="delegate_user_id" inputmode="numeric" pattern="[0-9]+" required></label><fieldset><legend><?php esc_html_e( 'Allowed scopes', 'sabri-profiles-doctors' ); ?></legend><label class="spd-check"><input type="checkbox" name="scopes[]" value="profile_presentation" checked> <?php esc_html_e( 'Profile presentation fields', 'sabri-profiles-doctors' ); ?></label><label class="spd-check"><input type="checkbox" name="scopes[]" value="clinic_schedule_request"> <?php esc_html_e( 'Clinic schedule request authority (File 08 must revalidate)', 'sabri-profiles-doctors' ); ?></label></fieldset><label><?php esc_html_e( 'Optional expiry (UTC)', 'sabri-profiles-doctors' ); ?><input type="datetime-local" name="expires_at"></label><button class="spd-btn" type="submit"><?php esc_html_e( 'Grant limited access', 'sabri-profiles-doctors' ); ?></button></form>
				<?php if ( $model['delegations'] ) : ?><ul class="spd-delegation-list"><?php foreach ( $model['delegations'] as $delegate ) : ?><li><strong><?php echo esc_html( $delegate['display_name'] ); ?></strong> — <?php echo esc_html( implode( ', ', $delegate['scopes'] ) ); ?><form class="spd-inline-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post"><input type="hidden" name="action" value="spd_revoke_delegate"><input type="hidden" name="delegate_user_id" value="<?php echo esc_attr( $delegate['user_id'] ); ?>"><?php wp_nonce_field( 'spd_revoke_delegate_' . $delegate['user_id'], 'spd_revoke_nonce' ); ?><button class="spd-btn spd-btn--secondary" type="submit"><?php esc_html_e( 'Revoke', 'sabri-profiles-doctors' ); ?></button></form></li><?php endforeach; ?></ul><?php endif; ?>
			</section>
			<?php endif; ?>
			<?php if ( $model['analytics'] ) : ?><section class="spd-card"><h2><?php esc_html_e( 'Privacy-minimized profile analytics', 'sabri-profiles-doctors' ); ?></h2><dl class="spd-stats"><?php foreach ( $model['analytics'] as $key => $value ) : ?><div><dt><?php echo esc_html( ucwords( str_replace( '_', ' ', $key ) ) ); ?></dt><dd><?php echo esc_html( number_format_i18n( $value ) ); ?></dd></div><?php endforeach; ?></dl><p class="spd-meta"><?php esc_html_e( 'Only aggregate data supplied by the analytics/search owner is shown; File 03 does not create a hidden patient-level tracking profile.', 'sabri-profiles-doctors' ); ?></p></section><?php endif; ?>
		</main>
		<?php return ob_get_clean();
	}

	public function private_preview() {
		if ( ! is_user_logged_in() ) { return $this->notice( __( 'Please log in to preview your profile.', 'sabri-profiles-doctors' ), 'warning' ); }
		$profile = SPD_Profile_Repository::instance()->find_by_user_id( get_current_user_id(), false );
		if ( ! $profile ) { return $this->notice( __( 'Your profile is unavailable.', 'sabri-profiles-doctors' ), 'error' ); }
		$dto = SPD_Central_Profile::personal_site_dto( $profile['public_id'], get_current_user_id() );
		if ( is_wp_error( $dto ) ) { return $this->notice( $dto->get_error_message(), 'error' ); }
		$mode = isset( $_GET['mode'] ) ? sanitize_key( wp_unslash( $_GET['mode'] ) ) : 'desktop'; if ( ! in_array( $mode, array( 'desktop','mobile','rtl' ), true ) ) { $mode = 'desktop'; }
		ob_start(); ?>
		<main class="spd" aria-labelledby="spd-preview-title"><header class="spd-page-header"><h1 id="spd-preview-title"><?php esc_html_e( 'Private Profile Preview', 'sabri-profiles-doctors' ); ?></h1><p><?php esc_html_e( 'This noindex/no-store preview may include fields that are not public. It is visible only to the authorized owner.', 'sabri-profiles-doctors' ); ?></p><nav class="spd-actions" aria-label="<?php esc_attr_e( 'Preview modes', 'sabri-profiles-doctors' ); ?>"><a class="spd-btn spd-btn--secondary" href="?mode=desktop"><?php esc_html_e( 'Desktop', 'sabri-profiles-doctors' ); ?></a><a class="spd-btn spd-btn--secondary" href="?mode=mobile"><?php esc_html_e( 'Mobile', 'sabri-profiles-doctors' ); ?></a><a class="spd-btn spd-btn--secondary" href="?mode=rtl"><?php esc_html_e( 'RTL', 'sabri-profiles-doctors' ); ?></a></nav></header><div class="spd-preview-frame spd-preview-frame--<?php echo esc_attr( $mode ); ?>"<?php echo 'rtl' === $mode ? ' dir="rtl"' : ''; ?>><?php echo $this->render_personal_site_preview( $dto ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div></main>
		<?php return ob_get_clean();
	}

	private function render_personal_site_preview( array $dto ) {
		ob_start(); ?><article class="spd-card"><h2><?php echo esc_html( $dto['display_name'] ); ?></h2><p><?php echo esc_html( $this->profile_subtitle( $dto ) ); ?></p><?php echo $this->personal_site_sections( $dto, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></article><?php return ob_get_clean();
	}

	public function personal_site_sections( array $dto, $preview = false ) {
		ob_start();
		if ( ! empty( $dto['extended'] ) ) : ?><section class="spd-grid spd-personal-site-sections"><?php foreach ( SPD_Central_Profile::extended_labels() as $key => $label ) : if ( empty( $dto['extended'][ $key ] ) ) { continue; } ?><article class="spd-card"><h2><?php echo esc_html( $label ); ?></h2><p><?php echo nl2br( esc_html( $dto['extended'][ $key ] ) ); ?></p></article><?php endforeach; ?></section><?php endif;
		if ( ! empty( $dto['credential_card'] ) ) : ?><section class="spd-card spd-credential-card"><h2><?php esc_html_e( 'Verified Credential Card', 'sabri-profiles-doctors' ); ?></h2><dl><?php foreach ( $dto['credential_card'] as $key => $value ) : ?><div><dt><?php echo esc_html( ucwords( str_replace( '_', ' ', $key ) ) ); ?></dt><dd><?php echo esc_html( $value ); ?></dd></div><?php endforeach; ?></dl><p class="spd-meta"><?php esc_html_e( 'Only current evidence-backed fields supplied by the verification owner are displayed.', 'sabri-profiles-doctors' ); ?></p></section><?php endif;
		if ( ! empty( $dto['clinic'] ) ) : ?><section class="spd-card"><h2><?php esc_html_e( 'Clinic and Availability', 'sabri-profiles-doctors' ); ?></h2><?php foreach ( array( 'name','country','city','timezone','hours','consultation_modes','languages','teleconsult','accessible_facilities','next_slots','services' ) as $key ) : if ( empty( $dto['clinic'][ $key ] ) ) { continue; } ?><p><strong><?php echo esc_html( ucwords( str_replace( '_', ' ', $key ) ) ); ?>:</strong> <?php echo nl2br( esc_html( $dto['clinic'][ $key ] ) ); ?></p><?php endforeach; ?><div class="spd-actions"><?php if ( ! empty( $dto['clinic']['url'] ) ) : ?><a class="spd-btn spd-btn--secondary" href="<?php echo esc_url( $dto['clinic']['url'] ); ?>"><?php esc_html_e( 'View clinic', 'sabri-profiles-doctors' ); ?></a><?php endif; ?><?php if ( ! empty( $dto['clinic']['appointment_url'] ) ) : ?><a class="spd-btn" href="<?php echo esc_url( $dto['clinic']['appointment_url'] ); ?>" data-spd-appointment><?php esc_html_e( 'Book Appointment', 'sabri-profiles-doctors' ); ?></a><?php endif; ?></div></section><?php endif;
		if ( ! $preview && ! empty( $dto['share']['short_url'] ) ) : ?><section class="spd-card spd-share-card"><h2><?php esc_html_e( 'Share Verified Profile', 'sabri-profiles-doctors' ); ?></h2><div class="spd-qr" data-spd-qr data-url="<?php echo esc_url( $dto['share']['short_url'] ); ?>" aria-label="<?php esc_attr_e( 'QR code for this profile', 'sabri-profiles-doctors' ); ?>"></div><p dir="ltr"><code><?php echo esc_html( $dto['share']['short_url'] ); ?></code></p><div class="spd-actions"><button class="spd-btn" type="button" data-spd-share data-url="<?php echo esc_url( $dto['share']['short_url'] ); ?>"><?php esc_html_e( 'Share', 'sabri-profiles-doctors' ); ?></button><button class="spd-btn spd-btn--secondary" type="button" data-spd-copy data-url="<?php echo esc_url( $dto['share']['short_url'] ); ?>"><?php esc_html_e( 'Copy link', 'sabri-profiles-doctors' ); ?></button><a class="spd-btn spd-btn--secondary" href="<?php echo esc_url( add_query_arg( 'print_profile', '1', $dto['canonical_url'] ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Print', 'sabri-profiles-doctors' ); ?></a></div><p class="spd-meta"><?php esc_html_e( 'The QR contains a first-party, tracking-free, revocable profile link.', 'sabri-profiles-doctors' ); ?></p></section><?php endif;
		if ( ! empty( $dto['reviews']['items'] ) ) : ?><section class="spd-card"><h2><?php esc_html_e( 'Service Reviews', 'sabri-profiles-doctors' ); ?></h2><?php foreach ( $dto['reviews']['items'] as $review ) : ?><article class="spd-review"><p><strong><?php echo esc_html( $review['rating'] ); ?>/5</strong><?php echo $review['disputed'] ? ' · ' . esc_html__( 'Under dispute', 'sabri-profiles-doctors' ) : ''; ?></p><p><?php echo esc_html( $review['text'] ); ?></p></article><?php endforeach; ?><p class="spd-meta"><?php esc_html_e( 'Reviews are accepted only through the appointment owner after an eligible completed consultation. Clinical outcome ratings are not displayed.', 'sabri-profiles-doctors' ); ?></p></section><?php endif;
		if ( ! empty( $dto['organizations'] ) ) : ?><section class="spd-card"><h2><?php esc_html_e( 'Verified Affiliations', 'sabri-profiles-doctors' ); ?></h2><ul><?php foreach ( $dto['organizations'] as $org ) : ?><li><?php if ( $org['url'] ) : ?><a href="<?php echo esc_url( $org['url'] ); ?>"><?php echo esc_html( $org['name'] ); ?></a><?php else : ?><?php echo esc_html( $org['name'] ); ?><?php endif; ?><?php echo $org['role'] ? ' — ' . esc_html( $org['role'] ) : ''; ?></li><?php endforeach; ?></ul></section><?php endif;
		if ( 'doctor' === $dto['profile_type'] ) : ?><p class="spd-disclaimer"><?php esc_html_e( 'This profile is for professional information and education. Verification does not guarantee treatment results. Online emergency care is not provided; urgent symptoms require qualified local emergency care.', 'sabri-profiles-doctors' ); ?></p><?php endif;
		return ob_get_clean();
	}

	public function save_central_profile() {
		if ( ! is_user_logged_in() || empty( $_POST['spd_central_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['spd_central_nonce'] ) ), 'spd_save_central_profile' ) ) { wp_die( esc_html__( 'Security check failed.', 'sabri-profiles-doctors' ), '', array( 'response' => 403 ) ); }
		$input = array( 'target_user_id' => absint( $_POST['target_user_id'] ?? get_current_user_id() ), 'custom_slug' => wp_unslash( $_POST['custom_slug'] ?? '' ), 'audiences' => array() );
		$posted_audiences = isset( $_POST['audience'] ) && is_array( $_POST['audience'] ) ? wp_unslash( $_POST['audience'] ) : array();
		foreach ( SPD_Central_Profile::extended_fields() as $key ) { $input[ $key ] = wp_unslash( $_POST[ $key ] ?? '' ); $input['audiences'][ $key ] = sanitize_key( $posted_audiences[ $key ] ?? 'private' ); }
		$result = SPD_Profile_Repository::instance()->update_central_profile( get_current_user_id(), $input, absint( $_POST['version'] ?? 0 ), sanitize_text_field( wp_unslash( $_POST['idempotency_key'] ?? '' ) ) );
		if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => $this->error_status( $result ), 'back_link' => true ) ); }
		wp_safe_redirect( add_query_arg( 'spd_central_updated', '1', home_url( '/account/profile/personal-site/' ) ) ); exit;
	}

	public function grant_delegate_post() {
		if ( ! is_user_logged_in() || empty( $_POST['spd_delegate_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['spd_delegate_nonce'] ) ), 'spd_grant_delegate' ) ) { wp_die( esc_html__( 'Security check failed.', 'sabri-profiles-doctors' ), '', array( 'response' => 403 ) ); }
		$scopes = isset( $_POST['scopes'] ) && is_array( $_POST['scopes'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['scopes'] ) ) : array();
		$result = SPD_Profile_Repository::instance()->grant_delegate( get_current_user_id(), absint( $_POST['delegate_user_id'] ?? 0 ), $scopes, sanitize_text_field( wp_unslash( $_POST['expires_at'] ?? '' ) ) );
		if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => $this->error_status( $result ), 'back_link' => true ) ); }
		wp_safe_redirect( home_url( '/account/profile/personal-site/' ) ); exit;
	}

	public function revoke_delegate_post() {
		$delegate = absint( $_POST['delegate_user_id'] ?? 0 );
		if ( ! is_user_logged_in() || ! $delegate || empty( $_POST['spd_revoke_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['spd_revoke_nonce'] ) ), 'spd_revoke_delegate_' . $delegate ) ) { wp_die( esc_html__( 'Security check failed.', 'sabri-profiles-doctors' ), '', array( 'response' => 403 ) ); }
		$result = SPD_Profile_Repository::instance()->revoke_delegate( get_current_user_id(), $delegate );
		if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => $this->error_status( $result ), 'back_link' => true ) ); }
		wp_safe_redirect( home_url( '/account/profile/personal-site/' ) ); exit;
	}

	public function rotate_share_post() {
		if ( ! is_user_logged_in() || empty( $_POST['spd_share_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['spd_share_nonce'] ) ), 'spd_rotate_share' ) ) { wp_die( esc_html__( 'Security check failed.', 'sabri-profiles-doctors' ), '', array( 'response' => 403 ) ); }
		$result = SPD_Profile_Repository::instance()->rotate_share_link( get_current_user_id(), absint( $_POST['version'] ?? 0 ), sanitize_text_field( wp_unslash( $_POST['idempotency_key'] ?? '' ) ) );
		if ( is_wp_error( $result ) ) { wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => $this->error_status( $result ), 'back_link' => true ) ); }
		wp_safe_redirect( home_url( '/account/profile/personal-site/' ) ); exit;
	}

	public function structured_data() {
		if ( 'private' === ( new SPD_Routes() )->current_context() ) { return; }
		$public_id = sanitize_text_field( (string) get_query_var( 'spd_public_id' ) );
		if ( ! $public_id && is_page() ) {
			$map = (array) get_option( 'spd_page_map', array() );
			if ( ! empty( $map['founder'] ) && is_page( absint( $map['founder'] ) ) ) {
				$founder = SPD_Profile_Repository::instance()->find_by_user_id( SPD_Membership_Adapter::founder_id(), false );
				$public_id = $founder ? $founder['public_id'] : '';
			}
		}
		if ( ! $public_id ) { return; }
		$schema = SPD_Central_Profile::structured_data( $public_id );
		if ( $schema ) { echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n"; }
	}
}
