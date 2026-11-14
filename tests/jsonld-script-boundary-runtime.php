<?php
/**
 * File03 V12.3 D09 - execute actual JSON-LD emitter under hostile owner
 * content. This is an isolated source regression, not live exploit evidence.
 */
declare(strict_types=1);
define('ABSPATH', __DIR__ . '/');
function sanitize_text_field($s) { return (string) $s; }
function get_query_var($name) { return '123e4567-e89b-42d3-a456-426614174000'; }
function wp_json_encode($data, $flags = 0) { return json_encode($data, $flags | JSON_THROW_ON_ERROR); }
class SPD_Routes { public function current_context() { return 'public'; } }
class SPD_Central_Profile {
    public static function structured_data($id) {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            'name' => 'Hostile </script><script>window.pwned=1</script>',
            'description' => 'Arabic/Urdu: علم و تحقیق',
        ];
    }
}
require __DIR__ . '/../includes/trait-spd-frontend-central.php';
class JSONLD_Probe { use SPD_Frontend_Central; }
ob_start();
(new JSONLD_Probe())->structured_data();
$html = ob_get_clean();
if (str_contains($html, '</script><script>')) {
    fwrite(STDERR, "FAIL File03 JSON-LD script delimiter breakout from profile DTO\n"); exit(1);
}
if (!str_contains($html, 'Hostile') || !str_contains($html, 'علم')) {
    fwrite(STDERR, "FAIL File03 JSON-LD UTF-8 structured data unavailable\n"); exit(1);
}
if (!str_contains($html, '\\u003C/script\\u003E')) {
    fwrite(STDERR, "FAIL File03 JSON-LD HTML-sensitive characters not escaped\n"); exit(1);
}
echo "PASS File03 public JSON-LD script-boundary escaping, UTF-8 and structured data preserved\n";
