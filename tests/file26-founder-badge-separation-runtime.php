<?php
/**
 * F03-FR-011/013: official-founder and verified-doctor badges have different
 * owners and semantics. File26 indexes a dedicated verified_doctor boolean;
 * an official Founder badge alone must never set it.
 * Independent File 03 projection mock; no production/real provider claim.
 */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
class WP_Error {}
function is_wp_error($x) { return $x instanceof WP_Error; }
function sanitize_key($x) { return preg_replace('/[^a-z0-9_-]/','',strtolower((string)$x)); }
function sanitize_text_field($x) { return trim(strip_tags((string)$x)); }
function absint($x) { return abs((int)$x); }
function esc_url_raw($x) { return (string)$x; }
class SPD_Helpers {
    public static function normalize_locale($x) { return (string)$x; }
    public static function sanitize_multiline($x,$max=1000) { return substr((string)$x,0,$max); }
}
const ID='f5555555-5555-4555-8555-555555555555';
$GLOBALS['f03_search_current']=[
    'canonical_id'=>ID,'canonical_url'=>'https://example.test/profile/'.ID.'/',
    'profile_type'=>'founder','version'=>8,'locale'=>'en-US',
    'display_name'=>'Founder profile','badge'=>['key'=>'official_founder','verified'=>true],
    'fields'=>['bio'=>'Founder public biography'], 'professional'=>[],
];
function spd_get_search_projection($id) {
    return $id===ID ? $GLOBALS['f03_search_current'] : new WP_Error();
}
require_once __DIR__.'/../includes/class-spd-contracts.php';
function check(bool $ok,string $name): void {if(!$ok){fwrite(STDERR,"FAIL: {$name}\n");exit(1);}}
$founder=SPD_Contracts::file26_fetch_object(ID);
check(is_array($founder) && ($founder['entity_type']??'')==='founder','Founder document not produced');
check(($founder['payload']['verified_doctor']??null)===false,'Founder badge conflated with verified doctor in File26 search');
$legacy=$founder;
$legacy['payload']['verified_doctor']=true;
check(SPD_Contracts::file26_can_view($legacy) === false,'previously indexed Founder false-doctor badge remained visible');
check(SPD_Contracts::file26_can_view($founder) === true,'corrected Founder index was unexpectedly hidden');
// File26 renders the persisted entity_type into doctor tier/facets *after* this
// callback, so a historical mismatched type must not survive click-time ACL.
$false_doctor=$founder;
$false_doctor['entity_type']='doctor';
$false_doctor['payload']['verified_doctor']=false;
check(SPD_Contracts::file26_can_view($false_doctor) === false,'historical Founder result displayed as a doctor despite current canonical Founder identity');
$unknown_type=$founder;
$unknown_type['entity_type']='ineligible';
check(SPD_Contracts::file26_can_view($unknown_type) === false,'unknown entity type accepted as current canonical profile');

$GLOBALS['f03_search_current']['profile_type']='doctor';
$GLOBALS['f03_search_current']['badge']=['key'=>'verified_doctor','verified'=>true];
$doctor=SPD_Contracts::file26_fetch_object(ID);
check(($doctor['payload']['verified_doctor']??null)===true,'current File09 verified doctor badge omitted');
$GLOBALS['f03_search_current']['badge']=['key'=>'member','verified'=>false];
$unverified=SPD_Contracts::file26_fetch_object(ID);
check(($unverified['payload']['verified_doctor']??null)===false,'unverified doctor incorrectly promoted');
echo "PASS Founder badge distinct from File09 doctor verification in File26 projection\n";
