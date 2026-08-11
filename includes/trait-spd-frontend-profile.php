<?php
defined( 'ABSPATH' ) || exit;

trait SPD_Frontend_Profile {
	public function founder() {
		$founder_id = SPD_Membership_Adapter::founder_id();
		if ( ! $founder_id ) {
			return $this->notice( __( 'The canonical Founder identity is not configured.', 'sabri-profiles-doctors' ), 'warning' );
		}
		$profile = SPD_Profile_Repository::instance()->find_by_user_id( $founder_id );
		if ( is_wp_error( $profile ) ) {
			return $this->notice( $profile->get_error_message(), 'error' );
		}
		return $this->render_profile( $profile['public_id'] );
	}

	public function profile_router() {
		$view = sanitize_key( (string) get_query_var( 'spd_view' ) );
		if ( 'timeline' === $view ) { return $this->timeline(); }
		if ( 'report' === $view ) { return $this->report_form(); }
		return $this->profile();
	}

	public function profile() {
		$public_id = sanitize_text_field( (string) get_query_var( 'spd_public_id' ) );
		if ( ! $public_id && isset( $_GET['public_id'] ) ) { $public_id = sanitize_text_field( wp_unslash( $_GET['public_id'] ) ); }
		if ( ! $public_id && is_user_logged_in() ) {
			$profile = SPD_Profile_Repository::instance()->find_by_user_id( get_current_user_id() );
			$public_id = ! is_wp_error( $profile ) ? $profile['public_id'] : '';
		}
		return $public_id ? $this->render_profile( $public_id ) : $this->notice( __( 'This profile is unavailable.', 'sabri-profiles-doctors' ), 'error' );
	}

	private function render_profile( $public_id ) {
		$dto = SPD_Central_Profile::personal_site_dto( $public_id, get_current_user_id() );
		if ( is_wp_error( $dto ) ) { return $this->notice( $dto->get_error_message(), 'error' ); }
		$owner = SPD_Profile_Repository::instance()->find_by_public_id_strict( $public_id );
		if ( is_wp_error( $owner ) ) { return $this->notice( $owner->get_error_message(), 'error' ); }
		if ( ! $owner ) { return $this->notice( __( 'This profile is unavailable.', 'sabri-profiles-doctors' ), 'error' ); }
		$dto = SPD_Future_Profile::augment_personal_site_dto( $dto, $owner, get_current_user_id() );
		$avatar = $dto['media']['avatar'] ?? array();
		$cover = $dto['media']['cover'] ?? array();
		$is_owner = absint( $owner['user_id'] ) === get_current_user_id();
		ob_start();
		?>
		<main class="spd spd-profile" aria-labelledby="spd-profile-name" data-profile-version="<?php echo esc_attr( $dto['version'] ); ?>">
			<section class="spd-hero"<?php echo ! empty( $cover['url'] ) ? ' style="--spd-cover:url(' . esc_url( $cover['url'] ) . ');--spd-cover-x:' . esc_attr( $cover['focal_x'] ) . '%;--spd-cover-y:' . esc_attr( $cover['focal_y'] ) . '%"' : ''; ?>>
				<div class="spd-avatar spd-avatar--large"<?php echo isset( $avatar['focal_x'] ) ? ' style="--spd-avatar-x:' . esc_attr( $avatar['focal_x'] ) . '%;--spd-avatar-y:' . esc_attr( $avatar['focal_y'] ) . '%"' : ''; ?>>
					<?php if ( ! empty( $avatar['url'] ) ) : ?><img src="<?php echo esc_url( $avatar['url'] ); ?>" alt="<?php echo esc_attr( $avatar['alt'] ?: $dto['display_name'] ); ?>" loading="eager" decoding="async" fetchpriority="high"><?php else : ?><span aria-hidden="true"><?php echo esc_html( SPD_Helpers::initials( $dto['display_name'] ) ); ?></span><?php endif; ?>
				</div>
				<div class="spd-hero__text"><span class="spd-badge spd-badge--<?php echo esc_attr( $dto['badge']['key'] ); ?>"><?php echo $dto['badge']['verified'] ? '<span aria-hidden="true">✓</span> ' : ''; ?><?php echo esc_html( $dto['badge']['label'] ); ?></span><h1 id="spd-profile-name"><?php echo esc_html( $dto['display_name'] ); ?></h1><p><?php echo esc_html( $this->profile_subtitle( $dto ) ); ?></p></div>
			</section>

			<nav class="spd-profile-nav" aria-label="<?php esc_attr_e( 'Profile sections', 'sabri-profiles-doctors' ); ?>"><a aria-current="page" href="<?php echo esc_url( $dto['canonical_url'] ); ?>"><?php esc_html_e( 'Profile', 'sabri-profiles-doctors' ); ?></a><a href="<?php echo esc_url( $dto['timeline_url'] ); ?>"><?php esc_html_e( 'Timeline', 'sabri-profiles-doctors' ); ?></a><?php if ( is_user_logged_in() && ! $is_owner ) : ?><a href="<?php echo esc_url( $dto['report_url'] ); ?>"><?php esc_html_e( 'Report', 'sabri-profiles-doctors' ); ?></a><?php endif; ?></nav>

			<?php echo $this->contact_actions( $dto ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php if ( $is_owner ) : ?><div class="spd-actions"><a class="spd-btn spd-btn--secondary" href="<?php echo esc_url( home_url( '/account/profile/' ) ); ?>"><?php esc_html_e( 'Edit profile and privacy', 'sabri-profiles-doctors' ); ?></a><a class="spd-btn" href="<?php echo esc_url( home_url( '/account/profile/personal-site/' ) ); ?>"><?php esc_html_e( 'Manage personal website', 'sabri-profiles-doctors' ); ?></a><a class="spd-btn spd-btn--secondary" href="<?php echo esc_url( home_url( '/account/profile/preview/' ) ); ?>"><?php esc_html_e( 'Private preview', 'sabri-profiles-doctors' ); ?></a></div><?php endif; ?>

			<div class="spd-grid">
				<?php if ( 'founder' === $dto['profile_type'] ) : foreach ( $this->founder_labels() as $key => $label ) : if ( ! empty( $dto['founder'][ $key ] ) ) : ?><section class="spd-card spd-card--verified"><h2><?php echo esc_html( $label ); ?></h2><p><?php echo nl2br( esc_html( $dto['founder'][ $key ] ) ); ?></p></section><?php endif; endforeach; endif; ?>
				<?php foreach ( $this->field_labels() as $key => $label ) : if ( ! empty( $dto['fields'][ $key ] ) ) : ?><section class="spd-card"><h2><?php echo esc_html( $label ); ?></h2><p><?php echo nl2br( esc_html( $dto['fields'][ $key ] ) ); ?></p></section><?php endif; endforeach; ?>
				<?php foreach ( $this->professional_labels() as $key => $label ) : if ( ! empty( $dto['professional'][ $key ] ) ) : ?><section class="spd-card spd-card--verified"><h2><?php echo esc_html( $label ); ?></h2><p><?php echo nl2br( esc_html( (string) $dto['professional'][ $key ] ) ); ?></p></section><?php endif; endforeach; ?>
			</div>

			<?php echo $this->personal_site_sections( $dto, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php echo $this->future_profile_sections( $dto, $is_owner ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php if ( $is_owner ) : ?><form class="spd-inline-form spd-share-rotation" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="spd_rotate_share"><input type="hidden" name="version" value="<?php echo esc_attr( $dto['version'] ); ?>"><input type="hidden" name="idempotency_key" value="<?php echo esc_attr( wp_generate_uuid4() ); ?>"><?php wp_nonce_field( 'spd_rotate_share', 'spd_share_nonce' ); ?><button class="spd-btn spd-btn--secondary" type="submit"><?php esc_html_e( 'Revoke and rotate share/QR link', 'sabri-profiles-doctors' ); ?></button></form><?php endif; ?>
		</main>
		<?php
		return ob_get_clean();
	}
}
