<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method not allowed'); }
if (!hash_equals((string)($_SESSION['logout_csrf'] ?? ''), (string)($_POST['csrf'] ?? ''))) { http_response_code(403); exit('Invalid token'); }
$_SESSION = [];
if (ini_get('session.use_cookies')) {
 $p = session_get_cookie_params();
 setcookie(session_name(), '', time()-3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
header('Location: login.php', true, 303); exit;
