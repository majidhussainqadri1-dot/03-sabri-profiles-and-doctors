<?php
defined( 'ABSPATH' ) || exit;

trait SPD_Frontend_Edit {
	public function edit() {
		if ( ! is_user_logged_in() ) {
			return $this->notice( __( 'Please log in to edit your profile.', 'sabri-profiles-doctors' ), 'warning' );
		}
		$model = SPD_Profile_Repository::instance()->edit_model( get_current_user_id() );
		if ( is_wp_error( $model ) ) {
			return $this->notice( $model->get_error_message(), 'error' );
		}
		$values = $model['values'];
		$audiences = $model['audiences'];
		ob_start(); ?>
		<main class="spd" aria-labelledby="spd-edit-title">
			<header class="spd-page-header"><h1 id="spd-edit-title"><?php esc_html_e( 'Edit Profile and Privacy', 'sabri-profiles-doctors' ); ?></h1><p><?php esc_html_e( 'Identity, roles and verification decisions remain with Files 00 and 09. This page controls File 03 presentation fields, media and audience choices.', 'sabri-profiles-doctors' ); ?></p></header>
			<?php if ( isset( $_GET['spd_updated'] ) ) : ?><div class="spd-notice spd-notice--success" role="status"><?php esc_html_e( 'Profile saved.', 'sabri-profiles-doctors' ); ?></div><?php endif; ?>
			<section class="spd-card"><h2><?php esc_html_e( 'Profile completion', 'sabri-profiles-doctors' ); ?></h2><p><?php echo esc_html( $model['completeness']['label'] ); ?> — <?php echo esc_html( $model['completeness']['complete_items'] . '/' . $model['completeness']['total_items'] ); ?></p><?php if ( $model['completeness']['missing'] ) : ?><p><?php echo esc_html( implode( ', ', $model['completeness']['missing'] ) ); ?></p><?php endif; ?></section>
			<form class="spd-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" enctype="multipart/form-data" novalidate>
				<input type="hidden" name="action" value="spd_save_profile">
				<input type="hidden" name="version" value="<?php echo esc_attr( $model['version'] ); ?>">
				<input type="hidden" name="idempotency_key" value="<?php echo esc_attr( wp_generate_uuid4() ); ?>">
				<?php wp_nonce_field( 'spd_save_profile', 'spd_nonce' ); ?>
				<?php echo $this->textarea_with_audience( 'bio', __( 'Biography', 'sabri-profiles-doctors' ), $values['bio'] ?? '', $audiences['bio'] ?? 'private', 5000 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo $this->text_with_audience( 'country', __( 'Country', 'sabri-profiles-doctors' ), $values['country'] ?? '', $audiences['country'] ?? 'private' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo $this->text_with_audience( 'city', __( 'City', 'sabri-profiles-doctors' ), $values['city'] ?? '', $audiences['city'] ?? 'private' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo $this->textarea_with_audience( 'languages', __( 'Languages', 'sabri-profiles-doctors' ), $values['languages'] ?? '', $audiences['languages'] ?? 'private', 1000 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo $this->textarea_with_audience( 'studied_books', __( 'Classical books studied', 'sabri-profiles-doctors' ), $values['studied_books'] ?? '', $audiences['studied_books'] ?? 'private', 5000 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<label><?php esc_html_e( 'Locale', 'sabri-profiles-doctors' ); ?><input name="locale" value="<?php echo esc_attr( $values['locale'] ?? 'en-US' ); ?>" maxlength="20"></label>
				<?php if ( 'founder' === $model['profile_type'] ) : ?><fieldset><legend><?php esc_html_e( 'Official Founder information', 'sabri-profiles-doctors' ); ?></legend><?php foreach ( $this->founder_labels() as $key => $label ) : ?><label><?php echo esc_html( $label ); ?><textarea name="<?php echo esc_attr( $key ); ?>" rows="5" maxlength="8000"><?php echo esc_textarea( $values[ $key ] ?? '' ); ?></textarea></label><?php endforeach; ?></fieldset><?php endif; ?>
				<fieldset><legend><?php esc_html_e( 'Whole profile visibility', 'sabri-profiles-doctors' ); ?></legend><?php echo $this->audience_select( 'profile_visibility', $audiences['profile_visibility'] ?? 'private' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></fieldset>
				<fieldset><legend><?php esc_html_e( 'Contact visibility', 'sabri-profiles-doctors' ); ?></legend><?php foreach ( array( 'phone' => __( 'Phone', 'sabri-profiles-doctors' ), 'email' => __( 'Email', 'sabri-profiles-doctors' ), 'whatsapp' => __( 'WhatsApp', 'sabri-profiles-doctors' ) ) as $key => $label ) : ?><label><?php echo esc_html( $label ); ?><?php echo $this->audience_select( $key, $audiences[ $key ] ?? 'private' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label><?php endforeach; ?><label class="spd-check"><input type="checkbox" name="internal_message" value="1" <?php checked( $model['internal_message'], '1' ); ?>><?php esc_html_e( 'Allow internal profile message action', 'sabri-profiles-doctors' ); ?></label><?php echo $this->audience_select( 'internal_message', $audiences['internal_message'] ?? 'private' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></fieldset>
				<fieldset><legend><?php esc_html_e( 'Profile media', 'sabri-profiles-doctors' ); ?></legend><label><?php esc_html_e( 'Avatar image — JPG, PNG or WebP; maximum 5 MB', 'sabri-profiles-doctors' ); ?><input type="file" name="avatar" accept="image/jpeg,image/png,image/webp"></label><label><?php esc_html_e( 'Avatar alternative text', 'sabri-profiles-doctors' ); ?><input name="avatar_alt" maxlength="255"></label><label><?php esc_html_e( 'Avatar focal point X', 'sabri-profiles-doctors' ); ?><input type="number" name="avatar_focal_x" min="0" max="100" value="50"></label><label><?php esc_html_e( 'Avatar focal point Y', 'sabri-profiles-doctors' ); ?><input type="number" name="avatar_focal_y" min="0" max="100" value="50"></label><label><?php esc_html_e( 'Cover image — JPG, PNG or WebP; maximum 5 MB', 'sabri-profiles-doctors' ); ?><input type="file" name="cover" accept="image/jpeg,image/png,image/webp"></label><label><?php esc_html_e( 'Cover alternative text', 'sabri-profiles-doctors' ); ?><input name="cover_alt" maxlength="255"></label></fieldset>
				<button class="spd-btn" type="submit"><?php esc_html_e( 'Save profile', 'sabri-profiles-doctors' ); ?></button>
			</form>
		</main>
		<?php return ob_get_clean();
	}

	public function save() {
		if ( ! is_user_logged_in() || empty( $_POST['spd_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['spd_nonce'] ) ), 'spd_save_profile' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'sabri-profiles-doctors' ), '', array( 'response' => 403 ) );
		}
		$user_id = get_current_user_id();
		$expected = absint( $_POST['version'] ?? 0 );
		$audiences = array();
		$posted_audiences = isset( $_POST['audience'] ) && is_array( $_POST['audience'] ) ? wp_unslash( $_POST['audience'] ) : array();
		foreach ( SPD_Profile_Repository::visibility_fields() as $key ) {
			$audiences[ $key ] = isset( $posted_audiences[ $key ] ) ? sanitize_key( $posted_audiences[ $key ] ) : 'private';
		}
		$input = array(
			'bio'              => wp_unslash( $_POST['bio'] ?? '' ),
			'country'          => wp_unslash( $_POST['country'] ?? '' ),
			'city'             => wp_unslash( $_POST['city'] ?? '' ),
			'languages'        => wp_unslash( $_POST['languages'] ?? '' ),
			'studied_books'    => wp_unslash( $_POST['studied_books'] ?? '' ),
			'locale'           => wp_unslash( $_POST['locale'] ?? 'en-US' ),
			'audiences'        => $audiences,
			'internal_message' => ! empty( $_POST['internal_message'] ),
		);
		foreach ( SPD_Profile_Repository::founder_fields() as $founder_key ) {
			$input[ $founder_key ] = wp_unslash( $_POST[ $founder_key ] ?? '' );
		}
		$prepared_media = array();
		foreach ( array( 'avatar', 'cover' ) as $purpose ) {
			$prepared = SPD_Media::prepare_upload(
				$user_id,
				$purpose,
				$purpose,
				array(
					'alt_text' => wp_unslash( $_POST[ $purpose . '_alt' ] ?? '' ),
					'focal_x'  => wp_unslash( $_POST[ $purpose . '_focal_x' ] ?? 50 ),
					'focal_y'  => wp_unslash( $_POST[ $purpose . '_focal_y' ] ?? 50 ),
				)
			);
			if ( is_wp_error( $prepared ) ) {
				$this->cleanup_prepared_media( $prepared_media, $user_id );
				wp_die( esc_html( $prepared->get_error_message() ), '', array( 'response' => 400, 'back_link' => true ) );
			}
			if ( $prepared ) {
				$prepared_media[ $purpose ] = $prepared;
			}
		}
		$idempotency = sanitize_text_field( wp_unslash( $_POST['idempotency_key'] ?? '' ) );
		$result = SPD_Profile_Repository::instance()->update_profile( $user_id, $input, $expected, $idempotency );
		if ( is_wp_error( $result ) ) {
			$this->cleanup_prepared_media( $prepared_media, $user_id );
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => $this->error_status( $result ), 'back_link' => true ) );
		}
		$current_version = absint( $result['version'] );
		foreach ( $prepared_media as $purpose => $prepared ) {
			$media_result = SPD_Profile_Repository::instance()->attach_media( $user_id, $purpose, $prepared, $current_version );
			if ( is_wp_error( $media_result ) ) {
				$this->cleanup_prepared_media( array( $purpose => $prepared ), $user_id );
				wp_die( esc_html( $media_result->get_error_message() ), '', array( 'response' => $this->error_status( $media_result ), 'back_link' => true ) );
			}
			$current_version = absint( $media_result['version'] );
		}
		wp_safe_redirect( add_query_arg( 'spd_updated', '1', home_url( '/account/profile/' ) ) );
		exit;
	}

}
