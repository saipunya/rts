<?php
declare(strict_types=1);

// All public PHP entry points live in the installation directory.
function app_base_path(): string {
    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $directory = str_replace('\\', '/', dirname($script));
    return $directory === '/' || $directory === '.' ? '' : rtrim($directory, '/');
}

function app_url(string $path = ''): string {
    return app_base_path() . '/' . ltrim($path, '/');
}

// Login destinations are local page names, never another installation or host.
function app_login_destination(string $destination): string {
    if (!preg_match('~\A[a-zA-Z0-9_-]+\.php(?:[?#][^\r\n\\\\]*)?\z~D', $destination)) {
        $destination = 'dashboard.php';
    }
    return app_url($destination);
}

if (session_status() === PHP_SESSION_NONE) {
    // Separate names also prevent the root site's cookie from being used in /test/.
    session_name('RTSSESSID_' . substr(hash('sha256', app_base_path() ?: '/'), 0, 16));
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => app_base_path() . '/',
        'domain' => '',
        'secure' => !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();
}
