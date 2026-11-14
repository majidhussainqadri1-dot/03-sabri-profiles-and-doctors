<?php
require __DIR__.'/test-bootstrap.php';
require dirname(__DIR__).'/includes/class-spd-helpers.php';
require dirname(__DIR__).'/includes/class-spd-membership-adapter.php';
require dirname(__DIR__).'/includes/class-spd-verification-adapter.php';
$GLOBALS['users'][2]=array('display_name'=>'Doctor');
$GLOBALS['smc_claims'][2]=array('user_id'=>2,'contract_version'=>'1.2.3','status'=>'approved','approved'=>true,'eligible'=>true,'session_two_factor'=>true,'membership_type'=>'doctor','approved_membership_types'=>array('doctor'),'professional_verified'=>true,'email_verified'=>true,'phone_verified'=>true,'public_profile_allowed'=>true,'guardian_verified'=>true,'age_context'=>array('known'=>true,'minor'=>false,'age_band'=>'adult','guardian_required'=>false));
test_assert(!SPD_Verification_Adapter::is_verified(2),'Missing File 09 provider must hide verified badge.');
function gdo_validate_public_projection($data,$uid,$contract){return is_array($data)&&absint($data['user_id']??0)===$uid;}
add_filter('sabri_doctor_verification_public_projection_v1',function($v,$uid){return array('user_id'=>$uid,'status'=>'verified','approved_fields'=>array('specialty'=>'Classical Homeopathy'),'reviewer_id'=>9,'reviewed_at'=>gmdate('c',time()-3600),'generated_at'=>gmdate('c'),'valid_until'=>gmdate('c',time()+3600),'claim_version'=>'7','contract_version'=>'1.0.0','issuer'=>'file09');},10,2);
test_assert(SPD_Verification_Adapter::is_verified(2),'Current validated File 09 projection should verify doctor.');
test_assert(!SPD_Membership_Adapter::is_minor(2),'Current verified adult File00 age_context must not be treated as an unknown/minor account.');
test_assert(SPD_Verification_Adapter::directory_eligible(2),'Current File00 adult and current File09 verified doctor must be directory eligible.');
test_assert(!SPD_Membership_Adapter::public_profile_age_eligible(2),'File00 public-profile opt-in must fail closed without an independent age claim; inline membership assertions may recurse here.');

$age_claim_factory = static function($value,$uid){
    $now=time();
    $claim=array('owner'=>'file00','purpose'=>'public_profile_age_assurance','contract_version'=>'1.2.3','user_id'=>$uid,
        'generated_at'=>gmdate('c',$now),'valid_until'=>gmdate('c',$now+120),
        'known'=>true,'age_band'=>'adult','is_minor'=>false,'guardian_required'=>false,'guardian_verified'=>true);
    if(3===$uid){$claim['known']=false;$claim['age_band']='unknown';}
    if(4===$uid){$claim['age_band']='minor';$claim['is_minor']=true;$claim['guardian_required']=true;}
    if(5===$uid){$claim['age_band']='minor';}
    if(6===$uid){$claim['contract_version']='1.2.0';}
    if(7===$uid){$claim['valid_until']=gmdate('c',$now-60);}
    if(8===$uid){$claim['purpose']='incorrect_scope';}
    return $claim;
};
add_filter('smc_profile_age_guardian_claim_v1',$age_claim_factory,10,2);
test_assert(SPD_Membership_Adapter::public_profile_age_eligible(2),'Current owner claim must allow a verified known adult without recursively calling File00 assertions.');
foreach(array(3,4,5,6,7,8) as $uid){
    test_assert(!SPD_Membership_Adapter::public_profile_age_eligible($uid),'Unknown, minor, inconsistent, old, expired and wrong-purpose age claims must fail closed: '.$uid);
}

$GLOBALS['smc_claims'][3] = array('user_id'=>3,'contract_version'=>'1.2.3','status'=>'approved','approved'=>true,'eligible'=>true,'membership_type'=>'member','guardian_verified'=>false,'age_context'=>array('known'=>false,'minor'=>false,'age_band'=>'unknown','guardian_required'=>false));
test_assert(SPD_Membership_Adapter::is_minor(3),'Unknown File00 age must remain minor-safe.');
test_assert(!SPD_Membership_Adapter::public_profile_age_eligible(3),'Unknown File00 age must never permit public adult-only profile.');
$GLOBALS['smc_claims'][4] = array('user_id'=>4,'contract_version'=>'1.2.3','status'=>'approved','approved'=>true,'eligible'=>true,'membership_type'=>'member','guardian_verified'=>true,'age_context'=>array('known'=>true,'minor'=>true,'age_band'=>'minor','guardian_required'=>true));
test_assert(SPD_Membership_Adapter::is_minor(4),'Current File00 confirmed minor must remain minor.');
test_assert(!SPD_Membership_Adapter::public_profile_age_eligible(4),'Confirmed minor cannot obtain adult public-profile eligibility.');
$GLOBALS['smc_claims'][5] = array('user_id'=>5,'contract_version'=>'1.2.3','status'=>'approved','approved'=>true,'eligible'=>true,'membership_type'=>'member','guardian_verified'=>true,'age_context'=>array('known'=>true,'minor'=>false,'age_band'=>'minor','guardian_required'=>false));
test_assert(SPD_Membership_Adapter::is_minor(5),'Contradictory File00 age flags must fail closed rather than grant adult status.');
$GLOBALS['smc_claims'][6] = array('user_id'=>6,'contract_version'=>'1.2.0','status'=>'approved','approved'=>true,'eligible'=>true,'membership_type'=>'member','guardian_verified'=>true,'age_context'=>array('known'=>true,'minor'=>false,'age_band'=>'adult','guardian_required'=>false));
test_assert(SPD_Membership_Adapter::is_minor(6),'Older File00 contract cannot silently make new inline adult age_context authoritative.');
$GLOBALS['smc_claims'][2]['suspended'] = true;
test_assert(!SPD_Verification_Adapter::directory_eligible(2),'Suspended File00 doctor must lose directory eligibility at read time.');
unset($GLOBALS['smc_claims'][2]['suspended']);
test_reset_filters();
add_filter('sabri_doctor_verification_public_projection_v1',function($v,$uid){return array('user_id'=>$uid,'status'=>'verified','reviewer_id'=>9,'reviewed_at'=>gmdate('c',time()-3600),'generated_at'=>gmdate('c',time()-7200),'valid_until'=>gmdate('c',time()+3600),'claim_version'=>'7','contract_version'=>'1.0.0');},10,2);
test_assert(!SPD_Verification_Adapter::is_verified(2),'Stale File 09 projection must fail closed.');

test_reset_filters();
add_filter('sabri_doctor_verification_public_projection_v1',function($v,$uid){
    return array('user_id'=>$uid,'status'=>'under_review','approved_fields'=>array(),
        'reviewer_id'=>0,'reviewed_at'=>'','generated_at'=>gmdate('c'),
        'valid_until'=>gmdate('c',time()+300),'claim_version'=>'1.0.1',
        'contract_version'=>'1.0.0','issuer'=>'file09');
},10,2);
test_assert(SPD_Verification_Adapter::status(2)==='under_review',
    'Current File09 under-review state must not be mislabeled as provider unavailable when no reviewer decision yet exists.');
test_assert(!SPD_Verification_Adapter::is_verified(2),
    'An under-review doctor must not obtain a verified public badge.');

test_reset_filters();
add_filter('sabri_doctor_verification_public_projection_v1',function($v,$uid){
    return array('user_id'=>$uid,'status'=>'under_review','approved_fields'=>array('qualification'=>'PRIVATE PENDING CLAIM'),
        'reviewer_id'=>0,'reviewed_at'=>'','generated_at'=>gmdate('c'),
        'valid_until'=>gmdate('c',time()+300),'claim_version'=>'1.0.1',
        'contract_version'=>'1.0.0','issuer'=>'file09');
},10,2);
test_assert(SPD_Verification_Adapter::status(2)==='verification_unavailable',
    'A pending doctor projection containing unapproved credentials must fail closed.');
test_assert(!SPD_Verification_Adapter::is_verified(2),
    'Pending professional data must never be used as a verified badge.');

// P0 fail-first: a legacy unscoped age response must never overrule
// a minor age or convert a negative age to asserted adulthood.
test_reset_filters();
add_filter('smc_profile_age_guardian_claim_v1',static function($value,$uid){
    return array('user_id'=>$uid,'contract_version'=>'1.2.3',
        'generated_at'=>gmdate('c'),'valid_until'=>gmdate('c',time()+120),
        'age'=>14,'is_minor'=>false,'guardian_verified'=>true);
},10,2);
test_assert(!SPD_Membership_Adapter::public_profile_age_eligible(90),
    'An unscoped legacy age=14/is_minor=false contradiction must never authorize an adult public profile.');

echo "Verification runtime checks passed.\n";
