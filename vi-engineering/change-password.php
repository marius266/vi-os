<?php
declare(strict_types=1);
require __DIR__ . '/config/database.php';
require_once __DIR__ . '/auth.php';
header('Cache-Control: no-store, private');
$_SESSION['change_password_csrf'] ??= bin2hex(random_bytes(32));
function cpesc($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$error=''; $done=false;
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!is_string($_POST['csrf']??null) || !hash_equals($_SESSION['change_password_csrf'],$_POST['csrf'])) {http_response_code(403);exit('Invalid request token');}
    $old=$_POST['current_password']??null; $new=$_POST['new_password']??null; $confirm=$_POST['confirm_password']??null;
    if (!is_string($old)||!is_string($new)||!is_string($confirm)||strlen($new)<12||strlen($new)>1024||$new!==$confirm) $error='Check your password fields. New password must be at least 12 characters and match confirmation.';
    else {
        $q=$pdo->prepare('SELECT password_hash FROM app_users WHERE id=? AND active=1');$q->execute([(int)current_user()['id']]);$hash=$q->fetchColumn();
        if (!$hash || !password_verify($old,$hash)) $error='Current password is incorrect.';
        else { $q=$pdo->prepare('UPDATE app_users SET password_hash=? WHERE id=?');$q->execute([password_hash($new,PASSWORD_DEFAULT),(int)current_user()['id']]);$done=true; }
    }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>VI Engineering | Change Password</title><style>body{font:16px Arial;background:#f1f5f9;margin:0}.panel{max-width:440px;background:white;border-radius:10px;margin:50px auto;padding:26px;box-shadow:0 3px 15px #0001}label{display:block;margin:15px 0;font-weight:600}input{display:block;width:100%;box-sizing:border-box;padding:11px;margin-top:6px;border:1px solid #cbd5e1;border-radius:5px}button{background:#1d4ed8;color:white;border:0;border-radius:5px;padding:12px 18px;cursor:pointer}.error{color:#b91c1c}.good{color:#166534}</style></head><body><?php require __DIR__.'/auth-ui.php'; ?><main class="panel"><a href="jobs.php">← Jobs</a><h1>Change Password</h1><?php if($error):?><p class="error"><?=cpesc($error)?></p><?php endif;?><?php if($done):?><p class="good">Password changed successfully.</p><?php else:?><form method="post"><input type="hidden" name="csrf" value="<?=cpesc($_SESSION['change_password_csrf'])?>"><label>Current password<input type="password" name="current_password" autocomplete="current-password" required></label><label>New password<input type="password" name="new_password" minlength="12" autocomplete="new-password" required></label><label>Confirm new password<input type="password" name="confirm_password" minlength="12" autocomplete="new-password" required></label><button type="submit">Update Password</button></form><?php endif;?></main></body></html>
