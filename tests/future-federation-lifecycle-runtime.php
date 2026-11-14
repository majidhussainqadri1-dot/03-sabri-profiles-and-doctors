<?php
/**
 * V12.3 D16: federation transport must be disabled on retired/legacy/unknown
 * professional lifecycle even if prior owner opt-in and provider claim persist.
 * Actual SPD_Future_Profile::federation_projection with synthetic providers.
 */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
define('SPD_CONTRACT_VERSION', '1.4.0');
class WP_Error {
    public function __construct(private string $code) {}
    public function get_error_code() { return $this->code; }
}
function is_wp_error($v) { return $v instanceof WP_Error; }
function absint($v) { return abs((int)$v); }
function sanitize_key($v) { return strtolower((string)$v); }
function esc_url_raw($v) { return $v; }
function __($v, $domain = '') { return $v; }
function spd_read_future_profile_state($id) {
    if ($GLOBALS['fixture_state'] === 'database_unavailable') return new WP_Error('spd_db_unavailable');
    return ['federation_opt_in'=>$GLOBALS['fixture_optin'],
        'professional_lifecycle'=>$GLOBALS['fixture_state']];
}
function apply_filters_ref_array($filter, $args) {
    $GLOBALS['provider_calls']++;
    return ['contract_version'=>'1.4.0','user_id'=>12,'active'=>true,
        'inbox'=>'https://example.test/actor/inbox',
        'outbox'=>'https://example.test/actor/outbox'];
}
class SPD_Helpers {
    public static function current_contract_claim($claim, $version, $max_age) { return is_array($claim); }
    public static function same_origin_url($url) { return str_starts_with($url, 'https://example.test/'); }
    public static function now() { return '2026-11-14 12:00:00'; }
}
require __DIR__.'/../includes/class-spd-future-profile.php';
$profile=['id'=>4,'user_id'=>12,'public_id'=>'some-public-id'];
$dto=['canonical_url'=>'https://example.test/profile/some-public-id/',
    'display_name'=>'A verified professional'];
function expect_inactive($state, $optin = 1) {
    global $profile, $dto;
    $GLOBALS['fixture_state']=$state; $GLOBALS['fixture_optin']=$optin;
    $GLOBALS['provider_calls']=0;
    $result=SPD_Future_Profile::federation_projection($profile,$dto);
    if (!is_array($result) || !empty($result['transport_active']) ||
        isset($result['inbox']) || isset($result['outbox']) ||
        $GLOBALS['provider_calls']!==0) {
        fwrite(STDERR,"FAIL D16 $state: inactive/denied/unknown professional exposed active federation transport\n");
        exit(1);
    }
}
expect_inactive('retired');
expect_inactive('legacy');
expect_inactive('unknown');
expect_inactive('database_unavailable');
expect_inactive('active',0);

$GLOBALS['fixture_state']='active'; $GLOBALS['fixture_optin']=1; $GLOBALS['provider_calls']=0;
$valid=SPD_Future_Profile::federation_projection($profile,$dto);
if (empty($valid['transport_active']) || empty($valid['inbox']) ||
    empty($valid['outbox']) || $GLOBALS['provider_calls']!==1) {
    fwrite(STDERR,"FAIL D16 current active opt-in lost legitimate federation transport\n");exit(1);
}
echo "PASS D16 lifecycle-aware federation transport and current active opt-in\n";
