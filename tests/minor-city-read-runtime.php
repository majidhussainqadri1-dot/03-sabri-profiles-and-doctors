<?php
/** V12.3 P0 fail-first: age-downgrade must revoke a stored public city field. */
require __DIR__ . '/test-bootstrap.php';
require dirname(__DIR__) . '/includes/class-spd-helpers.php';
require dirname(__DIR__) . '/includes/class-spd-membership-adapter.php';
require dirname(__DIR__) . '/includes/class-spd-authorization.php';
require dirname(__DIR__) . '/includes/trait-spd-profile-public-dto.php';
function user_trailingslashit($path){ return trim((string)$path,'/') . '/'; }
class SPD_Verification_Adapter { public static function projection($id){ return array(); } }
class SPD_Profile_Repository {
    use SPD_Profile_Public_DTO;
    public static $row;
    public function find_by_user_id($user_id,$ensure=false){ return self::$row; }
    public function find_by_public_id($public_id){ return self::$row; }
}
$GLOBALS['wpdb']=(object)array('last_error'=>'');
$GLOBALS['users'][2]=array('display_name'=>'Member');
$GLOBALS['users'][3]=array('display_name'=>'Other Eligible Member');
$GLOBALS['smc_claims'][2]=array(
    'user_id'=>2,'contract_version'=>'1.2.3','status'=>'approved','approved'=>true,'eligible'=>true,
    'membership_type'=>'member','approved_membership_types'=>array('member'),
    'guardian_verified'=>true,'public_profile_allowed'=>true,
    'age_context'=>array('known'=>true,'minor'=>true,'guardian_required'=>true,'age_band'=>'minor')
);
$GLOBALS['smc_claims'][3]=array(
    'user_id'=>3,'contract_version'=>'1.2.3','status'=>'approved','approved'=>true,'eligible'=>true,
    'membership_type'=>'member','approved_membership_types'=>array('member'),
    'guardian_verified'=>true,'public_profile_allowed'=>true,
    'age_context'=>array('known'=>true,'minor'=>false,'guardian_required'=>false,'age_band'=>'adult')
);
SPD_Profile_Repository::$row=array(
    'id'=>5,'user_id'=>2,'public_id'=>'123e4567-e89b-42d3-a456-426614174000',
    'profile_type'=>'member','state'=>'active','version'=>4,'profile_visibility'=>'members','locale'=>'ur_PK',
    'fields'=>array(
        'bio'=>array('audience'=>'members'),
        'country'=>array('audience'=>'public'),
        'city'=>array('audience'=>'public'),
        'languages'=>array('audience'=>'public'),
        'studied_books'=>array('audience'=>'private'),
        'phone'=>array('audience'=>'private'),
        'email'=>array('audience'=>'private'),
        'whatsapp'=>array('audience'=>'private'),
        'internal_message'=>array('audience'=>'private','field_value'=>'0'),
    ),
    'bio'=>'Ordinary public member biography',
    'country'=>'Pakistan','city'=>'Synthetic Test City',
    'languages'=>'Urdu','studied_books'=>''
);
$repo=new SPD_Profile_Repository();
$minor_view=$repo->public_dto(2,3);
test_assert(!is_wp_error($minor_view),'Fixture: an eligible member may view members-only profile while owner is minor.');
test_assert(!array_key_exists('city',(array)($minor_view['fields']??array())),
    'P0: cached public city remains disclosed to other members after a File00 age downgrade to minor.');
test_assert(($minor_view['fields']['country']??'')==='Pakistan','Non-sensitive country remains visible.');
$own_view=$repo->public_dto(2,2);
test_assert(!is_wp_error($own_view) && ($own_view['fields']['city']??'')==='Synthetic Test City',
    'The profile owner may see their own city in private editor/preview.');
$GLOBALS['smc_claims'][2]['age_context']=array('known'=>true,'minor'=>false,'guardian_required'=>false,'age_band'=>'adult');
$adult_view=$repo->public_dto(2,3);
test_assert(!is_wp_error($adult_view) && ($adult_view['fields']['city']??'')==='Synthetic Test City',
    'Current verified adult profile may disclose previously consented city to permitted member.');
echo "Current minor age/privacy DTO regression passed.\n";
