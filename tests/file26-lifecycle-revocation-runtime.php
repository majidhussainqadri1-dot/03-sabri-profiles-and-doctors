<?php
/**
 * V12.3 D20: current professional lifecycle changes do not increment native
 * profile version; File26 holds a persisted badge/ranking payload.
 * Retired/legacy profiles must invalidate stale "active verified doctor"
 * search documents and allow only accurate refreshed retired/legacy state.
 */
declare(strict_types=1);
namespace Sabri\File26 {
    final class Normalizer { public function normalize($v) {
        return preg_replace('/\s+/', ' ', strtolower(trim((string)$v)));
    } }
}
namespace {
define('ABSPATH',__DIR__.'/');
class WP_Error {}
function is_wp_error($v){return $v instanceof WP_Error;}
function sanitize_text_field($v){return trim(strip_tags((string)$v));}
function sanitize_key($v){return preg_replace('/[^a-z0-9_-]/','',strtolower((string)$v));}
function wp_strip_all_tags($v,$remove_breaks=false){return strip_tags((string)$v);}
const PROFILE_ID='b4444444-4444-4444-8444-444444444444';
$GLOBALS['d20_current']=[
    'canonical_id'=>PROFILE_ID,'version'=>12,'profile_type'=>'doctor',
    'professional_lifecycle'=>'active',
    'badge'=>['key'=>'verified_doctor','verified'=>true],
    'display_name'=>'Doctor A','fields'=>[],'professional'=>[]
];
function spd_get_search_projection($identity){return $GLOBALS['d20_current'];}
require __DIR__.'/../includes/class-spd-contracts.php';
function assert_case($check,$name){
    if(!$check){fwrite(STDERR,"FAIL D20: ".$name."\n");exit(1);}
}
$doc=[
    'object_id'=>PROFILE_ID,'object_version'=>12,'entity_type'=>'doctor',
    'normalized_body'=>'doctor a','state'=>'active','visibility'=>'public',
    'payload'=>['verified_doctor'=>true,'professional_lifecycle'=>'active']
];
assert_case(\SPD_Contracts::file26_can_view($doc),'active current verified doctor should be visible');
$GLOBALS['d20_current']['professional_lifecycle']='retired';
assert_case(!\SPD_Contracts::file26_can_view($doc),'retired doctor reused stale active verified index');
$doc['payload']=['verified_doctor'=>false,'professional_lifecycle'=>'retired'];
assert_case(\SPD_Contracts::file26_can_view($doc),'current retired profile cannot be discovered without active badge');
$GLOBALS['d20_current']['professional_lifecycle']='legacy';
assert_case(!\SPD_Contracts::file26_can_view($doc),'legacy profile still matches retired index');
$doc['payload']=['verified_doctor'=>false,'professional_lifecycle'=>'legacy'];
assert_case(\SPD_Contracts::file26_can_view($doc),'current legacy profile rejected');
$GLOBALS['d20_current']['professional_lifecycle']='unknown';
assert_case(!\SPD_Contracts::file26_can_view($doc),'unknown profile lifecycle matched saved index');
echo "PASS D20 File26 doctor active/retired/legacy lifecycle saved-payload revalidation\n";
}
