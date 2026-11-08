<?php
// Router for the local PHP development server used by Start-Demo.bat.
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
$relative = ltrim($path, '/');
if ($path === '/_demo_health' && getenv('RTS_DEMO_HEALTH')) {
    header('Content-Type: text/plain'); header('Cache-Control: no-store');
    echo getenv('RTS_DEMO_HEALTH'); return true;
}
if (str_contains($path, "\0") || str_contains($path, '\\') || preg_match('~(^|/)\.{1,2}(/|$)|(^|/)(setup|vendor|backups|windows|developer-tools|tests)(/|$)|(^|/)\.|\.(sql|log|lock|md|bat|ini|ps1|py|nsi|exe|zip|pem|key)$~i', $path)
    || in_array($relative, ['db_config.php', 'db_config.sample.php', 'router.php', 'composer.json'], true)) {
    http_response_code(404); exit('Not found');
}
if ($relative !== 'activation.php' && ($path === '/' || preg_match('/\.php(?:\/|$)/i', $path))) {
    require_once __DIR__ . '/app_bootstrap.php';
}
if ($path === '/') { require __DIR__ . '/index.php'; return true; }
return false;
