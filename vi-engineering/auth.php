<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ini_set('session.cookie_secure', '1');
    session_start();
}
function current_user(): ?array { return $_SESSION['vi_user'] ?? null; }
function require_login(): void {
    if (PHP_SAPI === 'cli' || defined('VI_AUTH_PUBLIC')) return;
    $user = current_user();
    if (!$user || !isset($_SESSION['vi_last']) || time() - (int)$_SESSION['vi_last'] > 1800) {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);
        header('Location: login.php', true, 303); exit;
    }
    // Recheck account status and role on every authenticated request.
    require_once __DIR__ . '/config/database.php';
    global $pdo;
    $q = $pdo->prepare('SELECT username, role, active FROM app_users WHERE id=?');
    $q->execute([(int)$user['id']]);
    $fresh = $q->fetch(PDO::FETCH_ASSOC);
    if (!$fresh || !(int)$fresh['active']) {
        $_SESSION = [];
        session_regenerate_id(true);
        header('Location: login.php', true, 303); exit;
    }
    $_SESSION['vi_user']['username'] = $fresh['username'];
    $_SESSION['vi_user']['role'] = $fresh['role'];
    $_SESSION['vi_last'] = time();
    header('Cache-Control: no-store, private');
}
function require_admin(): void {
    require_login();
    if ((current_user()['role'] ?? '') !== 'admin') { http_response_code(403); exit('Administrator permission required.'); }
}
require_login();
