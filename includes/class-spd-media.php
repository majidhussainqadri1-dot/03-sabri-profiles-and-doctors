<?php
defined( 'ABSPATH' ) || exit;

final class SPD_Media {
	const OWNER_META='_spd_media_owner_user_id';
	const PURPOSE_META='_spd_media_purpose';
	const STATE_META='_spd_media_state';
	const SCAN_SHA_META='_spd_media_scan_sha256';
	const SCAN_CONTRACT_MIN='1.0.0';

	private static function record_queue_error( $code ) {
		update_option( 'spd_last_media_queue_error', array( 'code' => sanitize_key( $code ), 'at' => SPD_Helpers::now() ), false );
		do_action( 'sabri_file24_media_queue_failure', array( 'owner' => 'file03', 'code' => sanitize_key( $code ), 'at' => SPD_Helpers::now() ) );
	}

	public static function prepare_upload( $user_id, $field, $purpose, array $context=array() ) {
		$user_id=absint($user_id); $purpose=sanitize_key($purpose);
		if ( empty($_FILES[$field]['name']) ) { return array(); }
		if ( ! in_array($purpose,array('avatar','cover'),true) ) { return new WP_Error('spd_media_purpose',__( 'The media purpose is invalid.','sabri-profiles-doctors')); }
		$visibility = SPD_Authorization::normalize_audience( $context['profile_visibility'] ?? 'private' );
		if ( 'public' !== $visibility || ( ! SPD_Membership_Adapter::is_founder( $user_id ) && ! SPD_Membership_Adapter::public_profile_age_eligible( $user_id ) ) ) {
			return new WP_Error( 'spd_media_secure_delivery_required', __( 'Avatar and cover uploads require an adult public profile until the approved secure profile-media delivery contract is available.', 'sabri-profiles-doctors' ), array( 'status' => 409 ) );
		}
		if ( ! SPD_Helpers::consume_rate_limit( 'media_upload_' . $user_id, 10, HOUR_IN_SECONDS ) ) { return new WP_Error('spd_media_rate_limit',__( 'Too many profile uploads were attempted. Try again later.','sabri-profiles-doctors'),array('status'=>429)); }
		$file=$_FILES[$field];
		if ( UPLOAD_ERR_OK!==(int)$file['error'] || (int)$file['size']<1 || (int)$file['size']>5*MB_IN_BYTES ) { return new WP_Error('spd_upload',__( 'The image is invalid or exceeds 5 MB.','sabri-profiles-doctors')); }
		$mimes=array('jpg|jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp');
		$checked=wp_check_filetype_and_ext($file['tmp_name'],$file['name'],$mimes);
		if ( empty($checked['type']) || ! in_array($checked['type'],array_values($mimes),true) ) { return new WP_Error('spd_upload_type',__( 'Only genuine JPG, PNG, or WebP images are accepted.','sabri-profiles-doctors')); }
		$dimensions=@getimagesize($file['tmp_name']); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! is_array($dimensions) || empty($dimensions[0]) || empty($dimensions[1]) || (int)$dimensions[0]*(int)$dimensions[1]>40000000 ) { return new WP_Error('spd_upload_dimensions',__( 'The image dimensions are invalid or too large.','sabri-profiles-doctors')); }
		$minw='avatar'===$purpose?200:640; $minh=200;
		if ( (int)$dimensions[0]<$minw || (int)$dimensions[1]<$minh ) { return new WP_Error('spd_upload_small',__( 'The image dimensions are too small for this profile placement.','sabri-profiles-doctors')); }

		if ( ! self::strip_metadata($file['tmp_name']) ) { return new WP_Error('spd_metadata_removal_failed',__( 'The image could not be safely re-encoded.','sabri-profiles-doctors')); }
		$rechecked=wp_check_filetype_and_ext($file['tmp_name'],$file['name'],$mimes);
		if ( empty($rechecked['type']) || $rechecked['type']!==$checked['type'] ) { return new WP_Error('spd_upload_reencode_mismatch',__( 'The re-encoded image did not retain a valid type.','sabri-profiles-doctors')); }
		if ( ! has_filter('spd_profile_media_scan_v1') ) { return new WP_Error('spd_scan_unavailable',__( 'A compatible profile-image safety scanner is required before upload.','sabri-profiles-doctors'),array('status'=>503)); }
		$scan=apply_filters('spd_profile_media_scan_v1',null,$file['tmp_name'],$rechecked['type'],$user_id,$purpose,SPD_CONTRACT_VERSION);
		if ( ! self::valid_clean_scan($scan,$file['tmp_name']) ) { return new WP_Error('spd_scan_unavailable',__( 'The image safety scan was unavailable, stale, or invalid.','sabri-profiles-doctors'),array('status'=>503)); }

		require_once ABSPATH.'wp-admin/includes/file.php'; require_once ABSPATH.'wp-admin/includes/media.php'; require_once ABSPATH.'wp-admin/includes/image.php';
		$attachment_id=media_handle_upload($field,0,array('post_author'=>$user_id),array('test_form'=>false,'mimes'=>$mimes));
		if ( is_wp_error($attachment_id) ) { return $attachment_id; }
		$attachment_id=absint($attachment_id);
		$required_meta=array(
			self::OWNER_META=>$user_id,
			self::PURPOSE_META=>$purpose,
			self::STATE_META=>'active',
			self::SCAN_SHA_META=>strtolower((string)$scan['sha256']),
		);
		foreach ( $required_meta as $meta_key=>$meta_value ) {
			if ( false === add_post_meta( $attachment_id, $meta_key, $meta_value, true ) ) {
				wp_delete_attachment( $attachment_id, true );
				return new WP_Error( 'spd_media_metadata_persist_failed', __( 'The uploaded image could not be bound to its verified owner and scan evidence, so it was removed.', 'sabri-profiles-doctors' ), array( 'status' => 503 ) );
			}
		}
		$alt=sanitize_text_field((string)($context['alt_text']??''));
		if ( !$alt ) { $alt=sanitize_text_field(SPD_Membership_Adapter::display_name($user_id)); }
		if ( $alt ) { update_post_meta($attachment_id,'_wp_attachment_image_alt',$alt); }
		return array('attachment_id'=>$attachment_id,'state'=>'active','alt_text'=>$alt,'focal_x'=>SPD_Helpers::normalize_focal($context['focal_x']??50),'focal_y'=>SPD_Helpers::normalize_focal($context['focal_y']??50),'scan_provider'=>sanitize_text_field((string)$scan['provider']),'scan_reference'=>sanitize_text_field((string)$scan['reference']),'scan_contract_version'=>sanitize_text_field((string)$scan['contract_version']),'scan_sha256'=>strtolower((string)$scan['sha256']));
	}

	private static function valid_clean_scan( $scan, $path ) {
		if ( ! is_array($scan) || 'clean'!==sanitize_key((string)($scan['status']??'')) ) { return false; }
		if ( empty($scan['provider']) || empty($scan['reference']) || empty($scan['contract_version']) || version_compare((string)$scan['contract_version'],self::SCAN_CONTRACT_MIN,'<') ) { return false; }
		$scanned_at=strtotime((string)($scan['scanned_at']??''));
		if ( false===$scanned_at || abs(time()-$scanned_at)>600 ) { return false; }
		$sha = strtolower( (string) ( $scan['sha256'] ?? '' ) );
		if ( ! preg_match( '/^[0-9a-f]{64}$/', $sha ) ) { return false; }
		$actual = hash_file( 'sha256', $path );
		if ( ! is_string( $actual ) || ! hash_equals( $sha, strtolower( $actual ) ) ) { return false; }
		return true;
	}

	private static function strip_metadata( $path ) {
		if ( ! function_exists('wp_get_image_editor') ) { require_once ABSPATH.'wp-admin/includes/image.php'; }
		$editor=wp_get_image_editor($path); if ( is_wp_error($editor) ) { return false; }
		$editor->set_quality(90); $result=$editor->save($path); if ( is_wp_error($result) ) { return false; }
		if ( ! empty($result['path']) && $result['path']!==$path && file_exists($result['path']) ) { if ( ! copy($result['path'],$path) ) { wp_delete_file($result['path']); return false; } wp_delete_file($result['path']); }
		return file_exists($path) && filesize($path)>0;
	}

	public static function state( $profile_id, $purpose ) {
		global $wpdb; $table=SPD_DB::table('media');
		$wpdb->last_error='';
		$state=$wpdb->get_var($wpdb->prepare("SELECT state FROM {$table} WHERE profile_id=%d AND purpose=%s LIMIT 1",absint($profile_id),sanitize_key($purpose))); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->last_error ) { return 'unknown'; }
		return $state?sanitize_key($state):'removed';
	}

	public static function queue_owned_deletion( $attachment_id, $user_id, $purpose='' ) {
		global $wpdb; $attachment_id=absint($attachment_id); $user_id=absint($user_id); $purpose=sanitize_key($purpose);
		if ( !$attachment_id || absint(get_post_meta($attachment_id,self::OWNER_META,true))!==$user_id ) { return new WP_Error('spd_media_ownership_mismatch',__( 'The media ownership could not be verified.','sabri-profiles-doctors')); }
		$stored=sanitize_key((string)get_post_meta($attachment_id,self::PURPOSE_META,true)); if ( $purpose && $stored!==$purpose ) { return new WP_Error('spd_media_purpose_mismatch',__( 'The media purpose could not be verified.','sabri-profiles-doctors')); }
		$table=SPD_DB::table('deletions'); $now=SPD_Helpers::now();
		$insert=$wpdb->query($wpdb->prepare("INSERT INTO {$table} (deletion_uuid,attachment_id,owner_user_id,purpose,status,attempts,available_at,created_at) VALUES (%s,%d,%d,%s,'pending',0,%s,%s) ON DUPLICATE KEY UPDATE status=IF(status='delivered','delivered','pending'),attempts=IF(status='delivered',attempts,0),available_at=VALUES(available_at),lease_token=IF(status='delivered',lease_token,''),lease_expires=IF(status='delivered',lease_expires,NULL),last_error_code=IF(status='delivered',last_error_code,'')",SPD_Helpers::public_id(),$attachment_id,$user_id,$stored,$now,$now)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return false===$insert?new WP_Error('spd_deletion_queue_failed',__( 'The media deletion could not be queued.','sabri-profiles-doctors')):true;
	}

	public static function reconcile_storage_privacy( $limit = 100 ) {
		global $wpdb;
		if ( class_exists( 'SPD_Schema_Guard' ) && ! SPD_Schema_Guard::base_ready() ) { self::record_queue_error( 'media_privacy_schema_unavailable' ); return 0; }
		if ( ! SPD_DB::tables_exist() ) { self::record_queue_error( 'media_privacy_tables_missing' ); return 0; }
		$limit  = min( 500, max( 1, absint( $limit ) ) );
		$table  = SPD_DB::table( 'profiles' );
		$cursor = absint( get_option( 'spd_media_privacy_cursor', 0 ) );
		$wpdb->last_error='';
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE id>%d AND (avatar_id>0 OR cover_id>0) ORDER BY id ASC LIMIT %d",
				$cursor,
				$limit
			)
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->last_error || ! is_array( $ids ) ) { self::record_queue_error( 'media_privacy_scan_failed' ); return 0; }
		$changed = 0;
		$repo    = SPD_Profile_Repository::instance();
		foreach ( $ids as $profile_id ) {
			$profile_id = absint( $profile_id );
			$wpdb->last_error = '';
			$profile = $repo->find_by_id( $profile_id );
			if ( is_wp_error( $profile ) || $wpdb->last_error || ( is_array( $profile ) && ! empty( $profile['_fields_read_failed'] ) ) ) {
				self::record_queue_error( 'media_privacy_profile_read_failed' );
				return $changed;
			}
			if ( ! $profile ) {
				// The ID came from the same profiles table scan. Treat a vanished row as a
				// concurrent deletion; advancing is safe only after a DB-certain reread.
				$cursor = max( $cursor, $profile_id );
				update_option( 'spd_media_privacy_cursor', $cursor, false );
				continue;
			}
			if ( SPD_Authorization::profile_visibility_allows( $profile, 0 ) ) { $cursor = max( $cursor, $profile_id ); update_option( 'spd_media_privacy_cursor', $cursor, false ); continue; }
			$old = array( 'avatar' => absint( $profile['avatar_id'] ), 'cover' => absint( $profile['cover_id'] ) );
			$result = SPD_DB::transaction(
				function () use ( $wpdb, $table, $profile, $old, $repo ) {
					$updated = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET avatar_id=0,cover_id=0,version=version+1,updated_at=%s WHERE id=%d AND version=%d", SPD_Helpers::now(), $profile['id'], $profile['version'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					if ( 1 !== $updated ) { return new WP_Error( 'spd_media_privacy_conflict', __( 'Profile media privacy reconciliation encountered a concurrent update.', 'sabri-profiles-doctors' ) ); }
					$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM " . SPD_DB::table( 'media' ) . " WHERE profile_id=%d", $profile['id'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					if ( false === $deleted ) { return new WP_Error( 'spd_media_privacy_refs_failed', __( 'Profile media references could not be removed.', 'sabri-profiles-doctors' ) ); }
					foreach ( $old as $purpose => $attachment_id ) {
						if ( ! $attachment_id ) { continue; }
						$queued = self::queue_owned_deletion( $attachment_id, $profile['user_id'], $purpose );
						if ( is_wp_error( $queued ) ) { return $queued; }
					}
					$event = $repo->event( 'ProfileMediaChanged.v1', 'profile', $profile['public_id'], array( 'state' => 'removed_for_privacy', 'reason' => 'profile_not_anonymous_public', 'version' => $profile['version'] + 1 ) );
					return is_wp_error( $event ) ? $event : true;
				}
			);
			if ( is_wp_error( $result ) ) { self::record_queue_error( 'media_privacy_reconcile_failed' ); return $changed; }
			$repo->purge_profile_cache( $profile ); $changed++;
			$cursor = max( $cursor, $profile_id ); update_option( 'spd_media_privacy_cursor', $cursor, false );
		}
		if ( count( $ids ) < $limit ) {
			update_option( 'spd_media_privacy_cursor', 0, false );
			update_option( 'spd_media_privacy_cycle_completed_at', SPD_Helpers::now(), false );
		}
		delete_option( 'spd_last_media_queue_error' );
		return $changed;
	}

	public static function process_deletion_queue( $limit=25 ) {
		global $wpdb; $table=SPD_DB::table('deletions'); $limit=min(100,max(1,absint($limit))); $processed=0; $had_error=false;
		if ( class_exists( 'SPD_Schema_Guard' ) && ! SPD_Schema_Guard::base_ready() ) { self::record_queue_error( 'deletion_schema_unavailable' ); return 0; }
		$wpdb->last_error='';
		$reset=$wpdb->query("UPDATE {$table} SET status='retry',lease_token='',lease_expires=NULL,available_at=UTC_TIMESTAMP(),last_error_code='lease_expired' WHERE status='processing' AND lease_expires<UTC_TIMESTAMP()"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( false===$reset || $wpdb->last_error ) { self::record_queue_error( 'deletion_lease_reset_failed' ); return 0; }
		$wpdb->last_error='';
		$ids=$wpdb->get_col("SELECT id FROM {$table} WHERE status IN ('pending','retry') AND available_at<=UTC_TIMESTAMP() ORDER BY id ASC LIMIT {$limit}"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->last_error || ! is_array( $ids ) ) { self::record_queue_error( 'deletion_queue_read_failed' ); return 0; }
		foreach ( $ids as $id ) {
			$token=hash('sha256',SPD_Helpers::trace_id().':'.$id); $lease=gmdate('Y-m-d H:i:s',time()+300);
			$wpdb->last_error='';
			$claimed=$wpdb->query($wpdb->prepare("UPDATE {$table} SET status='processing',lease_token=%s,lease_expires=%s WHERE id=%d AND status IN ('pending','retry') AND available_at<=UTC_TIMESTAMP()",$token,$lease,absint($id))); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( false===$claimed || $wpdb->last_error ) { self::record_queue_error( 'deletion_claim_failed' ); return $processed; }
			if ( 1!==$claimed ) { continue; }
			$wpdb->last_error='';
			$row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d AND lease_token=%s",absint($id),$token),ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $wpdb->last_error ) { self::record_queue_error( 'deletion_claim_read_failed' ); return $processed; }
			if ( !$row ) { $had_error=true; self::record_queue_error( 'deletion_claim_missing' ); continue; }
			$ok=self::delete_owned(absint($row['attachment_id']),absint($row['owner_user_id']),(string)$row['purpose']); $attempts=absint($row['attempts'])+1;
			if ( $ok || ! get_post(absint($row['attachment_id'])) ) {
				$saved=$wpdb->update($table,array('status'=>'delivered','attempts'=>$attempts,'completed_at'=>SPD_Helpers::now(),'lease_token'=>'','lease_expires'=>null),array('id'=>absint($id),'lease_token'=>$token));
			} else {
				$had_error=true;
				self::record_queue_error( 'attachment_delete_failed' );
				$status=$attempts>=8?'dead':'retry';
				$saved=$wpdb->update($table,array('status'=>$status,'attempts'=>$attempts,'available_at'=>gmdate('Y-m-d H:i:s',time()+min(HOUR_IN_SECONDS,30*(2**min($attempts,6)))),'last_error_code'=>'attachment_delete_failed','lease_token'=>'','lease_expires'=>null),array('id'=>absint($id),'lease_token'=>$token));
			}
			if ( false===$saved ) { self::record_queue_error( 'deletion_result_persist_failed' ); return $processed; }
			if ( 1!==$saved ) { $had_error=true; self::record_queue_error( 'deletion_lease_lost' ); continue; }
			$processed++;
		}
		if ( ! $had_error ) { delete_option( 'spd_last_media_queue_error' ); }
		return $processed;
	}

	public static function requeue_deletion( $deletion_uuid, $actor_id, $reason ) {
		global $wpdb;
		$actor_id = absint( $actor_id );
		$reason = SPD_Helpers::sanitize_multiline( $reason, 500 );
		if ( ! $reason || ! SPD_Membership_Adapter::can_operate_profiles( $actor_id ) ) { return false; }
		$table = SPD_DB::table( 'deletions' );
		$deletion_uuid = sanitize_text_field( $deletion_uuid );
		$updated = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status='retry',attempts=0,available_at=UTC_TIMESTAMP(),lease_token='',lease_expires=NULL,last_error_code='manual_requeue' WHERE deletion_uuid=%s AND status='dead'", $deletion_uuid ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( 1 === $updated ) {
			do_action( 'spd_operational_recovery', array( 'queue' => 'media_deletion', 'reference' => $deletion_uuid, 'actor_id' => $actor_id, 'reason' => $reason, 'at' => SPD_Helpers::now() ) );
			return true;
		}
		return false;
	}

	public static function delete_owned( $attachment_id, $user_id, $purpose='' ) {
		$attachment_id=absint($attachment_id); $owner=absint(get_post_meta($attachment_id,self::OWNER_META,true)); $stored=sanitize_key((string)get_post_meta($attachment_id,self::PURPOSE_META,true));
		if ( $attachment_id && $owner===absint($user_id) && (!$purpose || $stored===sanitize_key($purpose)) ) { update_post_meta($attachment_id,self::STATE_META,'removed'); return (bool)wp_delete_attachment($attachment_id,true); }
		return false;
	}

	public static function complete_scan() { return false; /* Public ingestion is synchronous and fail-closed in rc3. */ }
}
