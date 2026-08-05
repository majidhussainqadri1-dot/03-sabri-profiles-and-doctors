<?php
require __DIR__.'/test-bootstrap.php';
require dirname(__DIR__).'/includes/class-spd-membership-adapter.php';
require dirname(__DIR__).'/includes/class-spd-verification-adapter.php';
$GLOBALS['users'][2]=array('display_name'=>'Doctor');
$GLOBALS['smc_claims'][2]=array('user_id'=>2,'contract_version'=>'1.2.0','status'=>'approved','approved'=>true,'eligible'=>true,'session_two_factor'=>true,'membership_type'=>'doctor','approved_membership_types'=>array('doctor'),'professional_verified'=>true,'email_verified'=>true,'phone_verified'=>true,'public_profile_allowed'=>true,'guardian_verified'=>true);
test_assert(!SPD_Verification_Adapter::is_verified(2),'Missing File 09 provider must hide verified badge.');
function gdo_validate_public_projection($data,$uid,$contract){return is_array($data)&&absint($data['user_id']??0)===$uid;}
add_filter('sabri_doctor_verification_public_projection_v1',function($v,$uid){return array('user_id'=>$uid,'status'=>'verified','approved_fields'=>array('specialty'=>'Classical Homeopathy'),'reviewer_id'=>9,'reviewed_at'=>gmdate('c',time()-3600),'generated_at'=>gmdate('c'),'valid_until'=>gmdate('c',time()+3600),'claim_version'=>'7','contract_version'=>'1.0.0','issuer'=>'file09');},10,2);
test_assert(SPD_Verification_Adapter::is_verified(2),'Current validated File 09 projection should verify doctor.');
test_reset_filters();
add_filter('sabri_doctor_verification_public_projection_v1',function($v,$uid){return array('user_id'=>$uid,'status'=>'verified','reviewer_id'=>9,'reviewed_at'=>gmdate('c',time()-3600),'generated_at'=>gmdate('c',time()-7200),'valid_until'=>gmdate('c',time()+3600),'claim_version'=>'7','contract_version'=>'1.0.0');},10,2);
test_assert(!SPD_Verification_Adapter::is_verified(2),'Stale File 09 projection must fail closed.');
echo "Verification runtime checks passed.\n";
