<?php
defined( 'ABSPATH' ) || exit;

final class SPD_Helpers {
	public static function fields() {
		return array(
			'account_type'=>'Account type','country'=>'Country','city'=>'City','clinic'=>'Clinic','qualification'=>'Qualification',
			'licence_number'=>'Licence / registration number','licensing_authority'=>'Licensing authority','experience_years'=>'Years of experience',
			'specialty'=>'Specialty','languages'=>'Languages','studied_books'=>'Classical books studied','consultation_modes'=>'Consultation modes',
			'phone'=>'Phone number','whatsapp'=>'WhatsApp number','bio'=>'Professional introduction',
		);
	}

	public static function get( $user_id, $key, $default = '' ) { return SPD_Membership_Adapter::field( $user_id, $key, $default ); }
	public static function clean_phone( $value ) { $value=preg_replace('/[^0-9+]/','',(string)$value); $value=preg_replace('/(?!^)\+/','',$value); return substr($value,0,18); }
	public static function whatsapp_url( $number ) { $digits=preg_replace('/\D+/','',(string)$number); return $digits ? 'https://wa.me/'.$digits : ''; }
	public static function verification_status( $user_id ) { return SPD_Verification_Adapter::status( $user_id ); }
	public static function status_label( $status ) { return SPD_Verification_Adapter::status_label( $status ); }
	public static function is_doctor( $user_id ) { return SPD_Membership_Adapter::is_doctor( $user_id ); }

	public static function can_show_contact( $user_id, $founder = false, $viewer_id = 0 ) {
		$viewer_id = $viewer_id ? absint( $viewer_id ) : get_current_user_id();
		if ( SPD_Profile_Policy::is_minor( $user_id ) ) { return false; }
		if ( $founder && SPD_Membership_Adapter::is_founder( $user_id ) ) { return '1' === (string) get_user_meta( absint($user_id), '_spd_public_contact', true ); }
		return '1' === (string) get_user_meta( absint($user_id), '_spd_public_contact', true )
			&& ( SPD_Profile_Policy::can_view_field( $viewer_id, $user_id, 'phone' ) || SPD_Profile_Policy::can_view_field( $viewer_id, $user_id, 'whatsapp' ) );
	}

	public static function profile_url( $user_id ) { return SPD_Public_Identity::profile_url( absint( $user_id ) ); }
	public static function timeline_url( $user_id ) { return SPD_Public_Identity::profile_url( absint( $user_id ), true ); }

	public static function founder() {
		$founder_id=SPD_Membership_Adapter::founder_id(); $user=$founder_id?get_userdata($founder_id):false; $profile=(array)get_option('spd_founder_profile',array());
		$defaults=array(
			'name'=>$user?$user->display_name:'','title'=>'Founder — Sabri Social Homeopathy Platform',
			'location'=>$founder_id?trim(self::get($founder_id,'city').', '.self::get($founder_id,'country'),', '):'',
			'phone'=>$founder_id?self::get($founder_id,'phone'):'','whatsapp'=>$founder_id?self::get($founder_id,'whatsapp'):'',
			'introduction'=>'','mission'=>'','vision'=>'','objectives'=>'','methodology'=>'','experience'=>'','research'=>'','publications'=>'',
			'photo_id'=>$founder_id?absint(get_user_meta($founder_id,'_spd_profile_photo_id',true)):0,
			'cover_id'=>$founder_id?absint(get_user_meta($founder_id,'_spd_cover_photo_id',true)):0,
		);
		$merged=wp_parse_args($profile,$defaults);
		foreach(array('name','location','phone','whatsapp','photo_id','cover_id') as $key){$merged[$key]=$defaults[$key];}
		return $merged;
	}

	public static function audit( $target, $old, $new, $reason = '', $context = array() ) {
		if ( class_exists('SMC_Security') && is_callable(array('SMC_Security','audit')) ) {
			SMC_Security::audit('spd_projection_event',absint($target),'profile_projection',0,array(
				'old'=>sanitize_key($old),'new'=>sanitize_key($new),'reason'=>sanitize_textarea_field($reason),
				'context'=>array_map('sanitize_text_field',(array)$context),'profile_version'=>SPD_Profile_Policy::version($target),
			));
		}
	}
}
