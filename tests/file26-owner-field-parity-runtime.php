<?php
/**
 * D07 fail-first: File26 presents a persistent index title/location after a
 * successful owner can_view callback. File00 display-name changes can occur
 * without changing File03's native profile version. A recheck of version and
 * File09 badge alone cannot prevent stale public identity from being shown.
 *
 * Exact File03 native contracts; File26 persistence and File00 are mocked.
 * NOT a Hostinger Staging or Live test.
 */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
class WP_Error { public function __construct(public string $code='missing'){} }
function is_wp_error($x){return $x instanceof WP_Error;}
function sanitize_text_field($x){return trim(strip_tags((string)$x));}
function sanitize_key($x){return preg_replace('/[^a-z0-9_-]/','',strtolower((string)$x));}
function absint($x){return abs((int)$x);}
function esc_url_raw($x){return (string)$x;}
class SPD_Helpers {
    public static function normalize_locale($x){return (string)$x;}
    public static function sanitize_multiline($x,$max=1000){return substr((string)$x,0,$max);}
}
const PUBLIC_ID = 'd7777777-7777-4777-8777-777777777777';
$GLOBALS['current_f03']=[
    'canonical_id'=>PUBLIC_ID,'canonical_url'=>'https://example.test/profile/'.PUBLIC_ID.'/',
    'profile_type'=>'member','version'=>14,'locale'=>'en-US','display_name'=>'Old Public Name',
    'badge'=>['key'=>'member','verified'=>false],
    'fields'=>['bio'=>'Public biography','country'=>'Pakistan','city'=>'Previous Public City'],
    'professional'=>[],
];
function spd_get_search_projection($identity){return PUBLIC_ID===$identity?$GLOBALS['current_f03']:new WP_Error();}
require_once __DIR__.'/../includes/class-spd-contracts.php';
function check(bool $condition,string $name){if(!$condition){fwrite(STDERR,"FAIL: ".$name."\n");exit(1);}}
$indexed=SPD_Contracts::file26_fetch_object(PUBLIC_ID);
check(($indexed['title']??'')==='Old Public Name' && ($indexed['location']??'')==='Previous Public City','Fixture: File26 indexed earlier File00 name and File03 public city.');
check(SPD_Contracts::file26_can_view($indexed)===true,'Current saved index is viewable while owner data has not changed.');
$GLOBALS['current_f03']['display_name']='New Approved Public Name';
check($GLOBALS['current_f03']['version']===14,'Fixture: File00 name change leaves File03 native version unchanged.');
check(SPD_Contracts::file26_can_view($indexed)===false,'D07: old File00 display name is still exposed by File26 indexed title.');
$fresh=SPD_Contracts::file26_fetch_object(PUBLIC_ID);
check(SPD_Contracts::file26_can_view($fresh)===true,'Reindexed current owner title must be viewable.');
$GLOBALS['current_f03']['fields']['city']='';
check(SPD_Contracts::file26_can_view($fresh)===false,'D07: previous public location remains in File26 after owner projection becomes privacy-redacted.');
$no_location=SPD_Contracts::file26_fetch_object(PUBLIC_ID);
check(SPD_Contracts::file26_can_view($no_location)===true,'Latest empty-location index must be viewable, no false denial.');
echo "PASS D07 File03/File26 current title and location read-time indexed-field parity\n";
