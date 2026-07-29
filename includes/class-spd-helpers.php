<?php
defined( 'ABSPATH' ) || exit;

class SPD_Helpers {
	const META = '_spd_';

	public static function fields() {
		return array(
			'account_type'       => 'Account type',
			'country'            => 'Country',
			'city'               => 'City',
			'clinic'             => 'Clinic',
			'qualification'      => 'Qualification',
			'licence_number'     => 'Licence / registration number',
			'experience_years'   => 'Years of experience',
			'specialty'          => 'Specialty',
			'languages'          => 'Languages',
			'studied_books'      => 'Classical books studied',
			'consultation_modes' => 'Consultation modes',
			'phone'              => 'Phone number',
			'whatsapp'           => 'WhatsApp number',
			'bio'                => 'Professional introduction',
		);
	}

	public static function get( $user_id, $key, $default = '' ) {
		$value = get_user_meta( absint( $user_id ), self::META . $key, true );
		if ( '' === $value && in_array( $key, array( 'account_type', 'phone', 'country', 'city' ), true ) ) {
			$value = get_user_meta( absint( $user_id ), '_sa_' . $key, true );
		}
		if ( '' === $value && 'bio' === $key ) {
			$user  = get_userdata( absint( $user_id ) );
			$value = $user ? $user->description : '';
		}
		return '' === $value ? $default : $value;
	}

	public static function clean_phone( $value ) {
		$value = preg_replace( '/[^0-9+]/', '', (string) $value );
		$value = preg_replace( '/(?!^)\+/', '', $value );
		return substr( $value, 0, 18 );
	}

	public static function whatsapp_url( $number ) {
		$digits = preg_replace( '/\D+/', '', (string) $number );
		return $digits ? 'https://wa.me/' . $digits : '';
	}

	public static function verification_status( $user_id ) {
		$status  = self::get( $user_id, 'verification_status', 'pending' );
		$allowed = array( 'pending', 'under_review', 'verified', 'more_info', 'rejected', 'suspended' );
		return in_array( $status, $allowed, true ) ? $status : 'pending';
	}

	public static function status_label( $status ) {
		$labels = array(
			'pending'      => 'Pending',
			'under_review' => 'Under review',
			'verified'     => 'Verified',
			'more_info'    => 'More information required',
			'rejected'     => 'Not approved',
			'suspended'    => 'Suspended',
		);
		return isset( $labels[ $status ] ) ? $labels[ $status ] : $labels['pending'];
	}

	public static function is_doctor( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}
		return 'doctor' === self::get( $user_id, 'account_type' ) || array_intersect( array( 'sabri_doctor_pending', 'sabri_doctor_verified' ), (array) $user->roles );
	}

	public static function can_show_contact( $user_id, $founder = false ) {
		return $founder || self::is_doctor( $user_id ) || '1' === self::get( $user_id, 'public_contact', '0' );
	}

	public static function profile_url( $user_id ) {
		$pages = get_option( 'spd_page_map', array() );
		$base  = ! empty( $pages['profile'] ) ? get_permalink( $pages['profile'] ) : home_url( '/' );
		$user  = get_userdata( $user_id );
		return add_query_arg( 'user', $user ? $user->user_nicename : absint( $user_id ), $base );
	}

	public static function founder() {
		$defaults = array(
			'name'         => 'Dr. Allama Majid Hussain Sabri',
			'title'        => 'Founder — Sabri Social Homeopathy Platform',
			'location'     => 'Gujrat, Punjab, Pakistan',
			'phone'        => '+923494143244',
			'whatsapp'     => '+923494143244',
			'introduction' => 'Holistic medicine practitioner, homeopathy researcher, author, teacher and founder working across classical homeopathy, nutrition, health education and Islamic spiritual wellbeing.',
			'mission'      => 'To organize reliable learning, professional connection and responsible public education in one accessible global platform.',
			'vision'       => 'A multilingual knowledge community where students, practitioners and the public can learn, connect and make informed health decisions.',
			'objectives'    => 'To support doctors and students with structured knowledge, encourage ethical professional collaboration, make educational books readable online and help the public find transparent practitioner information.',
			'methodology'   => 'An individualized framework centered on careful symptom observation, classical homeopathic study, nutrition, hygiene and spiritual wellbeing. Appropriate medical testing, urgent care and qualified clinical referral remain important whenever needed.',
			'experience'   => 'Fifteen years of clinical observation, teaching, writing and independent research beginning in Gujrat and Phalia, Pakistan.',
			'research'     => 'Classical homeopathy, materia medica, repertory, nutrition, pathology, clinical observation and responsible digital health education.',
			'publications' => "Sabri Materia Medica (Third Expanded Edition)\nSabri Nutrition Science\nQawaneen-e-Sabri for Hygiene and Dietary Reform\nSabri Anwar-e-Shifa: Islamic and Spiritual Healing\nPhilosophy of Sabri Homeopathy\nComprehensive Sabri Clinical Pathology and Experienced Homeopathic Medicines\nManhaj-e-Sabri Homeopathic Repertory and Key to Use\nDastoor-e-Daulat-o-Kamyabi",
			'photo_id'     => 0,
			'cover_id'     => 0,
		);
		return wp_parse_args( (array) get_option( 'spd_founder_profile', array() ), $defaults );
	}

	public static function audit( $target, $old, $new, $reason = '' ) {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'spd_audit_log',
			array(
				'actor_id'      => get_current_user_id(),
				'target_user_id'=> absint( $target ),
				'old_status'    => sanitize_key( $old ),
				'new_status'    => sanitize_key( $new ),
				'reason'        => sanitize_textarea_field( $reason ),
				'created_at'    => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s' )
		);
	}
}
