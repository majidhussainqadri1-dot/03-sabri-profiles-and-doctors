<?php
/**
 * File 03 / V12.3 — read-only runtime reality freeze.
 *
 * Execute only on an explicitly authorized WordPress staging or live host:
 *   wp eval-file tools/f03-v123-readonly-freeze.php --path=/path/to/wordpress
 *
 * Emits bounded JSON with no user records, raw logs, secrets or configuration
 * values. Read-only: this utility must never write options, rows or files.
 * Repository SHA/checksums from GitHub alone cannot replace this installed
 * host evidence. Do not post the raw output publicly without review.
 */
defined('ABSPATH') || exit;
global $wpdb;
if (!defined('SPD_DIR') || !defined('SPD_VERSION') || !class_exists('SPD_Schema_Guard')) {
    fwrite(STDERR, "File03 V12.3 freeze refused: installed File03 is not loaded.\n");
    exit(2);
}

$dir = realpath(SPD_DIR);
if (!$dir || !is_dir($dir)) {
    fwrite(STDERR, "File03 V12.3 freeze refused: runtime directory unavailable.\n");
    exit(2);
}
$paths = ['sabri-profiles-doctors.php', 'uninstall.php', 'readme.txt'];
foreach (['includes','assets'] as $sub) {
    $root = $dir . DIRECTORY_SEPARATOR . $sub;
    if (!is_dir($root)) continue;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->isLink()) continue;
        $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($dir)+1));
        if (!str_starts_with($relative, $sub . '/')) continue;
        $paths[] = $relative;
    }
}
$paths = array_values(array_unique($paths));
sort($paths, SORT_STRING);
$files = [];
foreach ($paths as $path) {
    $full = $dir.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$path);
    $real = realpath($full);
    if (!$real || !str_starts_with($real, $dir.DIRECTORY_SEPARATOR) || !is_file($real)) {
        $files[$path] = 'MISSING_OR_INVALID';
    } else {
        $hash=hash_file('sha256',$real);
        $files[$path] = $hash === false ? 'HASH_UNAVAILABLE' : $hash;
    }
}
// This is a deterministic installed-file manifest hash, NOT the ZIP SHA256.
$manifest = [];
foreach ($files as $path=>$hash) $manifest[]=$path."\t".$hash;
$manifest_sha256=hash('sha256',implode("\n",$manifest)."\n");

$active=(array)get_option('active_plugins',[]);
sort($active,SORT_STRING);
$active_descriptors=[];
if (file_exists(ABSPATH.'wp-admin/includes/plugin.php')) {
    require_once ABSPATH.'wp-admin/includes/plugin.php';
}
foreach ($active as $plugin_basename) {
    $relative=(string)$plugin_basename;
    if (!preg_match('/^[A-Za-z0-9_.\-\/]+$/D',$relative) || str_contains($relative,'..')) continue;
    $plugin_path=WP_PLUGIN_DIR.'/'.$relative;
    $version='';
    if (function_exists('get_plugin_data') && is_file($plugin_path)) {
        $data=get_plugin_data($plugin_path,false,false);
        $version=(string)($data['Version']??'');
    }
    $active_descriptors[]=['plugin'=>$relative,'version'=>$version];
}
$mysql=$wpdb->get_var('SELECT VERSION()');
$mysql_version = is_string($mysql) ? substr($mysql,0,120) : 'UNVERIFIED';
if ($wpdb->last_error) $mysql_version='UNVERIFIED';
$cursor=get_option('spd_migration_cursor',null);

$evidence=[
    'source'=>'installed-wordpress-runtime',
    'date_utc'=>gmdate('c'),
    'site_environment'=>function_exists('wp_get_environment_type')?wp_get_environment_type():'unknown',
    'public_site_url'=>site_url(),
    'wordpress'=>get_bloginfo('version'),
    'php'=>PHP_VERSION,
    'mysql_or_mariadb'=>$mysql_version,
    'file03'=>[
        'declared_plugin_version'=>SPD_VERSION,
        'declared_public_contract'=>defined('SPD_CONTRACT_VERSION')?SPD_CONTRACT_VERSION:'UNVERIFIED',
        'declared_db_schema'=>defined('SPD_DB_VERSION')?SPD_DB_VERSION:'UNVERIFIED',
        'installed_manifest_sha256'=>$manifest_sha256,
        'installed_manifest_file_count'=>count($files),
        'runtime_files_sha256'=>$files,
        'db_version_option'=>get_option('spd_db_version','UNVERIFIED'),
        'central_schema_option'=>get_option('spd_central_schema_version','UNVERIFIED'),
        'future_schema_option'=>get_option('spd_future_schema_version','UNVERIFIED'),
        'schema_base_ready'=>(bool)SPD_Schema_Guard::base_ready(),
        'schema_central_ready'=>(bool)SPD_Schema_Guard::central_ready(),
        'schema_future_ready'=>(bool)SPD_Schema_Guard::future_ready(),
        'migration_cursor'=>is_numeric($cursor)?(int)$cursor:'UNVERIFIED',
        'safe_mode'=>(bool)get_option('spd_safe_mode',false),
        'safe_mode_reason_recorded'=>'' !== trim((string)get_option('spd_safe_mode_reason','')),
        'safe_mode_changed_at'=>(string)get_option('spd_safe_mode_changed_at',''),
    ],
    'active_plugins'=>$active_descriptors,
    'truth_boundary'=>'Read-only installed evidence; does not itself prove a passed staging suite, a backup/restore, a human approval, or a live rollout.',
];
echo wp_json_encode($evidence,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n";
