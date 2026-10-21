<?php
// False-positive regression for AEGIS request-line/body classification.
declare(strict_types=1);

define('ABSPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
if (!function_exists('get_option')) {
    function get_option(string $key, mixed $default = false): mixed { return $default; }
}
if (!function_exists('wp_cache_get')) {
    function wp_cache_get(string $key, string $group = ''): false { return false; }
}
if (!function_exists('wp_cache_set')) {
    function wp_cache_set(string $key, mixed $value, string $group = '', int $ttl = 0): bool { return true; }
}
if (!function_exists('wp_normalize_path')) {
    function wp_normalize_path(string $path): string { return str_replace('\\', '/', $path); }
}
if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in(): bool { return false; }
}
if (!function_exists('current_user_can')) {
    function current_user_can(string $cap): bool { return false; }
}
if (!function_exists('add_action')) {
    function add_action(string $hook, mixed $callback, int $priority = 10, int $accepted_args = 1): bool { return true; }
}
if (!function_exists('add_filter')) {
    function add_filter(string $hook, mixed $callback, int $priority = 10, int $accepted_args = 1): bool { return true; }
}
if (!function_exists('apply_filters')) {
    function apply_filters(string $hook, mixed $value, mixed ...$args): mixed { return $value; }
}
if (!function_exists('did_action')) {
    function did_action(string $hook): int { return 0; }
}

require dirname(__DIR__) . '/includes/modules/aegis/class-vis-aegis.php';

$failures = [];
$aegis = new VIS_Aegis(['aegis_enabled' => true, 'aegis_mode' => 'strict']);

$allow = [
    'REST _fields=id' => '/wp-json/wp/v2/pages?per_page=100&_fields=id,slug,template',
    'REST rest_route' => '/index.php?rest_route=/wp/v2/pages&per_page=100&_fields=id,slug,template',
    'article curl' => '/mehrere-schwachstellen-in-curl-ermoeglichen-datenmanipulation-und-informationslecks/',
    'article nmap' => '/nmap-update-schwachstelle-ermoeglicht-dos-angriff-durch-anonymen-angreifer/',
    'article cat ba' => '/neue-erkenntnisse-zur-artenvielfalt-und-evolution-von-blattkaefern-auf-der-insel-cat-ba-vietnam/',
    'article id' => '/mehrere-schwachstellen-in-microsoft-azure-entra-id-und-exchange-online/',
    'asset manifest' => '/asset-manifest.json',
];

foreach ($allow as $label => $uri) {
    $verdict = $aegis->assess_uri($uri);
    if (($verdict['verdict'] ?? '') !== 'ALLOW') {
        $failures[] = "False positive: $label => " . json_encode($verdict);
    }
}

$block = [
    'cmd parameter' => '/?cmd=id',
    'semicolon command' => '/?file=x;whoami',
    'pipe command' => '/?file=x|whoami',
    'subshell command' => '/?q=$(id)',
    'backtick command' => '/?q=`id`',
    'path traversal' => '/?file=../../etc/passwd',
];

foreach ($block as $label => $uri) {
    $verdict = $aegis->assess_uri($uri);
    if (($verdict['verdict'] ?? '') !== 'BLOCK') {
        $failures[] = "Missed attack: $label => " . json_encode($verdict);
    }
}

$payload_allow = [
    'prose command words' => 'The cat sat on the mat and the ping was fast.',
    'markdown inline code' => 'Use `id` and `name` as placeholders.',
];
foreach ($payload_allow as $label => $payload) {
    $verdict = $aegis->assess_payload($payload, 'POST_DATA_RAW');
    if (($verdict['verdict'] ?? '') !== 'ALLOW') {
        $failures[] = "False positive payload: $label => " . json_encode($verdict);
    }
}

$payload_block = [
    'command substitution' => 'x;id',
    'backtick command' => '`whoami`',
    'rce eval' => 'system($_GET["x"]);',
];
foreach ($payload_block as $label => $payload) {
    $verdict = $aegis->assess_payload($payload);
    if (($verdict['verdict'] ?? '') !== 'BLOCK') {
        $failures[] = "Missed payload attack: $label => " . json_encode($verdict);
    }
}

if ($failures !== []) {
    fwrite(STDERR, "VGT AEGIS FALSE-POSITIVE REGRESSION: FAILED\n" . implode("\n", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "VGT AEGIS FALSE-POSITIVE REGRESSION: PASS\n");
