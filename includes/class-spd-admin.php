<?php
defined( 'ABSPATH' ) || exit;

class SPD_Admin {
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_spd_verify_doctor', array( $this, 'verify' ) );
		add_action( 'admin_post_spd_save_founder', array( $this, 'save_founder' ) );
		add_action( 'admin_notices', array( $this, 'activation_notice' ) );
	}

	public function menu() {
		add_menu_page( 'Sabri Profiles', 'Sabri Profiles', 'manage_sabri_doctors', 'sabri-profiles', array( $this, 'doctors_page' ), 'dashicons-groups', 27 );
		add_submenu_page( 'sabri-profiles', 'Doctor Verification', 'Doctor Verification', 'manage_sabri_doctors', 'sabri-profiles', array( $this, 'doctors_page' ) );
		add_submenu_page( 'sabri-profiles', 'Founder Profile', 'Founder Profile', 'manage_sabri_doctors', 'sabri-founder-profile', array( $this, 'founder_page' ) );
	}

	public function doctors_page() {
		$this->guard();
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$meta   = array();
		if ( $status ) { $meta[] = array( 'key' => '_spd_verification_status', 'value' => $status ); }
		$users = get_users( array( 'role__in' => array( 'sabri_doctor_pending', 'sabri_doctor_verified' ), 'number' => 100, 'orderby' => 'registered', 'order' => 'DESC', 'meta_query' => $meta ) );
		?>
		<div class="wrap spd-admin"><h1>Doctor Verification</h1><p>This first release reviews professional profile information only. Identity documents are deliberately excluded until encrypted private storage is available.</p>
		<nav class="spd-admin-filters"><a href="<?php echo esc_url( admin_url( 'admin.php?page=sabri-profiles' ) ); ?>">All</a><?php foreach ( array( 'pending', 'under_review', 'verified', 'more_info', 'rejected', 'suspended' ) as $item ) : ?><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'sabri-profiles', 'status' => $item ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( SPD_Helpers::status_label( $item ) ); ?></a><?php endforeach; ?></nav>
		<table class="widefat striped"><thead><tr><th>Doctor</th><th>Professional details</th><th>Status</th><th>Review action</th></tr></thead><tbody>
		<?php if ( $users ) : foreach ( $users as $user ) : $current = SPD_Helpers::verification_status( $user->ID ); ?>
		<tr><td><strong><?php echo esc_html( $user->display_name ); ?></strong><br><a href="<?php echo esc_url( SPD_Helpers::profile_url( $user->ID ) ); ?>" target="_blank" rel="noopener">View profile</a><br><small><?php echo esc_html( $user->user_email ); ?></small></td><td><?php echo esc_html( SPD_Helpers::get( $user->ID, 'qualification', 'Not added' ) ); ?><br><?php echo esc_html( SPD_Helpers::get( $user->ID, 'licence_number', 'Licence not added' ) ); ?><br><?php echo esc_html( SPD_Helpers::get( $user->ID, 'country', '' ) ); ?></td><td><span class="spd-admin-status"><?php echo esc_html( SPD_Helpers::status_label( $current ) ); ?></span></td><td><form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post"><input type="hidden" name="action" value="spd_verify_doctor"><input type="hidden" name="user_id" value="<?php echo absint( $user->ID ); ?>"><?php wp_nonce_field( 'spd_verify_' . $user->ID ); ?><select name="new_status"><?php foreach ( array( 'pending', 'under_review', 'verified', 'more_info', 'rejected', 'suspended' ) as $option ) : ?><option value="<?php echo esc_attr( $option ); ?>" <?php selected( $current, $option ); ?>><?php echo esc_html( SPD_Helpers::status_label( $option ) ); ?></option><?php endforeach; ?></select><input name="reason" placeholder="Internal review note"><button class="button button-primary" type="submit">Save review</button></form></td></tr>
		<?php endforeach; else : ?><tr><td colspan="4">No doctor accounts found.</td></tr><?php endif; ?></tbody></table></div>
		<?php
	}

	public function founder_page() {
		$this->guard(); $f = SPD_Helpers::founder();
		$fields = array( 'name' => 'Full name', 'title' => 'Professional title', 'location' => 'Location', 'phone' => 'Phone', 'whatsapp' => 'WhatsApp', 'introduction' => 'Introduction', 'mission' => 'Mission', 'vision' => 'Vision', 'objectives' => 'Objectives', 'methodology' => 'Professional methodology', 'experience' => 'Clinical experience', 'research' => 'Research and knowledge areas', 'publications' => 'Books and publications — one per line' );
		?>
		<div class="wrap spd-admin"><h1>Founder Profile — 100% Foundation</h1><p>These details appear publicly. Use an international phone format such as +923001234567.</p>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" enctype="multipart/form-data"><input type="hidden" name="action" value="spd_save_founder"><?php wp_nonce_field( 'spd_save_founder' ); ?>
		<table class="form-table"><tbody><?php foreach ( $fields as $key => $label ) : ?><tr><th><label for="spd-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th><td><?php if ( in_array( $key, array( 'introduction', 'mission', 'vision', 'objectives', 'methodology', 'experience', 'research', 'publications' ), true ) ) : ?><textarea class="large-text" rows="5" id="spd-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>"><?php echo esc_textarea( $f[ $key ] ); ?></textarea><?php else : ?><input class="regular-text" id="spd-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $f[ $key ] ); ?>"><?php endif; ?></td></tr><?php endforeach; ?>
		<tr><th>Founder photo</th><td><?php if ( $f['photo_id'] ) { echo wp_get_attachment_image( $f['photo_id'], 'thumbnail' ); } ?><input type="file" name="founder_photo" accept="image/jpeg,image/png,image/webp"></td></tr><tr><th>Cover photo</th><td><?php if ( $f['cover_id'] ) { echo wp_get_attachment_image( $f['cover_id'], 'medium' ); } ?><input type="file" name="founder_cover" accept="image/jpeg,image/png,image/webp"></td></tr></tbody></table><button class="button button-primary" type="submit">Save founder profile</button></form></div>
		<?php
	}

	public function verify() {
		$this->guard(); $user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		check_admin_referer( 'spd_verify_' . $user_id );
		$allowed = array( 'pending', 'under_review', 'verified', 'more_info', 'rejected', 'suspended' );
		$new = isset( $_POST['new_status'] ) ? sanitize_key( wp_unslash( $_POST['new_status'] ) ) : 'pending';
		if ( ! $user_id || ! in_array( $new, $allowed, true ) || ! SPD_Helpers::is_doctor( $user_id ) ) { wp_die( esc_html__( 'Invalid doctor review request.', 'sabri-profiles-doctors' ), '', array( 'response' => 400 ) ); }
		$old = SPD_Helpers::verification_status( $user_id ); update_user_meta( $user_id, '_spd_verification_status', $new );
		$user = new WP_User( $user_id ); $user->remove_role( 'sabri_doctor_pending' ); $user->remove_role( 'sabri_doctor_verified' ); $user->add_role( 'verified' === $new ? 'sabri_doctor_verified' : 'sabri_doctor_pending' );
		$reason = isset( $_POST['reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reason'] ) ) : ''; SPD_Helpers::audit( $user_id, $old, $new, $reason );
		wp_safe_redirect( add_query_arg( array( 'page' => 'sabri-profiles', 'updated' => '1' ), admin_url( 'admin.php' ) ) ); exit;
	}

	public function save_founder() {
		$this->guard(); check_admin_referer( 'spd_save_founder' ); $f = SPD_Helpers::founder();
		foreach ( array( 'name', 'title', 'location', 'phone', 'whatsapp', 'introduction', 'mission', 'vision', 'objectives', 'methodology', 'experience', 'research', 'publications' ) as $key ) {
			if ( isset( $_POST[ $key ] ) ) { $f[ $key ] = in_array( $key, array( 'introduction', 'mission', 'vision', 'objectives', 'methodology', 'experience', 'research', 'publications' ), true ) ? sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) ) : sanitize_text_field( wp_unslash( $_POST[ $key ] ) ); }
		}
		$f['phone'] = SPD_Helpers::clean_phone( $f['phone'] ); $f['whatsapp'] = SPD_Helpers::clean_phone( $f['whatsapp'] );
		$f['photo_id'] = $this->upload( 'founder_photo', absint( $f['photo_id'] ) ); $f['cover_id'] = $this->upload( 'founder_cover', absint( $f['cover_id'] ) ); update_option( 'spd_founder_profile', $f, false );
		wp_safe_redirect( add_query_arg( array( 'page' => 'sabri-founder-profile', 'updated' => '1' ), admin_url( 'admin.php' ) ) ); exit;
	}

	private function upload( $field, $current ) {
		if ( empty( $_FILES[ $field ]['name'] ) || ! empty( $_FILES[ $field ]['error'] ) || (int) $_FILES[ $field ]['size'] > 5 * MB_IN_BYTES ) { return $current; }
		require_once ABSPATH . 'wp-admin/includes/file.php'; require_once ABSPATH . 'wp-admin/includes/media.php'; require_once ABSPATH . 'wp-admin/includes/image.php';
		$id = media_handle_upload( $field, 0, array(), array( 'test_form' => false, 'mimes' => array( 'jpg|jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' ) ) ); return is_wp_error( $id ) ? $current : absint( $id );
	}

	private function guard() { if ( ! current_user_can( 'manage_sabri_doctors' ) ) { wp_die( esc_html__( 'You are not allowed to manage doctor profiles.', 'sabri-profiles-doctors' ), '', array( 'response' => 403 ) ); } }
	public function activation_notice() { if ( current_user_can( 'activate_plugins' ) && get_transient( 'spd_activation_notice' ) ) { delete_transient( 'spd_activation_notice' ); echo '<div class="notice notice-success is-dismissible"><p>Sabri Profiles and Doctors is active. Review the Founder profile and doctor verification dashboard.</p></div>'; } }
}
