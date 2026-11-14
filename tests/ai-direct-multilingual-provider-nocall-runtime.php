<?php
/**
 * V12.3 D18 — direct File03 caller must never forward patient-specific prompts
 * to the File16 provider before applying the same multilingual safety boundary
 * as the REST endpoint. Exercises the real Future Profile and REST guard.
 */
declare(strict_types=1);
define('ABSPATH', __DIR__.'/');
define('SPD_CONTRACT_VERSION', '1.4.0');
class WP_Error {
    private string $code;
    public function __construct(string $code = '', string $msg = '', array $data = []) {$this->code=$code;}
    public function get_error_code(): string {return $this->code;}
}
function is_wp_error($value): bool {return $value instanceof WP_Error;}
function absint($value): int {return abs((int)$value);}
function sanitize_key($value): string {return preg_replace('/[^a-z0-9_-]/','',strtolower((string)$value));}
function __($value,$domain='') {return $value;}
$GLOBALS['d18_provider_calls']=0;
function apply_filters_ref_array($filter,$args) {
    ++$GLOBALS['d18_provider_calls'];
    return ['contract_version'=>'1.4.0','user_id'=>12,'grounded'=>true,
        'scope'=>'public_professional_work','answer'=>'Synthetic professional bibliography',
        'citations'=>[]];
}
class SPD_Membership_Adapter {public static function is_member_eligible($id): bool {return true;}}
class SPD_Authorization {public static function profile_visibility_allows($p,$id): bool {return true;}}
class SPD_Profile_Repository {
    public static function instance() {return new self();}
    public function find_by_public_id($id) {return ['user_id'=>12,'public_id'=>$id];}
}
class SPD_Helpers {
    public static function sanitize_multiline($v,$max=500) {return trim(mb_substr((string)$v,0,$max,'UTF-8'));}
    public static function current_contract_claim($claim,$version,$age): bool {return true;}
}
require __DIR__.'/../includes/class-spd-future-rest.php';
require __DIR__.'/../includes/class-spd-future-profile.php';
$restricted=[
    'Please prescribe my medicine',
    'مجھے کون سی دوا لینی چاہیے؟',
    'أي دواء لعلاج مرضي؟',
    'कौन सी दवा लूँ?',
    '请给我处方',
    '¿Qué medicamento debo tomar?',
    'Quel traitement dois-je prendre?',
    'Qual medicamento devo tomar?',
    'আমাকে চিকিৎসার পরামর্শ দিন',
    'Bana reçete yaz',
    // Colloquial symptom-to-advice prompts: no explicit "prescription" word.
    'I have a fever. What should I do?',
    'مجھے بخار ہے، کیا کروں؟',
    'عندي حمى، ماذا أفعل؟',
    'मुझे बुखार है, क्या करूँ?',
    '我发烧了怎么办？',
    'Tengo fiebre, ¿qué hago?',
    "J'ai de la fièvre, que faire?",
    'Estou com febre, o que faço?',
    'আমার জ্বর হয়েছে, কী করব?',
    'Ateşim var, ne yapmalıyım?',
];
foreach ($restricted as $question) {
    $before=$GLOBALS['d18_provider_calls'];
    if (!SPD_Future_REST::medical_scope_question($question)) {
        fwrite(STDERR,"FAIL D18 fixture is outside native REST medical boundary: $question\n");exit(1);
    }
    $actual=SPD_Future_Profile::ask_about_work('f2222222-2222-4222-8222-222222222222',7,$question);
    if (!is_wp_error($actual) || $actual->get_error_code()!=='spd_ai_scope_restricted' ||
        $GLOBALS['d18_provider_calls']!==$before) {
        fwrite(STDERR,"FAIL D18 direct provider called or accepted medical prompt: $question\n");exit(1);
    }
}
$before=$GLOBALS['d18_provider_calls'];
$ok=SPD_Future_Profile::ask_about_work('f2222222-2222-4222-8222-222222222222',7,
    'Which books did this professional publish?');
if(!is_array($ok) || empty($ok['grounded']) || $GLOBALS['d18_provider_calls']!==$before+1) {
    fwrite(STDERR,"FAIL D18 legitimate professional bibliography was blocked\n");exit(1);
}
echo "PASS D18 multilingual direct consumer safety preflight and safe professional work\n";
