<?php
// Minimal immutable bootstrap surface: detects include-order, duplicate-symbol and
// parse/runtime-composition failures without pretending to be WordPress staging.
function plugin_dir_path($file){return dirname($file).'/';}
function plugin_dir_url($file){return 'https://example.test/wp-content/plugins/file03/';}
function plugin_basename($file){return basename($file);}
function register_activation_hook($file,$callback){}
function register_deactivation_hook($file,$callback){}
function add_action($hook,$callback,$priority=10,$accepted_args=1){}
function absint($value){return abs((int)$value);}
if(!defined('ABSPATH'))define('ABSPATH',dirname(__DIR__).'/');
require dirname(__DIR__).'/sabri-profiles-doctors.php';
$classes=array(
 'SPD_DB','SPD_Membership_Adapter','SPD_Verification_Adapter','SPD_Contracts',
 'SPD_Authorization','SPD_Helpers','SPD_Profile_Repository','SPD_Media',
 'SPD_Timeline','SPD_Routes','SPD_REST','SPD_Frontend','SPD_Privacy',
 'SPD_Observability','SPD_Admin','SPD_Activator','SPD_Plugin'
);
foreach($classes as $class){if(!class_exists($class)){fwrite(STDERR,"Missing class: $class\n");exit(1);}}
$traits=array(
 'SPD_Profile_Identity_Create','SPD_Profile_Identity_Read','SPD_Profile_Public_DTO',
 'SPD_Profile_Edit_Model','SPD_Profile_Update','SPD_Profile_Professional',
 'SPD_Profile_Media','SPD_Profile_Moderation','SPD_Profile_Lifecycle',
 'SPD_Profile_Events','SPD_Profile_Cache','SPD_Frontend_Profile',
 'SPD_Frontend_Timeline','SPD_Frontend_Edit','SPD_Frontend_Report','SPD_Frontend_Helpers'
);
foreach($traits as $trait){if(!trait_exists($trait)){fwrite(STDERR,"Missing trait: $trait\n");exit(1);}}
foreach(array('spd_get_public_profile','spd_get_profile_timeline','spd_get_profile_contract_manifest') as $function){if(!function_exists($function)){fwrite(STDERR,"Missing public contract: $function\n");exit(1);}}
echo "Plugin bootstrap composition passed.\n";
