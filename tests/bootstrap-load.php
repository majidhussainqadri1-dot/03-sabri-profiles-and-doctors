<?php
// Minimal WordPress bootstrap surface for detecting class/trait load-order fatals.
function plugin_dir_path($file){return dirname($file).'/';}
function plugin_dir_url($file){return 'https://example.test/plugins/file03/';}
function plugin_basename($file){return basename($file);}
function register_activation_hook($file,$callback){}
function register_deactivation_hook($file,$callback){}
function add_action($hook,$callback,$priority=10,$accepted_args=1){}
function absint($value){return abs((int)$value);}
if(!defined('ABSPATH'))define('ABSPATH',dirname(__DIR__).'/');
require dirname(__DIR__).'/sabri-profiles-doctors.php';
$required=array(
 'SPD_Profile_Repository','SPD_Frontend','SPD_Plugin','SPD_REST','SPD_Routes',
 'SPD_DB','SPD_Membership_Adapter','SPD_Verification_Adapter','SPD_Contracts'
);
foreach($required as $class){if(!class_exists($class)){fwrite(STDERR,"Missing class: $class\n");exit(1);}}
foreach(array('SPD_Profile_Update','SPD_Frontend_Edit') as $trait){if(!trait_exists($trait)){fwrite(STDERR,"Missing trait: $trait\n");exit(1);}}
echo "Plugin bootstrap load order passed.\n";
