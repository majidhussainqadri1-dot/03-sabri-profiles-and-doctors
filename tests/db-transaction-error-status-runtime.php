<?php
/**
 * V12.3 D19: all temporary DB transaction failures must be represented as
 * unavailable 503, not ordinary client/input 400. Exercises the real helper.
 */
declare(strict_types=1);
define('ABSPATH',__DIR__.'/');
class WP_Error {
    public function __construct(private string $code, private string $message='',private array $data=[]){}
    public function get_error_code(): string {return $this->code;}
    public function get_error_data(): array {return $this->data;}
}
function is_wp_error($v) {return $v instanceof WP_Error;}
function __($m,$domain='') {return $m;}
function sanitize_key($v){return strtolower((string)$v);}
class F03DB {
    public string $last_error='';
    public string $mode='';
    public array $statements=[];
    public function query($sql) {
        $this->statements[]=$sql;
        if($this->mode==='start_fail' && $sql==='START TRANSACTION')return false;
        if($this->mode==='commit_fail' && $sql==='COMMIT')return false;
        if($this->mode==='rollback_fail' && $sql==='ROLLBACK')return false;
        return 1;
    }
}
$GLOBALS['wpdb']=new F03DB();
require __DIR__.'/../includes/class-spd-db.php';
function assert_503($result,$case){
    if(!is_wp_error($result) || ($result->get_error_data()['status']??null)!==503){
        fwrite(STDERR,"FAIL D19 $case: temporary DB failure not 503\n");exit(1);
    }
}
$db=$GLOBALS['wpdb'];
$db->mode='start_fail';
assert_503(SPD_DB::transaction(fn()=>['ok'=>true]),'START failed');
$db->mode='commit_fail';
assert_503(SPD_DB::transaction(fn()=>['ok'=>true]),'COMMIT failed');
$db->mode='';
assert_503(SPD_DB::transaction(function(){throw new RuntimeException('redacted fixture exception');}),'callback exception');
assert_503(SPD_DB::transaction(function() use($db){$db->last_error='SQL failed';return ['ok'=>true];}),'callback SQL failure');
// A real authorization failure must retain its own status (not be reclassified).
$denied=SPD_DB::transaction(fn()=>new WP_Error('spd_forbidden','Not allowed',['status'=>403]));
if(!is_wp_error($denied)||($denied->get_error_data()['status']??null)!==403){
    fwrite(STDERR,"FAIL D19 authorization error status was rewritten\n");exit(1);
}
echo "PASS D19 DB temporary unavailable statuses and preserved permission denial\n";
