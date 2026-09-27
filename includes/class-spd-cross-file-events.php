<?php
defined( 'ABSPATH' ) || exit;

/**
 * Cross-file event and File 19 notification bridge for File 03.
 *
 * External domain events are never treated as commands or source-of-truth
 * mutations. They only invalidate File 03 projections/reconciliation state;
 * the next read revalidates the canonical owner contract.
 */
final class SPD_Cross_File_Events {
	const FILE19_PRODUCER = 'file03-profiles';
	const FILE19_OWNER    = 'File 03';
	const FILE19_SCHEMA   = '1.0';

	private static $external_events = array(
		'DoctorVerified.v1',
		'DoctorSuspended.v1',
		'PublicationPublished.v1',
		'ClinicProfileChanged.v1',
		// Current companion-domain naming families.
		'DoctorVerification.Verified',
		'DoctorVerification.Suspended',
		'DoctorVerification.Revoked',
		'DoctorVerification.Expired',
		'DoctorVerification.Reinstated',
		'Publication.Published',
		'Publication.Corrected',
		'Publication.Retracted',
		'ClinicProfile.Changed',
		'Clinic.AvailabilityChanged',
		'Appointment.Changed',
	);

	public static function register() {
		add_action( 'sabri_platform_event', array( __CLASS__, 'consume_external_event' ), 15, 3 );
		add_action( 'sun_event_processed', array( __CLASS__, 'consume_file19_event' ), 20, 3 );
		add_action( 'init', array( __CLASS__, 'register_file19_producer' ), 40 );
	}

	/** Consume current companion facts after File 19 has validated their event envelope. */
	public static function consume_file19_event( $event, $created = 0, $suppressed = 0 ) {
		unset( $created, $suppressed );
		if ( ! is_array( $event ) || empty( $event['event_type'] ) ) { return; }
		$payload = array();
		if ( isset( $event['subject'] ) && is_array( $event['subject'] ) ) { $payload['subject'] = $event['subject']; }
		if ( isset( $event['recipients'][0]['user_id'] ) ) { $payload['user_id'] = absint( $event['recipients'][0]['user_id'] ); }
		self::consume_external_event( (string) $event['event_type'], $payload, array( 'owner' => (string) ( $event['owner'] ?? '' ), 'producer' => (string) ( $event['producer'] ?? 'file19' ) ) );
	}

	public static function register_file19_producer() {
		if ( ! function_exists( 'sun_register_notification_producer' ) ) {
			return false;
		}
		return (bool) sun_register_notification_producer(
			self::FILE19_PRODUCER,
			array(
				'owner'           => self::FILE19_OWNER,
				'event_types'     => array( 'Profile.*' ),
				'schema_versions' => array( self::FILE19_SCHEMA ),
				'allowed_data_fields' => array( 'action_name', 'summary', 'status', 'source_label', 'source_kind', 'group_key' ),
				'internal'        => true,
			)
		);
	}

	public static function file19_health() {
		if ( ! function_exists( 'sun_register_notification_producer' ) || ! function_exists( 'sun_ingest_domain_event' ) ) {
			return 'unavailable';
		}
		return self::register_file19_producer() ? 'compatible' : 'degraded';
	}

	/**
	 * Consume only recognized external facts. The event causes invalidation, not
	 * a local state mutation; canonical owner projections are re-read on demand.
	 */
	public static function consume_external_event( $event_name, $payload = array(), $meta = array() ) {
		$event_name = sanitize_text_field( (string) $event_name );
		if ( ! in_array( $event_name, self::$external_events, true ) ) {
			return;
		}
		$meta = is_array( $meta ) ? $meta : array();
		if ( 'file03' === sanitize_key( (string) ( $meta['owner'] ?? '' ) ) ) {
			return;
		}
		$payload = is_array( $payload ) ? $payload : array();

		try {
			$repo = SPD_Profile_Repository::instance();
			$profile = array();
			$public_id = self::payload_public_id( $payload );
			if ( $public_id ) {
				$profile = $repo->find_by_public_id( $public_id );
			}
			if ( ! is_array( $profile ) || empty( $profile['id'] ) ) {
				$user_id = self::payload_user_id( $payload );
				if ( $user_id ) {
					$profile = $repo->find_by_user_id( $user_id, false );
				}
			}

			if ( is_array( $profile ) && ! empty( $profile['id'] ) ) {
				$repo->purge_profile_cache( $profile );
			} else {
				// Unknown object mapping: invalidate only the generation ledger so
				// no cached public projection can survive an external truth change.
				$generation = SPD_Profile_Repository::cache_generation() + 1;
				update_option( 'spd_profile_cache_generation', $generation, false );
				update_option(
					'spd_reconciliation_required',
					array(
						'event'      => hash( 'sha256', $event_name ),
						'generation' => $generation,
						'changed_at' => SPD_Helpers::now(),
					),
					false
				);
			}
		} catch ( Throwable $exception ) {
			try {
				do_action(
					'sabri_file24_profile_external_event_failure',
					array(
						'owner'           => 'file03',
						'event_hash'      => hash( 'sha256', $event_name ),
						'exception_class' => sanitize_key( get_class( $exception ) ),
						'at'              => SPD_Helpers::now(),
					)
				);
			} catch ( Throwable $ignored ) {}
		}
	}

	/**
	 * Deliver File 03 user-facing domain notifications through File 19 only.
	 * Missing File 19 degrades safely and does not create a parallel transport.
	 *
	 * @return true|WP_Error
	 */
	public static function deliver_file19_notification( $event_name, array $payload, array $row ) {
		$spec = self::notification_spec( (string) $event_name, $payload, $row );
		if ( ! $spec ) {
			return true;
		}
		if ( ! function_exists( 'sun_ingest_domain_event' ) || ! self::register_file19_producer() ) {
			try {
				do_action(
					'sabri_file24_profile_notification_degraded',
					array(
						'owner'      => 'file03',
						'event_hash' => hash( 'sha256', (string) $event_name ),
						'reason'     => 'file19_unavailable',
						'at'         => SPD_Helpers::now(),
					)
				);
			} catch ( Throwable $ignored ) {}
			return new WP_Error( 'spd_file19_notification_unavailable', __( 'Unified notifications are temporarily unavailable; this event will be retried.', 'sabri-profiles-doctors' ) );
		}

		$event_id = sanitize_text_field( (string) ( $row['event_uuid'] ?? '' ) );
		if ( ! $event_id ) {
			return new WP_Error( 'spd_file19_event_id_missing', __( 'The notification event identifier is missing.', 'sabri-profiles-doctors' ) );
		}
		$occurred_at = sanitize_text_field( (string) ( $row['created_at'] ?? '' ) );
		if ( ! $occurred_at ) {
			$occurred_at = gmdate( 'c' );
		}
		$envelope = array(
			'producer'        => self::FILE19_PRODUCER,
			'owner'           => self::FILE19_OWNER,
			'event_id'        => $event_id,
			'event_type'      => $spec['event_type'],
			'schema_version'  => self::FILE19_SCHEMA,
			'occurred_at'     => $occurred_at,
			'recipients'      => array( array( 'user_id' => absint( $spec['recipient_user_id'] ) ) ),
			'subject'         => array( 'type' => $spec['subject_type'], 'id' => $spec['subject_id'] ),
			'trace_id'        => $event_id,
			'category'        => $spec['category'],
			'priority'        => $spec['priority'],
			'sensitivity'     => $spec['sensitivity'],
			'deep_context'    => 'file03-profile',
			'data'            => array(
				'action_name' => $spec['title'],
				'summary'     => $spec['summary'],
				'status'      => $spec['status'],
				'source_label'=> 'Profiles and Doctors',
				'source_kind' => 'file03',
				'group_key'   => $spec['group_key'],
			),
			'source_version'  => defined( 'SPD_VERSION' ) ? SPD_VERSION : 'unknown',
			'idempotency_key' => $event_id,
		);
		$result = sun_ingest_domain_event( $envelope );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$status = is_array( $result ) ? sanitize_key( (string) ( $result['status'] ?? '' ) ) : '';
		if ( in_array( $status, array( 'processed', 'duplicate' ), true ) ) {
			return true;
		}
		return new WP_Error( 'spd_file19_notification_unacknowledged', __( 'File 19 did not acknowledge the profile notification event.', 'sabri-profiles-doctors' ) );
	}

	private static function notification_spec( $event_name, array $payload, array $row ) {
		$event_name = sanitize_text_field( (string) $event_name );
		$recipient = 0;
		$subject_type = 'profile';
		$subject_id = sanitize_text_field( (string) ( $row['aggregate_id'] ?? '' ) );
		$status = sanitize_key( (string) ( $payload['to'] ?? $payload['status'] ?? '' ) );

		if ( 'ProfileReported.v1' === $event_name ) {
			$profile = SPD_Profile_Repository::instance()->find_by_public_id( (string) ( $payload['profile_public_id'] ?? '' ) );
			$recipient = is_array( $profile ) ? absint( $profile['user_id'] ?? 0 ) : 0;
			return $recipient ? array(
				'event_type'=>'Profile.Reported','recipient_user_id'=>$recipient,'subject_type'=>'profile','subject_id'=>(string)($payload['profile_public_id']??''),
				'category'=>'safety','priority'=>'high','sensitivity'=>'restricted','title'=>'Profile report received',
				'summary'=>'A safety or policy report was submitted about your public profile. Review the profile status and available response path.',
				'status'=>'reported','group_key'=>'profile-report',
			) : array();
		}

		if ( 'ProfileModerated.v1' === $event_name ) {
			$profile = SPD_Profile_Repository::instance()->find_by_public_id( $subject_id );
			$recipient = is_array( $profile ) ? absint( $profile['user_id'] ?? 0 ) : 0;
			return $recipient ? array(
				'event_type'=>'Profile.Moderated','recipient_user_id'=>$recipient,'subject_type'=>'profile','subject_id'=>$subject_id,
				'category'=>'administration','priority'=>'high','sensitivity'=>'standard','title'=>'Profile moderation status changed',
				'summary'=>'Your profile moderation status changed. Open your profile to review the current authoritative state and any available correction or appeal path.',
				'status'=>$status ?: 'changed','group_key'=>'profile-moderation',
			) : array();
		}

		if ( in_array( $event_name, array( 'ProfileReportReviewed.v1', 'ProfileReportReopenedByAppeal.v1' ), true ) ) {
			$report = self::report_row( $subject_id );
			$recipient = absint( $report['reporter_user_id'] ?? 0 );
			return $recipient ? array(
				'event_type'=>'Profile.ReportReviewed','recipient_user_id'=>$recipient,'subject_type'=>'profile_report','subject_id'=>$subject_id,
				'category'=>'safety','priority'=>'high','sensitivity'=>'restricted','title'=>'Profile report status changed',
				'summary'=>'A report you submitted has a new review status. Open the platform to view the current authorized outcome.',
				'status'=>$status ?: 'reviewed','group_key'=>'profile-report-review',
			) : array();
		}

		if ( 'ProfileReportAppealReviewed.v1' === $event_name ) {
			$appeal_uuid = sanitize_text_field( (string) ( $payload['appeal_uuid'] ?? '' ) );
			$recipient = self::appeal_requester( $appeal_uuid );
			return $recipient ? array(
				'event_type'=>'Profile.ReportAppealReviewed','recipient_user_id'=>$recipient,'subject_type'=>'profile_report','subject_id'=>$subject_id,
				'category'=>'administration','priority'=>'high','sensitivity'=>'restricted','title'=>'Profile report appeal reviewed',
				'summary'=>'Your profile-report appeal has been reviewed. Open the platform to view the current authorized outcome.',
				'status'=>$status ?: 'reviewed','group_key'=>'profile-report-appeal',
			) : array();
		}

		return array();
	}

	private static function report_row( $report_uuid ) {
		global $wpdb;
		if ( ! SPD_Helpers::valid_uuid( (string) $report_uuid ) ) {
			return array();
		}
		$table = SPD_DB::table( 'reports' );
		$wpdb->last_error = '';
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT reporter_user_id,profile_id,status FROM {$table} WHERE report_uuid=%s LIMIT 1", sanitize_text_field( $report_uuid ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->last_error || ! is_array( $row ) ? array() : $row;
	}

	private static function appeal_requester( $appeal_uuid ) {
		global $wpdb;
		if ( ! SPD_Helpers::valid_uuid( (string) $appeal_uuid ) ) {
			return 0;
		}
		$table = SPD_Central_Profile::appeals_table();
		$wpdb->last_error = '';
		$user_id = $wpdb->get_var( $wpdb->prepare( "SELECT requested_by FROM {$table} WHERE appeal_uuid=%s LIMIT 1", sanitize_text_field( $appeal_uuid ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->last_error ? 0 : absint( $user_id );
	}

	private static function payload_user_id( array $payload ) {
		foreach ( array( 'user_id', 'doctor_user_id', 'profile_user_id', 'owner_user_id' ) as $key ) {
			if ( ! empty( $payload[ $key ] ) ) {
				return absint( $payload[ $key ] );
			}
		}
		if ( isset( $payload['subject'] ) && is_array( $payload['subject'] ) && ! empty( $payload['subject']['user_id'] ) ) {
			return absint( $payload['subject']['user_id'] );
		}
		return 0;
	}

	private static function payload_public_id( array $payload ) {
		foreach ( array( 'profile_public_id', 'public_id' ) as $key ) {
			$value = isset( $payload[ $key ] ) ? sanitize_text_field( (string) $payload[ $key ] ) : '';
			if ( $value && SPD_Helpers::valid_uuid( $value ) ) {
				return $value;
			}
		}
		if ( isset( $payload['subject'] ) && is_array( $payload['subject'] ) ) {
			$value = sanitize_text_field( (string) ( $payload['subject']['public_id'] ?? '' ) );
			if ( $value && SPD_Helpers::valid_uuid( $value ) ) {
				return $value;
			}
		}
		return '';
	}
}
