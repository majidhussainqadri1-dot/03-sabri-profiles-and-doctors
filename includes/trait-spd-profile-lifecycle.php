<?php
defined( 'ABSPATH' ) || exit;

require_once SPD_DIR . 'includes/trait-spd-profile-report-appeals.php';

trait SPD_Profile_Lifecycle {
	use SPD_Profile_Report_Appeals;

	public function completeness( array $profile, array $claims, array $professional ) {
		$required=array('display_name'=>!empty($claims['display_name']),'bio'=>!empty($profile['bio']),'avatar'=>!empty($profile['avatar_id']),'country'=>!empty($profile['country']),'languages'=>!empty($profile['languages']));
		if('doctor'===$profile['profile_type']){$required['verification']=SPD_Verification_Adapter::is_verified($profile['user_id']);$required['qualification']=!empty($professional['qualification']);$required['specialty']=!empty($professional['specialty']);}
		$complete=count(array_filter($required));$total=count($required);return array('complete_items'=>$complete,'total_items'=>$total,'missing'=>array_keys(array_filter($required,static function($done){return!$done;})),'label'=>$complete===$total?__( 'Core profile complete','sabri-profiles-doctors'):__( 'Complete the missing profile information','sabri-profiles-doctors'));
	}

	public function erase_profile( $user_id ) {
		global $wpdb;$user_id=absint($user_id);
		$wpdb->last_error='';
		$profile=$this->find_by_user_id($user_id,false);
		if($wpdb->last_error){return array('removed'=>false,'retained'=>true,'retry'=>true,'messages'=>array(__( 'Profile data could not be read safely for erasure; retry is required.','sabri-profiles-doctors')));}
		if(!$profile){return array('removed'=>false,'retained'=>false,'messages'=>array());}
		try {
			$legal_hold=(bool)apply_filters('spd_profile_legal_hold',false,$user_id,$profile);
		} catch ( Throwable $exception ) {
			try { do_action('sabri_file24_profile_provider_failure',array('owner'=>'file03','provider'=>'profile_legal_hold','surface'=>'profile_erasure','exception_class'=>sanitize_key(get_class($exception)),'at'=>SPD_Helpers::now())); } catch ( Throwable $ignored ) {}
			return array('removed'=>false,'retained'=>true,'retry'=>true,'messages'=>array(__( 'Profile erasure is temporarily paused because retention or legal-hold status could not be verified safely.','sabri-profiles-doctors')));
		}
		if($legal_hold){return array('removed'=>false,'retained'=>true,'messages'=>array(__( 'Profile data is retained under an active legal or governance hold.','sabri-profiles-doctors')));}
		// R17 — the persisted institutional Founder identity is an independent
		// fail-closed guard. File 00 outage/claim uncertainty must never turn the
		// official Founder profile into an erasable ordinary profile.
		if('founder'===sanitize_key((string)($profile['profile_type']??''))||SPD_Membership_Adapter::is_founder($user_id)){return array('removed'=>false,'retained'=>true,'messages'=>array(__( 'The official Founder profile requires an authorized governance decision before removal.','sabri-profiles-doctors')));}
		$profiles=SPD_DB::table('profiles');$fields=SPD_DB::table('fields');$media=SPD_DB::table('media');$attachments=array('avatar'=>absint($profile['avatar_id']),'cover'=>absint($profile['cover_id']));
		$result=SPD_DB::transaction(function() use($wpdb,$profile,$profiles,$fields,$media,$attachments,$user_id){
			foreach($attachments as $purpose=>$attachment_id){if($attachment_id){$queued=SPD_Media::queue_owned_deletion($attachment_id,$user_id,$purpose);if(is_wp_error($queued)){return $queued;}}}
			$updated=$wpdb->query($wpdb->prepare("UPDATE {$profiles} SET state='tombstoned',bio='',country='',city='',languages='',studied_books='',avatar_id=0,cover_id=0,version=version+1,updated_at=%s WHERE id=%d AND version=%d",SPD_Helpers::now(),$profile['id'],$profile['version'])); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if(1!==$updated){return new WP_Error('spd_version_conflict',__( 'This profile changed before erasure could complete.','sabri-profiles-doctors'),array('status'=>409));}
			if(false===$wpdb->query($wpdb->prepare("DELETE FROM {$fields} WHERE profile_id=%d",$profile['id']))){return new WP_Error('spd_erasure_fields_failed',__( 'Profile fields could not be erased.','sabri-profiles-doctors'));}
			if(false===$wpdb->query($wpdb->prepare("DELETE FROM {$media} WHERE profile_id=%d",$profile['id']))){return new WP_Error('spd_erasure_media_refs_failed',__( 'Profile media references could not be erased.','sabri-profiles-doctors'));}
			$event=$this->event('ProfileTombstoned.v1','profile',$profile['public_id'],array('user_id'=>$profile['user_id'],'version'=>$profile['version']+1));return is_wp_error($event)?$event:true;
		});
		if(is_wp_error($result)){return array('removed'=>false,'retained'=>true,'retry'=>true,'messages'=>array($result->get_error_message()));}
		$this->purge_profile_cache($profile);SPD_Media::process_deletion_queue(10);
		return array('removed'=>true,'retained'=>true,'messages'=>array(
			__( 'Personal profile fields are erased. A minimal public-ID tombstone, audit events, and retryable media-deletion ledger remain for integrity and secure cleanup.','sabri-profiles-doctors'),
			__( 'Canonical and historical profile slug aliases are retained as permanent redirect/citation-integrity records; they no longer expose erased profile fields.','sabri-profiles-doctors')
		));
	}
}
