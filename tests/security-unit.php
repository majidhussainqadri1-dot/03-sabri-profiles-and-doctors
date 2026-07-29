<?php
function wp_json_encode($value,$flags=0){return json_encode($value,$flags);}
function sanitize_key($value){return preg_replace('/[^a-z0-9_\-]/','',strtolower((string)$value));}
function absint($value){return abs((int)$value);}
function apply_filters($tag,$value){return $value;}
function get_option($key,$default=0){return $default;}
function get_user_meta($id,$key,$single=true){return '';}
function get_users($args=array()){return array();}
function get_userdata($id){return false;}
if(!defined('ABSPATH'))define('ABSPATH',__DIR__);
require_once dirname(__DIR__).'/includes/class-spd-membership-adapter.php';
require_once dirname(__DIR__).'/includes/class-spd-verification-adapter.php';

$data=array('display_name'=>'Doctor Example','country'=>'PK','profile_photo_id'=>12);
$one=SPD_Verification_Adapter::fingerprint($data);
$two=SPD_Verification_Adapter::fingerprint(array_reverse($data,true));
if(!hash_equals($one,$two)){fwrite(STDERR,"Fingerprint must be canonical\n");exit(1);}
if('Verified'!==SPD_Verification_Adapter::status_label('verified')){fwrite(STDERR,"Status label failed\n");exit(1);}
if('Profile changes awaiting re-review'!==SPD_Verification_Adapter::status_label('changes_pending')){fwrite(STDERR,"Change-control label failed\n");exit(1);}
echo "Security unit checks passed.\n";
