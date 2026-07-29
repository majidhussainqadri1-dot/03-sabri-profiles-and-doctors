<?php
defined( 'ABSPATH' ) || exit;

final class SPD_Admin {
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_spd_save_founder', array( $this, 'save_founder' ) );
		add_action( 'admin_notices', array( $this, 'activation_notice' ) );
	}

	public function menu() {
		$has_shell = defined( 'SABRI_SHELL_VERSION' );
		if ( current_user_can( 'smc_manage_membership' ) || current_user_can( 'manage_options' ) ) {
			if ( $has_shell ) {
				add_submenu_page( 'sabri-shell', 'Profile Projection Status', 'Profile Projection', 'smc_manage_membership', 'sabri-profiles', array( $this, 'status_page' ) );
			} else {
				add_options_page( 'Profile Projection Status', 'Profile Projection', 'smc_manage_membership', 'sabri-profiles', array( $this, 'status_page' ) );
			}
		}
		if ( SPD_Membership_Adapter::can_manage_founder() ) {
			if ( $has_shell ) {
				add_submenu_page( 'sabri-shell', 'Founder Profile', 'Founder Profile', 'read', 'sabri-founder-profile', array( $this, 'founder_page' ) );
			} else {
				add_menu_page( 'Founder Profile', 'Founder Profile', 'read', 'sabri-founder-profile', array( $this, 'founder_page' ), 'dashicons-admin-users', 59 );
			}
		}
	}

	public function status_page() {
		if ( ! current_user_can( 'smc_manage_membership' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Access denied.', 'sabri-profiles-doctors' ), '', array( 'response' => 403 ) );
		}
		$founder_id = SPD_Membership_Adapter::founder_id();
		?>
		<div class="wrap spd-admin"><h1>Profile Projection Status</h1>
		<p>File 03 is a read-only profile and directory projection. File 00 owns identity and roles; File 09 owns doctor verification.</p>
		<table class="widefat striped"><tbody>
		<tr><th>File 00 Membership Core</th><td><?php echo SPD_Membership_Adapter::available() ? 'Available' : 'Missing'; ?></td></tr>
		<tr><th>File 09 Doctor Verification</th><td><?php echo SPD_Verification_Adapter::gdo_available() ? 'Available' : 'Missing — verified directory is fail-closed'; ?></td></tr>
		<tr><th>Canonical Founder</th><td><?php echo $founder_id ? esc_html( get_userdata( $founder_id )->display_name . ' (ID ' . $founder_id . ')' ) : 'Not uniquely resolved'; ?></td></tr>
		<tr><th>Doctor approval controls</th><td>Not provided by File 03. Use File 09.</td></tr>
		<tr><th>Legacy audit retention</th><td>Identifiers anonymized after 180 days; legacy rows deleted after 365 days.</td></tr>
		</tbody></table></div>
		<?php
	}

	public function founder_page() {
		$this->guard_founder();
		$f = SPD_Helpers::founder();
		$fields = array(
			'title' => 'Professional title', 'introduction' => 'Introduction', 'mission' => 'Mission', 'vision' => 'Vision',
			'objectives' => 'Objectives', 'methodology' => 'Professional methodology', 'experience' => 'Clinical experience',
			'research' => 'Research and knowledge areas', 'publications' => 'Books and publications — one per line',
		);
		?>
		<div class="wrap spd-admin"><h1>Founder Profile</h1><p>The Founder identity, name, location, phone, and WhatsApp are read from the canonical File 00 account. File 03 stores only public presentation content and media.</p>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" enctype="multipart/form-data"><input type="hidden" name="action" value="spd_save_founder"><?php wp_nonce_field( 'spd_save_founder' ); ?>
		<table class="form-table"><tbody><tr><th>Canonical name</th><td><?php echo esc_html( $f['name'] ); ?></td></tr><tr><th>Canonical location</th><td><?php echo esc_html( $f['location'] ); ?></td></tr>
		<?php foreach ( $fields as $key => $label ) : ?><tr><th><label for="spd-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th><td><?php if ( 'title' === $key ) : ?><input class="regular-text" id="spd-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $f[ $key ] ); ?>"><?php else : ?><textarea class="large-text" rows="5" id="spd-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>"><?php echo esc_textarea( $f[ $key ] ); ?></textarea><?php endif; ?></td></tr><?php endforeach; ?>
		<tr><th>Public contact</th><td><label><input type="checkbox" name="public_contact" value="1" <?php checked( get_user_meta( get_current_user_id(), '_spd_public_contact', true ), '1' ); ?>> Show canonical phone and WhatsApp publicly.</label></td></tr>
		<tr><th>Founder photo</th><td><?php if ( $f['photo_id'] ) { echo wp_get_attachment_image( $f['photo_id'], 'thumbnail' ); } ?><input type="file" name="founder_photo" accept="image/jpeg,image/png,image/webp"></td></tr>
		<tr><th>Cover photo</th><td><?php if ( $f['cover_id'] ) { echo wp_get_attachment_image( $f['cover_id'], 'medium' ); } ?><input type="file" name="founder_cover" accept="image/jpeg,image/png,image/webp"></td></tr></tbody></table>
		<button class="button button-primary" type="submit">Save Founder presentation</button></form></div>
		<?php
	}

	public function save_founder() {
		$this->guard_founder();
		check_admin_referer( 'spd_save_founder' );
		$allowed = array( 'title', 'introduction', 'mission', 'vision', 'objectives', 'methodology', 'experience', 'research', 'publications' );
		$profile = array();
		foreach ( $allowed as $key ) {
			$raw = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
			$profile[ $key ] = 'title' === $key ? sanitize_text_field( $raw ) : sanitize_textarea_field( $raw );
		}
		update_option( 'spd_founder_profile', $profile, false );
		$user_id = get_current_user_id();
		update_user_meta( $user_id, '_spd_public_contact', isset( $_POST['public_contact'] ) ? '1' : '0' );
		$this->save_image( $user_id, 'founder_photo', 'profile_photo_id', 'profile' );
		$this->save_image( $user_id, 'founder_cover', 'cover_photo_id', 'cover' );
		SPD_Helpers::audit( $user_id, 'founder_profile', 'updated', 'Founder presentation updated by canonical Founder account.' );
		wp_safe_redirect( add_query_arg( array( 'page' => 'sabri-founder-profile', 'updated' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private function save_image( $user_id, $field, $meta_key, $purpose ) {
		$result = SPD_Media::upload( $user_id, $field, $purpose );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 400, 'back_link' => true ) );
		}
		if ( $result ) {
			SPD_Media::replace( $user_id, $meta_key, $result, $purpose );
		}
	}

	private function guard_founder() {
		if ( ! SPD_Membership_Adapter::can_manage_founder() ) {
			wp_die( esc_html__( 'Only the canonical Founder account may edit this profile.', 'sabri-profiles-doctors' ), '', array( 'response' => 403 ) );
		}
	}

	public function activation_notice() {
		if ( current_user_can( 'activate_plugins' ) && get_transient( 'spd_activation_notice' ) ) {
			delete_transient( 'spd_activation_notice' );
			echo '<div class="notice notice-success is-dismissible"><p>Sabri Profiles and Doctors 0.2.0 is active as a profile/directory projection. Doctor approvals remain in File 09.</p></div>';
		}
	}
}
