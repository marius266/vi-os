<?php
declare(strict_types=1);
define('VI_AUTH_PUBLIC', true);
require __DIR__ . '/config/database.php';
require_once __DIR__ . '/auth.php';
if (current_user()) { header('Location: jobs.php', true, 303); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $csrf = (string)($_POST['csrf'] ?? '');
    if (!hash_equals((string)($_SESSION['login_csrf'] ?? ''), $csrf)) { http_response_code(403); exit('Invalid request token.'); }
    // Shared per-username rate limit in DB, including nonexistent users.
    $key = hash('sha256', mb_strtolower($username));
    $pdo->beginTransaction();
    try {
        $q = $pdo->prepare('INSERT INTO login_attempts (username_hash, attempts, last_attempt) VALUES (?, 0, NOW()) ON DUPLICATE KEY UPDATE username_hash=username_hash');
        $q->execute([$key]);
        $q = $pdo->prepare('SELECT attempts, last_attempt FROM login_attempts WHERE username_hash=? FOR UPDATE');
        $q->execute([$key]); $limit = $q->fetch();
        $locked = (int)$limit['attempts'] >= 5 && strtotime((string)$limit['last_attempt']) > time() - 900;
        $q = $pdo->prepare('SELECT id, username, password_hash, role, active FROM app_users WHERE username=? LIMIT 1');
        $q->execute([$username]); $user = $q->fetch();
        $valid = !$locked && $user && (int)$user['active'] === 1 && password_verify($password, $user['password_hash']);
        if ($valid) {
            $q = $pdo->prepare('UPDATE login_attempts SET attempts=0, last_attempt=NOW() WHERE username_hash=?'); $q->execute([$key]);
        } else {
            $q = $pdo->prepare('UPDATE login_attempts SET attempts=IF(last_attempt < NOW() - INTERVAL 15 MINUTE, 1, attempts+1), last_attempt=NOW() WHERE username_hash=?'); $q->execute([$key]);
        }
        $pdo->commit();
        if ($valid) {
            session_regenerate_id(true);
            $_SESSION['vi_user'] = ['id'=>(int)$user['id'], 'username'=>$user['username'], 'role'=>$user['role']];
            $_SESSION['vi_last'] = time();
            unset($_SESSION['login_csrf']);
            header('Location: jobs.php', true, 303); exit;
        }
        $error = 'Invalid credentials or temporarily locked. Please try again later.';
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); error_log($e->getMessage()); $error = 'Login temporarily unavailable.'; }
}
$_SESSION['login_csrf'] ??= bin2hex(random_bytes(32));
function esc($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>VI Engineering | Login</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f1f5f9;font:16px system-ui,Arial}.panel{background:white;width:min(380px,90vw);padding:32px;border-radius:12px;box-shadow:0 8px 30px #0001}h1{font-size:24px;margin:0 0 5px}p{color:#64748b}label{display:block;margin-top:18px;font-weight:600}input{box-sizing:border-box;width:100%;padding:12px;margin-top:5px;border:1px solid #cbd5e1;border-radius:6px;font:inherit}button{width:100%;padding:13px;margin-top:22px;background:#1d4ed8;color:white;border:0;border-radius:6px;font-weight:700;cursor:pointer}.error{color:#b91c1c}</style></head><body><main class="panel"><h1>VI Engineering</h1><p>Sign in to manage jobs and clients.</p><?php if ($error): ?><p class="error" role="alert"><?=esc($error)?></p><?php endif; ?><form method="post" action="login.php"><input type="hidden" name="csrf" value="<?=esc($_SESSION['login_csrf'])?>"><label>Username<input name="username" autocomplete="username" required maxlength="80" autofocus></label><label>Password<input type="password" name="password" autocomplete="current-password" required></label><button type="submit">Sign in</button></form></main></body></html>
