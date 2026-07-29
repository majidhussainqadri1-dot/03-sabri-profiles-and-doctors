<?php
defined( 'ABSPATH' ) || exit;

class SPD_Privacy {
	public function hooks() {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'erasers' ) );
	}

	public function exporters( $exporters ) {
		$exporters['sabri-profiles'] = array( 'exporter_friendly_name' => 'Sabri profile information', 'callback' => array( $this, 'export' ) );
		return $exporters;
	}

	public function erasers( $erasers ) {
		$erasers['sabri-profiles'] = array( 'eraser_friendly_name' => 'Sabri profile information', 'callback' => array( $this, 'erase' ) );
		return $erasers;
	}

	public function export( $email, $page = 1 ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user || $page > 1 ) { return array( 'data' => array(), 'done' => true ); }
		$data = array();
		foreach ( SPD_Helpers::fields() as $key => $label ) { $value = SPD_Helpers::get( $user->ID, $key ); if ( '' !== $value ) { $data[] = array( 'name' => $label, 'value' => $value ); } }
		$data[] = array( 'name' => 'Public contact consent', 'value' => SPD_Helpers::get( $user->ID, 'public_contact', '0' ) );
		$data[] = array( 'name' => 'Doctor verification status', 'value' => SPD_Helpers::verification_status( $user->ID ) );
		return array( 'data' => array( array( 'group_id' => 'sabri-profile', 'group_label' => 'Sabri Profile', 'item_id' => 'user-' . $user->ID, 'data' => $data ) ), 'done' => true );
	}

	public function erase( $email, $page = 1 ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user || $page > 1 ) { return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true ); }
		$removed = false;
		foreach ( array_keys( SPD_Helpers::fields() ) as $key ) { $removed = delete_user_meta( $user->ID, '_spd_' . $key ) || $removed; }
		foreach ( array( 'public_contact', 'profile_photo_id', 'cover_photo_id' ) as $key ) { $removed = delete_user_meta( $user->ID, '_spd_' . $key ) || $removed; }
		return array( 'items_removed' => $removed, 'items_retained' => SPD_Helpers::is_doctor( $user->ID ), 'messages' => SPD_Helpers::is_doctor( $user->ID ) ? array( 'Doctor verification status and audit history are retained for platform integrity and legal review.' ) : array(), 'done' => true );
	}
}

