<?php
/** D10 fail-first: an unverified public profile must not market regulated
 * doctor clinical services via owner-entered "Professional Services" field.
 * Executes File03's real central personal-site source with bounded synthetic
 * File00/File09 state; not actual Staging, provider or Live evidence.
 */
require __DIR__.'/test-bootstrap.php';
require dirname(__DIR__).'/includes/class-spd-helpers.php';
require dirname(__DIR__).'/includes/class-spd-central-profile.php';
function user_trailingslashit($p){return trim((string)$p,'/').'/';}
function wp_salt($scheme='auth'){return 'synthetic-D10-testing-only';}
class SPD_Authorization { public static function audience_allows($a,$owner,$viewer){return $viewer===$owner || $a==='public';} }
class SPD_Profile_Repository {
 public static array $dto=[];
 public static array $profile=[];
 public static function instance(){return new self();}
 public function public_dto($id,$viewer=0){return self::$dto;}
 public function find_by_public_id_strict($id){return self::$profile;}
}
SPD_Profile_Repository::$profile=[
 'id'=>10,'user_id'=>2,'public_id'=>'e9999999-9999-4999-8999-999999999999',
 'profile_type'=>'doctor','fields'=>[
  'services'=>['audience'=>'public','field_value'=>'Clinical diagnosis, treatment and teleconsultations'],
  'interests'=>['audience'=>'public','field_value'=>'History of Classical Homeopathy'],
  'share_epoch'=>['audience'=>'private','field_value'=>'1'],
 ],
];
SPD_Profile_Repository::$dto=[
 'public_id'=>SPD_Profile_Repository::$profile['public_id'],
 'canonical_url'=>'https://example.test/profile/e9999999-9999-4999-8999-999999999999/',
 'profile_type'=>'doctor','badge'=>['key'=>'member','verified'=>false],
 'professional'=>[],'clinic'=>[],'fields'=>[],'display_name'=>'Applicant',
 'version'=>2,'media'=>[],
];
$guest=SPD_Central_Profile::personal_site_dto(SPD_Profile_Repository::$profile['public_id'],0);
test_assert(!is_wp_error($guest),'Applicant public profile fixture must render safely.');
test_assert(empty($guest['extended']['services']),'D10: unverified doctor applicant falsely markets clinical professional services to public visitors.');
test_assert(($guest['extended']['interests']??'')==='History of Classical Homeopathy','Public educational interests remain permitted for nonprofessional profiles.');
$owner=SPD_Central_Profile::personal_site_dto(SPD_Profile_Repository::$profile['public_id'],2);
test_assert(($owner['extended']['services']??'')==='Clinical diagnosis, treatment and teleconsultations','Owner preview preserves the saved private draft for corrections.');
SPD_Profile_Repository::$dto['badge']=['key'=>'verified_doctor','verified'=>true];
$approved=SPD_Central_Profile::personal_site_dto(SPD_Profile_Repository::$profile['public_id'],0);
test_assert(($approved['extended']['services']??'')==='Clinical diagnosis, treatment and teleconsultations','Currently verified doctor may display previously approved public professional services.');
SPD_Profile_Repository::$dto['profile_type']='member';
SPD_Profile_Repository::$dto['badge']=['key'=>'member','verified'=>false];
$member=SPD_Central_Profile::personal_site_dto(SPD_Profile_Repository::$profile['public_id'],0);
test_assert(empty($member['extended']['services']),'Ordinary member cannot inherit clinic marketing from professional field.');
echo "PASS D10 professional service field is gated to current verified File09 doctor identity\n";
