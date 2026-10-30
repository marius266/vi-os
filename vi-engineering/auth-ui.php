<?php
require_once __DIR__ . '/auth.php';
$_SESSION['logout_csrf'] ??= bin2hex(random_bytes(32));
?>
<div class="auth-toolbar" style="display:flex;justify-content:flex-end;gap:12px;align-items:center;padding:8px 12px;font:14px system-ui;background:#e2e8f0">
<span>Signed in: <?=htmlspecialchars(current_user()['username'] ?? '', ENT_QUOTES, 'UTF-8')?> (<?=htmlspecialchars(current_user()['role'] ?? '', ENT_QUOTES, 'UTF-8')?>)</span>
<?php if ((current_user()['role'] ?? '') === 'admin'): ?><a href="users.php" style="font-weight:600">Manage Users</a><?php endif; ?>
<a href="change-password.php">Change Password</a>
<form action="logout.php" method="post" style="margin:0"><input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['logout_csrf'], ENT_QUOTES, 'UTF-8')?>"><button type="submit">Logout</button></form></div>
