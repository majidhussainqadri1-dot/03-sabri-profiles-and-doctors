<?php
require __DIR__.'/test-bootstrap.php';
require dirname(__DIR__).'/includes/class-spd-helpers.php';
require dirname(__DIR__).'/includes/class-spd-membership-adapter.php';
class SPD_Observability{public static function safe_mode(){return false;}}
require dirname(__DIR__).'/includes/class-spd-authorization.php';
$GLOBALS['users']=array(1=>array('display_name'=>'Founder'),2=>array('display_name'=>'Adult'),3=>array('display_name'=>'Unknown Age'),4=>array('display_name'=>'Moderator'));
$base=function($id,$type='member'){return array('user_id'=>$id,'contract_version'=>'1.2.0','status'=>'approved','approved'=>true,'eligible'=>true,'session_two_factor'=>true,'membership_type'=>$type,'approved_membership_types'=>$type==='doctor'?array('doctor'):array($type),'professional_verified'=>$type==='doctor','email_verified'=>true,'phone_verified'=>true,'public_profile_allowed'=>true,'guardian_verified'=>true);};
$GLOBALS['smc_claims'][1]=$base(1);$GLOBALS['smc_claims'][1]['account_class']='founder';
$GLOBALS['smc_claims'][2]=$base(2);
$GLOBALS['smc_claims'][3]=$base(3);
$GLOBALS['smc_claims'][4]=$base(4);$GLOBALS['caps'][4]['smc_review_verification']=true;
add_filter('smc_profile_age_guardian_claim_v1',function($v,$id){if(2===$id)return array('user_id'=>2,'age'=>35,'is_minor'=>false,'guardian_verified'=>true,'contract_version'=>'1.2.0','generated_at'=>gmdate('c'),'valid_until'=>gmdate('c',time()+600));return null;},10,2);
test_assert(SPD_Membership_Adapter::public_profile_age_eligible(2),'Adult age contract should allow public eligibility.');
test_assert(SPD_Membership_Adapter::is_minor(3),'Unknown ordinary age must remain minor-safe.');
test_assert(!SPD_Authorization::audience_allows('private',2,4),'Moderator must not bypass private audience.');
test_assert(SPD_Authorization::audience_allows('members',2,4),'Eligible moderator is still an eligible member.');
$GLOBALS['smc_claims'][4]['eligible']=false;
test_assert(!SPD_Authorization::audience_allows('members',2,4),'Ineligible logged-in account must not satisfy members audience.');
$GLOBALS['smc_claims'][4]['eligible']=true;
$profile=array('user_id'=>2,'profile_type'=>'member','state'=>'active','profile_visibility'=>'public');
test_assert(SPD_Authorization::profile_visibility_allows($profile,0),'Adult eligible opted-in profile should be public.');
$GLOBALS['smc_claims'][2]['public_profile_allowed']=false;
test_assert(!SPD_Authorization::profile_visibility_allows($profile,0),'File 00 public-profile assertion must be enforced.');
$GLOBALS['smc_claims'][2]['public_profile_allowed']=true;
add_filter('spd_restrict_founder_management',fn($allowed,$id)=>false,10,2);
test_assert(!SPD_Membership_Adapter::can_manage_founder(1),'Founder restriction filter may narrow authority.');
echo "Authorization runtime checks passed.\n";
