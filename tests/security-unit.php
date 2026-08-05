<?php
function sanitize_key($value){return preg_replace('/[^a-z0-9_\-]/','',strtolower((string)$value));}
function sanitize_text_field($value){return trim(strip_tags((string)$value));}
function sanitize_textarea_field($value){return trim(strip_tags((string)$value));}
function wp_generate_uuid4(){return '123e4567-e89b-42d3-a456-426614174000';}
function current_time($type,$gmt=false){return '2026-08-05 17:00:00';}
function wp_json_encode($value,$flags=0){return json_encode($value,$flags);}
function wp_parse_args($args,$defaults=array()){return array_merge($defaults,$args);}
function get_user_locale($id=0){return 'en_US';}
function absint($value){return abs((int)$value);}
function apply_filters($tag,$value){return $value;}
function get_userdata($id){return (object)array('ID'=>$id,'display_name'=>'Doctor Example','user_email'=>'doctor@example.test');}
function get_users($args=array()){return array();}
function get_user_meta($id,$key,$single=true){return '';}
function update_user_meta($id,$key,$value){return true;}
function get_option($key,$default=false){return $default;}
function get_current_user_id(){return 0;}
function current_user_can($cap){return false;}
function has_filter($tag){return false;}
function __($text,$domain=null){return $text;}
if(!defined('ABSPATH'))define('ABSPATH',__DIR__);
if(!defined('SPD_CONTRACT_VERSION'))define('SPD_CONTRACT_VERSION','1.0.0');
require_once dirname(__DIR__).'/includes/class-spd-helpers.php';
require_once dirname(__DIR__).'/includes/class-spd-membership-adapter.php';
require_once dirname(__DIR__).'/includes/class-spd-verification-adapter.php';

$assert = function($condition,$message){if(!$condition){fwrite(STDERR,$message."\n");exit(1);}};
$assert(SPD_Helpers::clean_phone('+44 (20) 7946-0958')==='+442079460958','Phone normalization failed');
$assert(SPD_Helpers::normalize_locale('ur_PK')==='ur-PK','Locale normalization failed');
$assert(SPD_Helpers::normalize_focal(120)===100.0,'Focal upper bound failed');
$assert(SPD_Helpers::normalize_focal(-3)===0.0,'Focal lower bound failed');
$assert(SPD_Helpers::state_transition_allowed('active','suspended','profile'),'Valid profile transition failed');
$assert(!SPD_Helpers::state_transition_allowed('tombstoned','active','profile'),'Forbidden profile transition passed');
$data=array('display_name'=>'Doctor Example','country'=>'PK','profile_photo_id'=>12);
$one=SPD_Verification_Adapter::fingerprint($data);
$two=SPD_Verification_Adapter::fingerprint(array_reverse($data,true));
$assert(hash_equals($one,$two),'Fingerprint must be canonical');
$assert(SPD_Verification_Adapter::status_label('verified')==='Verified','Status label failed');
echo "Security and state-machine unit checks passed.\n";
