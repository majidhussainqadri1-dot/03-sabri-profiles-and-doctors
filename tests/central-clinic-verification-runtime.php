<?php
/** D09 fail-first: File08 current clinic must not reappear after File09 verification revocation.
 * Bounded native File03 personal_site_dto() execution with synthetic owner responses.
 * Not proof of actual File09/File08 provider state, Staging or Hostinger Live.
 */
require __DIR__.'/test-bootstrap.php';
require dirname(__DIR__).'/includes/class-spd-helpers.php';
require dirname(__DIR__).'/includes/class-spd-central-profile.php';
function user_trailingslashit($p){return trim((string)$p,'/').'/';}
function wp_salt($scheme='auth'){return 'disposable-test-hmac-key';}
class SPD_Authorization {
 public static function audience_allows($audience,$owner,$viewer){return false;}
}
class SPD_Profile_Repository {
 public static array $dto=[];
 public static array $profile=[];
 public static function instance(){return new self();}
 public function public_dto($identity,$viewer_id=0){return self::$dto;}
 public function find_by_public_id_strict($id){return self::$profile;}
}
$now=time();
add_filter('sabri_file08_public_clinic_projection_v1',static function($unused,$uid,$viewer,$requested)use($now){
 return ['user_id'=>$uid,'doctor_user_id'=>$uid,'contract_version'=>'1.0.0',
  'generated_at'=>gmdate('c',$now),'valid_until'=>gmdate('c',$now+250),
  'owner_version'=>'8','status'=>'active','visibility'=>'public',
  'name'=>'Synthetic Clinic','city'=>'Synthetic City',
  'appointment_url'=>'https://example.test/appointments/test/'];
},10,4);
SPD_Profile_Repository::$profile=[
 'id'=>4,'user_id'=>2,'public_id'=>'a8888888-8888-4888-8888-888888888888',
 'profile_type'=>'doctor','fields'=>['share_epoch'=>['field_value'=>'1']],
];
SPD_Profile_Repository::$dto=[
 'public_id'=>SPD_Profile_Repository::$profile['public_id'],
 'canonical_url'=>'https://example.test/profile/'.SPD_Profile_Repository::$profile['public_id'].'/',
 'profile_type'=>'doctor','badge'=>['key'=>'member','verified'=>false],
 'professional'=>[],'clinic'=>[],'fields'=>[],'display_name'=>'Test Member',
 'version'=>4,'media'=>[],
];
$revoked=SPD_Central_Profile::personal_site_dto(SPD_Profile_Repository::$profile['public_id'],0);
test_assert(is_array($revoked),'Fixture unverified doctor profile is renderable without a verified badge.');
test_assert(empty($revoked['clinic']),'D09 P0: revoked/unverified File09 doctor retained File08 clinic or appointment CTA on central personal site.');
test_assert(empty($revoked['reviews']),'D09: unverified doctor must not display current service-review block as a verified medical profile.');
SPD_Profile_Repository::$dto['badge']=['key'=>'verified_doctor','verified'=>true];
$verified=SPD_Central_Profile::personal_site_dto(SPD_Profile_Repository::$profile['public_id'],0);
test_assert(($verified['clinic']['name']??'')==='Synthetic Clinic','Verified doctor can display current File08 owner clinic.');
test_assert(!empty($verified['clinic']['appointment_url']),'Verified doctor can display current File08 appointment CTA.');
SPD_Profile_Repository::$dto['profile_type']='member';
SPD_Profile_Repository::$dto['badge']=['key'=>'member','verified'=>false];
$member=SPD_Central_Profile::personal_site_dto(SPD_Profile_Repository::$profile['public_id'],0);
test_assert(empty($member['clinic']),'Member-only presentation cannot bypass File09 doctor verification using File08 clinic.');
echo "PASS D09 File03 public personal site hides clinic until current verified doctor badge\n";
