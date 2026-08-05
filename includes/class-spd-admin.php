<?php
defined( 'ABSPATH' ) || exit;

final class SPD_Admin {
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_spd_toggle_safe_mode', array( $this, 'toggle_safe_mode' ) );
		add_action( 'admin_post_spd_repair', array( $this, 'repair' ) );
		add_action( 'admin_post_spd_update_report', array( $this, 'update_report' ) );
		add_action( 'admin_notices', array( $this, 'dependency_notice' ) );
	}

	public function menu() {
		if ( ! $this->can_operate() ) {
			return;
		}
		$parent = defined( 'SABRI_SHELL_VERSION' ) ? 'sabri-shell' : 'tools.php';
		add_submenu_page( $parent, __( 'Profile System Check', 'sabri-profiles-doctors' ), __( 'Profile System Check', 'sabri-profiles-doctors' ), 'read', 'sabri-profiles-system-check', array( $this, 'status_page' ) );
		add_submenu_page( $parent, __( 'Profile Reports', 'sabri-profiles-doctors' ), __( 'Profile Reports', 'sabri-profiles-doctors' ), 'read', 'sabri-profile-reports', array( $this, 'reports_page' ) );
	}

	private function can_operate() {
		return SPD_Membership_Adapter::can_moderate_profiles( get_current_user_id() ) || SPD_Membership_Adapter::can_manage_founder( get_current_user_id() );
	}

	public function status_page() {
		if ( ! $this->can_operate() ) {
			wp_die( esc_html__( 'Access denied.', 'sabri-profiles-doctors' ), '', array( 'response' => 403 ) );
		}
		$health = SPD_Observability::health_report();
		$dry_run = SPD_Observability::repair( false );
		?>
		<div class="wrap spd-admin">
			<h1><?php esc_html_e( 'File 03 — Profile System Check', 'sabri-profiles-doctors' ); ?></h1>
			<p><?php esc_html_e( 'This page reports File 03-owned health only. It never repairs or mutates companion-module data.', 'sabri-profiles-doctors' ); ?></p>
			<table class="widefat striped"><tbody>
			<?php foreach ( $health as $key => $value ) : ?><tr><th><?php echo esc_html( ucwords( str_replace( '_', ' ', $key ) ) ); ?></th><td><code><?php echo esc_html( is_scalar( $value ) ? (string) $value : wp_json_encode( $value ) ); ?></code></td></tr><?php endforeach; ?>
			</tbody></table>
			<h2><?php esc_html_e( 'Repair dry run', 'sabri-profiles-doctors' ); ?></h2>
			<?php if ( $dry_run['actions'] ) : ?><ul><?php foreach ( $dry_run['actions'] as $action ) : ?><li><code><?php echo esc_html( $action ); ?></code></li><?php endforeach; ?></ul><?php else : ?><p><?php esc_html_e( 'No File 03-owned repair action is currently required.', 'sabri-profiles-doctors' ); ?></p><?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="spd_repair"><?php wp_nonce_field( 'spd_repair' ); ?><button class="button" type="submit"<?php disabled( empty( $dry_run['actions'] ) ); ?>><?php esc_html_e( 'Execute File 03 repair plan', 'sabri-profiles-doctors' ); ?></button></form>
			<h2><?php esc_html_e( 'Safe mode', 'sabri-profiles-doctors' ); ?></h2>
			<p><?php echo SPD_Observability::safe_mode() ? esc_html__( 'Safe mode is active. Public safe reading remains available; profile mutations are disabled.', 'sabri-profiles-doctors' ) : esc_html__( 'Safe mode is not active.', 'sabri-profiles-doctors' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="spd_toggle_safe_mode"><input type="hidden" name="enabled" value="<?php echo SPD_Observability::safe_mode() ? '0' : '1'; ?>"><?php wp_nonce_field( 'spd_toggle_safe_mode' ); ?><label><?php esc_html_e( 'Reason', 'sabri-profiles-doctors' ); ?><input class="regular-text" name="reason" required></label> <button class="button button-primary" type="submit"><?php echo SPD_Observability::safe_mode() ? esc_html__( 'Disable safe mode', 'sabri-profiles-doctors' ) : esc_html__( 'Enable safe mode', 'sabri-profiles-doctors' ); ?></button></form>
		</div>
		<?php
	}

	public function reports_page() {
		global $wpdb;
		if ( ! $this->can_operate() ) {
			wp_die( esc_html__( 'Access denied.', 'sabri-profiles-doctors' ), '', array( 'response' => 403 ) );
		}
		$table = SPD_DB::table( 'reports' );
		$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 100", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		?>
		<div class="wrap spd-admin"><h1><?php esc_html_e( 'Profile Reports', 'sabri-profiles-doctors' ); ?></h1><p><?php esc_html_e( 'Only File 03 report orchestration is shown. Identity evidence and companion-domain decisions remain with their canonical owners.', 'sabri-profiles-doctors' ); ?></p><table class="widefat striped"><thead><tr><th><?php esc_html_e( 'Report', 'sabri-profiles-doctors' ); ?></th><th><?php esc_html_e( 'Reason', 'sabri-profiles-doctors' ); ?></th><th><?php esc_html_e( 'Status', 'sabri-profiles-doctors' ); ?></th><th><?php esc_html_e( 'Created', 'sabri-profiles-doctors' ); ?></th><th><?php esc_html_e( 'Action', 'sabri-profiles-doctors' ); ?></th></tr></thead><tbody><?php if ( ! $rows ) : ?><tr><td colspan="5"><?php esc_html_e( 'No profile reports.', 'sabri-profiles-doctors' ); ?></td></tr><?php endif; ?><?php foreach ( $rows as $row ) : ?><tr><td><code><?php echo esc_html( $row['report_uuid'] ); ?></code><br><?php echo esc_html( wp_trim_words( $row['details'], 20 ) ); ?></td><td><?php echo esc_html( $row['reason'] ); ?></td><td><?php echo esc_html( $row['status'] ); ?> (v<?php echo esc_html( $row['version'] ); ?>)</td><td><?php echo esc_html( $row['created_at'] ); ?></td><td><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="spd_update_report"><input type="hidden" name="report_uuid" value="<?php echo esc_attr( $row['report_uuid'] ); ?>"><input type="hidden" name="version" value="<?php echo esc_attr( $row['version'] ); ?>"><?php wp_nonce_field( 'spd_update_report_' . $row['report_uuid'] ); ?><select name="status"><?php foreach ( array( 'triaged', 'in_review', 'actioned', 'rejected', 'closed' ) as $status ) : ?><option value="<?php echo esc_attr( $status ); ?>" <?php selected( $row['status'], $status ); ?>><?php echo esc_html( $status ); ?></option><?php endforeach; ?></select><input name="note" placeholder="<?php esc_attr_e( 'Review note', 'sabri-profiles-doctors' ); ?>" maxlength="1000"><button class="button" type="submit"><?php esc_html_e( 'Update', 'sabri-profiles-doctors' ); ?></button></form></td></tr><?php endforeach; ?></tbody></table></div>
		<?php
	}

	public function update_report() {
		if ( ! $this->can_operate() ) {
			wp_die( esc_html__( 'Access denied.', 'sabri-profiles-doctors' ), '', array( 'response' => 403 ) );
		}
		$uuid = sanitize_text_field( wp_unslash( $_POST['report_uuid'] ?? '' ) );
		check_admin_referer( 'spd_update_report_' . $uuid );
		$result = SPD_Profile_Repository::instance()->moderate_report( $uuid, get_current_user_id(), wp_unslash( $_POST['status'] ?? '' ), absint( $_POST['version'] ?? 0 ), wp_unslash( $_POST['note'] ?? '' ) );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), '', array( 'response' => 409, 'back_link' => true ) );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=sabri-profile-reports' ) );
		exit;
	}

	public function toggle_safe_mode() {
		if ( ! $this->can_operate() ) {
			wp_die( esc_html__( 'Access denied.', 'sabri-profiles-doctors' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'spd_toggle_safe_mode' );
		$enabled = ! empty( $_POST['enabled'] );
		$reason = sanitize_text_field( wp_unslash( $_POST['reason'] ?? '' ) );
		if ( '' === $reason ) {
			wp_die( esc_html__( 'A reason is required.', 'sabri-profiles-doctors' ), '', array( 'response' => 400, 'back_link' => true ) );
		}
		SPD_Observability::set_safe_mode( $enabled, $reason );
		wp_safe_redirect( admin_url( 'admin.php?page=sabri-profiles-system-check' ) );
		exit;
	}

	public function repair() {
		if ( ! $this->can_operate() ) {
			wp_die( esc_html__( 'Access denied.', 'sabri-profiles-doctors' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'spd_repair' );
		SPD_Observability::repair( true );
		wp_safe_redirect( admin_url( 'admin.php?page=sabri-profiles-system-check' ) );
		exit;
	}

	public function dependency_notice() {
		$health = SPD_Membership_Adapter::health();
		if ( 'available' !== $health['status'] && current_user_can( 'activate_plugins' ) ) {
			echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Sabri Profiles and Doctors:', 'sabri-profiles-doctors' ) . '</strong> ' . esc_html__( 'File 00 — Sabri Membership Core is missing or incompatible. File 03 has failed closed and does not expose or mutate profiles.', 'sabri-profiles-doctors' ) . '</p></div>';
		}
	}
}
