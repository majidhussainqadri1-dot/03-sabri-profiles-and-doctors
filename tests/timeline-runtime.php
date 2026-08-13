<?php
require __DIR__.'/test-bootstrap.php';
if(!defined('MINUTE_IN_SECONDS'))define('MINUTE_IN_SECONDS',60);
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

// R14: provider health callbacks are third-party/cross-file code and must not
// escape the timeline boundary when they throw.
add_filter('sabri_file21_profile_timeline_provider_health_v1',function(){throw new RuntimeException('health provider failed');},20,1);
$health_failure=SPD_Timeline::query(2,array(),0);
test_assert(!is_wp_error($health_failure),'Timeline health-provider exception escaped the boundary.');
test_assert(($health_failure['provider_health']['file21']??'')==='degraded','Throwing provider health must be marked degraded.');
test_assert(!empty($health_failure['partial']),'Throwing provider health must produce a partial/degraded timeline.');

// R14: the optional provider-registry extension point itself is also untrusted.
// A throwing registry extension must fall back to the canonical built-in registry.
add_filter('spd_profile_timeline_providers_v1',function(){throw new RuntimeException('registry failed');},20,1);
$registry_failure=SPD_Timeline::query(2,array(),0);
test_assert(!is_wp_error($registry_failure),'Timeline provider-registry exception escaped the boundary.');
test_assert(array_key_exists('file21',$registry_failure['provider_health']),'Canonical providers were lost after registry exception.');

echo "Timeline runtime checks passed.\n";
