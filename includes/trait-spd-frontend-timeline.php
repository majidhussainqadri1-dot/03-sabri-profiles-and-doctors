<?php
defined( 'ABSPATH' ) || exit;

trait SPD_Frontend_Timeline {
	public function timeline() {
		$public_id = sanitize_text_field( (string) get_query_var( 'spd_public_id' ) );
		$cursor = isset( $_GET['cursor'] ) ? sanitize_text_field( wp_unslash( $_GET['cursor'] ) ) : '';
		$provider = isset( $_GET['provider'] ) ? sanitize_key( wp_unslash( $_GET['provider'] ) ) : '';
		$result = SPD_Timeline::query( $public_id, array( 'limit' => 20, 'cursor' => $cursor, 'provider' => $provider ), get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			return $this->notice( $result->get_error_message(), 'error' );
		}
		$profile = SPD_Profile_Repository::instance()->public_dto( $public_id, get_current_user_id() );
		if ( is_wp_error( $profile ) ) { return $this->notice( $profile->get_error_message(), 'error' ); }
		ob_start(); ?>
		<main class="spd" aria-labelledby="spd-timeline-title">
			<header class="spd-page-header"><h1 id="spd-timeline-title"><?php echo esc_html( sprintf( __( '%s — Timeline', 'sabri-profiles-doctors' ), $profile['display_name'] ) ); ?></h1><p><a href="<?php echo esc_url( $profile['canonical_url'] ); ?>"><?php esc_html_e( 'Back to profile', 'sabri-profiles-doctors' ); ?></a></p></header>
			<?php if ( $result['partial'] ) : ?><div class="spd-notice spd-notice--warning" role="status"><?php esc_html_e( 'Some timeline providers are temporarily unavailable. Available items are shown.', 'sabri-profiles-doctors' ); ?></div><?php endif; ?>
			<div class="spd-timeline">
				<?php if ( empty( $result['items'] ) ) : ?><div class="spd-empty"><h2><?php esc_html_e( 'No public timeline items yet', 'sabri-profiles-doctors' ); ?></h2><p><?php esc_html_e( 'Owner modules have not supplied eligible public items for this profile.', 'sabri-profiles-doctors' ); ?></p></div><?php endif; ?>
				<?php foreach ( $result['items'] as $item ) : ?><article class="spd-card spd-timeline-item"><p class="spd-meta"><?php echo esc_html( strtoupper( $item['provider'] ) . ' · ' . mysql2date( get_option( 'date_format' ), $item['published_at'], true ) ); ?></p><h2><a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['title'] ); ?></a></h2><?php if ( $item['excerpt'] ) : ?><div><?php echo wp_kses_post( wpautop( $item['excerpt'] ) ); ?></div><?php endif; ?><?php if ( 'retracted' === $item['status'] ) : ?><p class="spd-notice spd-notice--warning"><?php esc_html_e( 'This item has been retracted by its owner module.', 'sabri-profiles-doctors' ); ?></p><?php endif; ?></article><?php endforeach; ?>
			</div>
			<?php if ( $result['next_cursor'] ) : ?><p><a class="spd-btn" href="<?php echo esc_url( add_query_arg( array( 'cursor' => $result['next_cursor'], 'provider' => $provider ), SPD_Helpers::timeline_url( $public_id ) ) ); ?>"><?php esc_html_e( 'Load older items', 'sabri-profiles-doctors' ); ?></a></p><?php endif; ?>
		</main>
		<?php return ob_get_clean();
	}

}
