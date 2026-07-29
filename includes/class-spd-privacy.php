<?php
defined( 'ABSPATH' ) || exit;

final class SPD_Privacy {
	public function hooks() {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'erasers' ) );
	}
	public function exporters($exporters){$exporters['sabri-profile-projection']=array('exporter_friendly_name'=>'Sabri profile presentation','callback'=>array($this,'export'));return $exporters;}
	public function erasers($erasers){$erasers['sabri-profile-projection']=array('eraser_friendly_name'=>'Sabri profile presentation','callback'=>array($this,'erase'));return $erasers;}

	public function export($email,$page=1){$user=get_user_by('email',$email);if(!$user||$page>1){return array('data'=>array(),'done'=>true);} $data=array(
		array('name'=>'Profile visibility','value'=>SPD_Membership_Adapter::public_visibility($user->ID)),
		array('name'=>'Public contact consent','value'=>get_user_meta($user->ID,'_spd_public_contact',true)?'Yes':'No'),
		array('name'=>'Profile image attachment ID','value'=>(string)absint(get_user_meta($user->ID,'_spd_profile_photo_id',true))),
		array('name'=>'Cover image attachment ID','value'=>(string)absint(get_user_meta($user->ID,'_spd_cover_photo_id',true))),
		array('name'=>'Projected verification status','value'=>SPD_Verification_Adapter::status($user->ID)),
	);return array('data'=>array(array('group_id'=>'sabri-profile-projection','group_label'=>'Sabri Profile Presentation','item_id'=>'user-'.$user->ID,'data'=>$data)),'done'=>true);}

	public function erase($email,$page=1){$user=get_user_by('email',$email);if(!$user||$page>1){return array('items_removed'=>false,'items_retained'=>false,'messages'=>array(),'done'=>true);} $removed=false;$retained=false;$messages=array();
		foreach(array('profile_photo_id'=>'profile','cover_photo_id'=>'cover')as$key=>$purpose){$id=absint(get_user_meta($user->ID,'_spd_'.$key,true));if($id){if(SPD_Media::delete_owned($id,$user->ID,$purpose)){$removed=true;}else{$retained=true;$messages[]='A referenced media item was not deleted because File 03 could not prove ownership.';}}$removed=delete_user_meta($user->ID,'_spd_'.$key)||$removed;}
		$removed=delete_user_meta($user->ID,SPD_Verification_Adapter::SNAPSHOT_META)||$removed;
		update_user_meta($user->ID,'_spd_profile_visibility','private');$removed=true;
		update_user_meta($user->ID,'_spd_public_contact','0');$removed=true;
		if(SPD_Membership_Adapter::is_doctor($user->ID)){$retained=true;$messages[]='Identity, credentials, doctor-verification decisions, and canonical audit records are retained by Files 00 and 09 and must be handled through their privacy procedures. File 03 has removed its public projection snapshot.';}
		if(SPD_Membership_Adapter::is_founder($user->ID)){$retained=true;$messages[]='Official Founder institutional content is retained until an authorized governance decision removes it.';}
		return array('items_removed'=>$removed,'items_retained'=>$retained,'messages'=>array_values(array_unique($messages)),'done'=>true);
	}
}
