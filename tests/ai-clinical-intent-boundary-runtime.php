<?php
/**
 * F03 V12.3 D11: native clinical-scope boundary for grounded professional
 * work. The real File03 method runs against synthetic current File16 claims.
 * This is defense-in-depth and never substitutes for File16's own safety
 * checks or multilingual medical-prompt review on staging.
 */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
define('SPD_CONTRACT_VERSION', '1.4.0');
class WP_Error {
    private string $code;
    public function __construct(string $code = '', string $message = '', array $data = []) { $this->code = $code; }
    public function get_error_code(): string { return $this->code; }
}
function is_wp_error($v): bool { return $v instanceof WP_Error; }
function absint($v): int { return abs((int) $v); }
function sanitize_key($v): string { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $v)); }
function __($s, $domain = '') { return $s; }
function apply_filters_ref_array($filter, $args) {
    return [
        'contract_version' => '1.4.0',
        'user_id' => 12,
        'grounded' => true,
        'scope' => 'public_professional_work',
        'answer' => 'Synthetic response from a provider stub',
        'citations' => [],
    ];
}
class SPD_Membership_Adapter { public static function is_member_eligible($id) { return true; } }
class SPD_Authorization { public static function profile_visibility_allows($p, $id) { return true; } }
class SPD_Profile_Repository {
    public static function instance() { return new self(); }
    public function find_by_public_id($id) { return ['user_id' => 12, 'public_id' => $id]; }
}
class SPD_Helpers {
    public static function sanitize_multiline($v, $n = 500) { return substr(trim((string) $v), 0, $n); }
    public static function current_contract_claim($claim, $version, $age) { return true; }
}
require __DIR__ . '/../includes/class-spd-future-profile.php';
$cases = [
    'Please diagnose my illness',
    'Can you prescribe a remedy?',
    'Write a prescription for my cough',
    'What dosage should I take?',
    'مجھے تشخیص دیں',
    'علاج تجویز کریں',
];
foreach ($cases as $question) {
    $result = SPD_Future_Profile::ask_about_work('f2222222-2222-4222-8222-222222222222', 7, $question);
    if (!is_wp_error($result) || $result->get_error_code() !== 'spd_ai_scope_restricted') {
        fwrite(STDERR, 'FAIL D11 clinical query passed source boundary: ' . $question . "\n");
        exit(1);
    }
}
foreach (['Which books has this doctor published?', 'Tell me about the public research portfolio.'] as $question) {
    $result = SPD_Future_Profile::ask_about_work('f2222222-2222-4222-8222-222222222222', 7, $question);
    if (!is_array($result) || empty($result['grounded']) || ($result['scope'] ?? '') !== 'public_professional_work') {
        fwrite(STDERR, "FAIL D11 legitimate public-work query was rejected\n"); exit(1);
    }
}
echo "PASS File03 D11 clinical scope boundary and ordinary public-work queries\n";
