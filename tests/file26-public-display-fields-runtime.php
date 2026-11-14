<?php
/**
 * V12.3 D21: File26 returns stored public-facing fields AFTER File03 owner
 * view-time authorization. An unchanged normalized search body and native
 * version do not authorize a stale biography excerpt, URL, country/location
 * or a tampered displayed title. Check the REAL File03 can_view method.
 *
 * Isolated source fixture. Real File26 renderer and Staging reindex still need
 * their own acceptance receipts.
 */
declare(strict_types=1);
namespace Sabri\File26 {
    final class Normalizer {
        public function normalize($value) {
            return preg_replace('/\s+/u', ' ', strtolower(trim((string)$value)));
        }
    }
}
namespace {
define('ABSPATH',__DIR__.'/');
class WP_Error {}
function is_wp_error($v) {return $v instanceof WP_Error;}
function sanitize_text_field($v){return trim(strip_tags((string)$v));}
function wp_strip_all_tags($v,$remove_breaks=false){$v=strip_tags((string)$v);return $remove_breaks?preg_replace('/[\r\n\t ]+/u',' ',$v):$v;}
function esc_url_raw($v){return trim((string)$v);}
class SPD_Helpers {
    public static function sanitize_multiline($v,$length=600) {return substr(trim((string)$v),0,$length);}
}
const ID='d1111111-1111-4111-8111-111111111111';
$GLOBALS['current_projection']=[
  'canonical_id'=>ID,'canonical_url'=>'https://example.test/profile/d1111111/',
  'display_name'=>'Doctor Alpha','profile_type'=>'doctor','version'=>18,
  'professional_lifecycle'=>'active',
  'badge'=>['key'=>'verified_doctor','verified'=>true],
  'fields'=>['bio'=>'Approved public biography','country'=>'Pakistan','city'=>'Lahore'],
  'professional'=>['professional_title'=>'Doctor','specialty'=>'Classical Homeopathy'],
];
function spd_get_search_projection($id) {
    return $id===ID ? $GLOBALS['current_projection'] : new WP_Error();
}
require __DIR__.'/../includes/class-spd-contracts.php';
function expect($ok,$why) {
  if(!$ok){fwrite(STDERR,"FAIL D21: ".$why."\n");exit(1);}
}
$doc=[
  'connector_slug'=>'file03-profiles',
  'object_id'=>ID,'object_version'=>18,'entity_type'=>'doctor',
  'normalized_body'=>'doctor alpha doctor classical homeopathy pakistan lahore',
  'state'=>'active','visibility'=>'public',
  'title'=>'Doctor Alpha','excerpt'=>'Approved public biography',
  'canonical_url'=>'https://example.test/profile/d1111111/',
  'country'=>'Pakistan','location'=>'Lahore',
  'payload'=>['verified_doctor'=>true,'professional_lifecycle'=>'active'],
];
expect(\SPD_Contracts::file26_can_view($doc),'approved current document denied');
// File26's indexer removes line breaks from a public excerpt before saving.
$GLOBALS['current_projection']['fields']['bio']="Approved\n public biography";
expect(\SPD_Contracts::file26_can_view($doc),'normal WordPress multiline excerpt normalization incorrectly denied');
$GLOBALS['current_projection']['fields']['bio']='Approved public biography';
foreach ([
   ['title','Unapproved impersonation'],
   ['excerpt','Formerly public but now private statement'],
   ['canonical_url','https://attacker.example/path'],
   ['country','OtherCountry'],
   ['location','OtherCity'],
] as [$field,$bad]) {
  $altered=$doc; $altered[$field]=$bad;
  expect(!\SPD_Contracts::file26_can_view($altered),
    'stale or forged stored '.$field.' was authorized despite canonical mismatch');
}
// Profile bio changes independently of the current normalized search body.
// A stale index excerpt must fail even if local version was not incremented.
$GLOBALS['current_projection']['fields']['bio']='Approved replacement biography';
expect(!\SPD_Contracts::file26_can_view($doc),
    'formerly public biography remained visible with unchanged search text and version');
$doc['excerpt']='Approved replacement biography';
expect(\SPD_Contracts::file26_can_view($doc),
    'current biography was not discoverable after reindex');
echo "PASS D21 canonical search display-field revalidation, privacy and positive recovery\n";
}
