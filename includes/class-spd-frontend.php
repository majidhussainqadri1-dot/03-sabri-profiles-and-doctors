<?php
defined( 'ABSPATH' ) || exit;

final class SPD_Frontend {
	use SPD_Frontend_Profile;
	use SPD_Frontend_Timeline;
	use SPD_Frontend_Edit;
	use SPD_Frontend_Report;
	use SPD_Frontend_Helpers;
	use SPD_Frontend_Central;

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
}
