<?php
require __DIR__.'/test-bootstrap.php';
require dirname(__DIR__).'/includes/class-spd-helpers.php';
require dirname(__DIR__).'/includes/class-spd-membership-adapter.php';
require dirname(__DIR__).'/includes/class-spd-authorization.php';
class SPD_Profile_Repository {public static $profile;public static function instance(){return new self();}public function find_by_user_id($id,$ensure=false){return self::$profile;}public function find_by_public_id($id){return self::$profile;}}
class SPD_Observability{public static function safe_mode(){return false;}}
require dirname(__DIR__).'/includes/class-spd-timeline.php';
$GLOBALS['users'][2]=array('display_name'=>'Adult');
$GLOBALS['smc_claims'][2]=array('user_id'=>2,'contract_version'=>'1.2.0','status'=>'approved','approved'=>true,'eligible'=>true,'session_two_factor'=>true,'membership_type'=>'member','approved_membership_types'=>array('member'),'professional_verified'=>false,'email_verified'=>true,'phone_verified'=>true,'public_profile_allowed'=>true,'guardian_verified'=>true);
add_filter('smc_profile_age_guardian_claim_v1',fn($v,$id)=>array('user_id'=>$id,'age'=>35,'is_minor'=>false,'guardian_verified'=>true,'contract_version'=>'1.2.0','generated_at'=>gmdate('c'),'valid_until'=>gmdate('c',time()+600)),10,2);
SPD_Profile_Repository::$profile=array('id'=>5,'user_id'=>2,'public_id'=>'123e4567-e89b-42d3-a456-426614174000','profile_type'=>'member','state'=>'active','profile_visibility'=>'public');
add_filter('sabri_file21_profile_timeline_provider_health_v1',fn()=>array('status'=>'available','contract_version'=>'1.0.0','generated_at'=>gmdate('c'),'valid_until'=>gmdate('c',time()+300)));
add_filter('sabri_file21_profile_timeline_items_v1',function(){return array(
 array('author_user_id'=>2,'contract_version'=>'1.0.0','canonical_id'=>'good','owner_version'=>'4','url'=>'https://example.test/post/good','published_at'=>gmdate('c'),'visibility'=>'public','status'=>'published','title'=>'Good'),
 array('author_user_id'=>99,'contract_version'=>'1.0.0','canonical_id'=>'wrong-author','owner_version'=>'1','url'=>'https://example.test/post/bad','published_at'=>gmdate('c'),'visibility'=>'public','status'=>'published'),
 array('author_user_id'=>2,'contract_version'=>'1.0.0','canonical_id'=>'external','owner_version'=>'1','url'=>'https://evil.test/post','published_at'=>gmdate('c'),'visibility'=>'public','status'=>'published')
);});
$result=SPD_Timeline::query(2,array(),0);
test_assert(!is_wp_error($result),'Timeline query failed.');
test_assert(count($result['items'])===1 && $result['items'][0]['canonical_id']==='good','Timeline accepted untrusted author or URL.');
test_assert(($result['provider_health']['file10']??'')==='unavailable','Missing provider must be unavailable, not empty.');
echo "Timeline runtime checks passed.\n";
