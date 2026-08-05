<?php
defined( 'ABSPATH' ) || exit;

final class SPD_Privacy {
	public function hooks() {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'erasers' ) );
	}

	public function exporters( $exporters ) {
		$exporters['sabri-profile-domain'] = array(
			'exporter_friendly_name' => __( 'Sabri profile domain', 'sabri-profiles-doctors' ),
			'callback'               => array( $this, 'export' ),
		);
		return $exporters;
	}

	public function erasers( $erasers ) {
		$erasers['sabri-profile-domain'] = array(
			'eraser_friendly_name' => __( 'Sabri profile domain', 'sabri-profiles-doctors' ),
			'callback'             => array( $this, 'erase' ),
		);
		return $erasers;
	}

	public function export( $email, $page = 1 ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user || $page > 1 ) {
			return array( 'data' => array(), 'done' => true );
		}
		$repo = SPD_Profile_Repository::instance();
		$profile = $repo->find_by_user_id( $user->ID, false );
		if ( ! $profile ) {
			return array( 'data' => array(), 'done' => true );
		}
		$data = array(
			array( 'name' => 'Public profile ID', 'value' => $profile['public_id'] ),
			array( 'name' => 'Canonical slug', 'value' => $profile['slug'] ),
			array( 'name' => 'Profile type', 'value' => $profile['profile_type'] ),
			array( 'name' => 'Profile state', 'value' => $profile['state'] ),
			array( 'name' => 'Version', 'value' => (string) $profile['version'] ),
			array( 'name' => 'Biography', 'value' => $profile['bio'] ),
			array( 'name' => 'Country', 'value' => $profile['country'] ),
			array( 'name' => 'City', 'value' => $profile['city'] ),
			array( 'name' => 'Languages', 'value' => $profile['languages'] ),
			array( 'name' => 'Classical books studied', 'value' => $profile['studied_books'] ),
			array( 'name' => 'Avatar attachment ID', 'value' => (string) $profile['avatar_id'] ),
			array( 'name' => 'Cover attachment ID', 'value' => (string) $profile['cover_id'] ),
		);
		foreach ( $profile['fields'] as $key => $field ) {
			$data[] = array( 'name' => 'Audience: ' . $key, 'value' => $field['audience'] );
		}
		return array(
			'data' => array(
				array(
					'group_id'    => 'sabri-profile-domain',
					'group_label' => __( 'Sabri Profile', 'sabri-profiles-doctors' ),
					'item_id'     => 'profile-' . $profile['public_id'],
					'data'        => $data,
				),
			),
			'done' => true,
		);
	}

	public function erase( $email, $page = 1 ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user || $page > 1 ) {
			return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true );
		}
		$result = SPD_Profile_Repository::instance()->erase_profile( $user->ID );
		return array(
			'items_removed'  => ! empty( $result['removed'] ),
			'items_retained' => ! empty( $result['retained'] ),
			'messages'       => $result['messages'] ?? array(),
			'done'           => true,
		);
	}
}
