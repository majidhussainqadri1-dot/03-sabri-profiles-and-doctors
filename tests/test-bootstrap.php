<?php
error_reporting( E_ALL );

class WP_Error {
	private $code; private $message; private $data;
	public function __construct( $code = '', $message = '', $data = null ) { $this->code=$code; $this->message=$message; $this->data=$data; }
	public function get_error_code(){ return $this->code; }
	public function get_error_message(){ return $this->message; }
	public function get_error_data(){ return $this->data; }
}
function is_wp_error($v){return $v instanceof WP_Error;}
$GLOBALS['filters']=array();
function add_filter($tag,$callback,$priority=10,$args=1){$GLOBALS['filters'][$tag][$priority][]=array($callback,$args);return true;}
function has_filter($tag){return !empty($GLOBALS['filters'][$tag]);}
function apply_filters($tag,$value,...$args){if(empty($GLOBALS['filters'][$tag]))return$value;ksort($GLOBALS['filters'][$tag]);foreach($GLOBALS['filters'][$tag] as $callbacks){foreach($callbacks as [$cb,$accepted]){$value=call_user_func_array($cb,array_slice(array_merge([$value],$args),0,$accepted));}}return$value;}
function add_action($tag,$callback,$priority=10,$args=1){return add_filter($tag,$callback,$priority,$args);}
function do_action($tag,...$args){if(empty($GLOBALS['filters'][$tag]))return;ksort($GLOBALS['filters'][$tag]);foreach($GLOBALS['filters'][$tag] as $callbacks){foreach($callbacks as [$cb,$accepted]){call_user_func_array($cb,array_slice($args,0,$accepted));}}}
function sanitize_key($v){return preg_replace('/[^a-z0-9_\-]/','',strtolower((string)$v));}
function sanitize_text_field($v){return trim(strip_tags((string)$v));}
function sanitize_textarea_field($v){return trim(strip_tags((string)$v));}
function sanitize_email($v){return filter_var((string)$v,FILTER_SANITIZE_EMAIL);}
function sanitize_title($v){$v=strtolower(trim((string)$v));$v=preg_replace('/[^a-z0-9]+/','-',$v);return trim($v,'-');}
function absint($v){return abs((int)$v);}
function wp_json_encode($v,$flags=0){return json_encode($v,$flags);}
function wp_parse_args($a,$d=array()){return array_merge($d,(array)$a);}
function wp_generate_uuid4(){return '123e4567-e89b-42d3-a456-426614174000';}
function get_user_locale($id=0){return 'ur_PK';}
function get_current_user_id(){return absint($GLOBALS['current_user_id']??0);}
function __($t,$d=null){return$t;}
function esc_url_raw($v){return filter_var((string)$v,FILTER_SANITIZE_URL);}
function wp_kses_post($v){return strip_tags((string)$v,'<p><br><strong><em>');}
function home_url($path=''){return 'https://example.test'.('/'===substr((string)$path,0,1)?$path:'/'.$path);}
function wp_parse_url($url,$component=-1){return parse_url($url,$component);}
function get_userdata($id){$id=absint($id);if(empty($GLOBALS['users'][$id]))return false;return(object)array_merge(array('ID'=>$id,'display_name'=>'User '.$id,'user_email'=>'user'.$id.'@example.test'),$GLOBALS['users'][$id]);}
function user_can($user,$cap){$id=is_object($user)?$user->ID:absint($user);return !empty($GLOBALS['caps'][$id][$cap]);}
function current_user_can($cap){return user_can(get_current_user_id(),$cap);}
function get_user_meta($id,$key,$single=true){return $GLOBALS['user_meta'][$id][$key]??'';}
function update_user_meta($id,$key,$value){$GLOBALS['user_meta'][$id][$key]=$value;return true;}
function get_option($key,$default=false){return $GLOBALS['options'][$key]??$default;}
function update_option($key,$value,$autoload=false){$GLOBALS['options'][$key]=$value;return true;}
function delete_option($key){unset($GLOBALS['options'][$key]);return true;}
function get_transient($key){return $GLOBALS['transients'][$key]??false;}
function set_transient($key,$value,$ttl){$GLOBALS['transients'][$key]=$value;return true;}
function delete_transient($key){unset($GLOBALS['transients'][$key]);return true;}
function wp_cache_get($k,$g=''){return false;}
function wp_cache_set($k,$v,$g='',$ttl=0){return true;}
function wp_cache_delete($k,$g=''){return true;}
function wp_get_attachment_image_url($id,$size){return 'https://example.test/uploads/'.absint($id).'.jpg';}
function get_post_meta($id,$key,$single=true){return $GLOBALS['post_meta'][$id][$key]??'';}
function gmdate_test(){return gmdate('c');}

if(!defined('ABSPATH'))define('ABSPATH',dirname(__DIR__).'/');
if(!defined('SPD_CONTRACT_VERSION'))define('SPD_CONTRACT_VERSION','1.2.0');
if(!defined('SPD_VERSION'))define('SPD_VERSION','1.0.0-rc2');
if(!defined('SPD_DB_VERSION'))define('SPD_DB_VERSION','1.2.0');
if(!defined('SMC_VERSION'))define('SMC_VERSION','1.2.11');
if(!defined('SMC_CONTRACT_VERSION'))define('SMC_CONTRACT_VERSION','1.2.0');
$GLOBALS['smc_claims']=array();
function smc_membership_assertions($id){return $GLOBALS['smc_claims'][absint($id)]??array();}
function smc_user_status($id){return $GLOBALS['smc_claims'][absint($id)]['status']??'not_enrolled';}
function smc_founder_user_id(){return 1;}
function smc_is_founder($id){return 1===absint($id);}

function test_assert($condition,$message){if(!$condition){fwrite(STDERR,"FAIL: $message\n");exit(1);}}
function test_reset_filters(){$GLOBALS['filters']=array();}
