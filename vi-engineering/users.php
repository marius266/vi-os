<?php
declare(strict_types=1);
require __DIR__ . '/config/database.php';
require_once __DIR__ . '/auth.php';
require_admin();
header('Cache-Control: no-store, private');
$_SESSION['users_csrf'] ??= bin2hex(random_bytes(32));
function uh($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$msg = ''; $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf'] ?? null;
    if (!is_string($token) || !hash_equals($_SESSION['users_csrf'], $token)) { http_response_code(403); exit('Invalid request token'); }
    $action = (string)($_POST['action'] ?? '');
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $role = (string)($_POST['role'] ?? '');
    $password = $_POST['password'] ?? '';
    try {
        if ($action === 'create') {
            $name = trim((string)($_POST['username'] ?? ''));
            if (!preg_match('/^[A-Za-z0-9_.-]{3,80}$/D', $name)) throw new RuntimeException('Username must be 3–80 characters (letters, digits, _, . or -).');
            if (!in_array($role, ['admin','staff'], true)) throw new RuntimeException('Invalid role.');
            if (!is_string($password) || strlen($password) < 12 || strlen($password) > 1024) throw new RuntimeException('Password must be 12–1024 characters.');
            $q=$pdo->prepare('INSERT INTO app_users (username,password_hash,role,active) VALUES (?,?,?,1)');
            $q->execute([$name,password_hash($password,PASSWORD_DEFAULT),$role]);
            $msg='User created.';
        } elseif (in_array($action, ['role','status','reset'], true)) {
            if (!$id || $id < 1) throw new RuntimeException('Invalid user.');
            $pdo->beginTransaction();
            // Lock administrator rows so simultaneous requests cannot remove the last admin.
            $admins=$pdo->query("SELECT id FROM app_users WHERE role='admin' AND active=1 FOR UPDATE")->fetchAll(PDO::FETCH_COLUMN);
            $q=$pdo->prepare('SELECT id,role,active FROM app_users WHERE id=? FOR UPDATE'); $q->execute([$id]);
            $target=$q->fetch(PDO::FETCH_ASSOC);
            if (!$target) throw new RuntimeException('User not found.');
            if ($action === 'role') {
                if (!in_array($role,['admin','staff'],true)) throw new RuntimeException('Invalid role.');
                if ($id === (int)current_user()['id'] && $role !== 'admin') throw new RuntimeException('You cannot demote your own account.');
                if ($target['role']==='admin' && (int)$target['active']===1 && $role!=='admin' && count($admins)<=1) throw new RuntimeException('Cannot demote the last active administrator.');
                $q=$pdo->prepare('UPDATE app_users SET role=? WHERE id=?'); $q->execute([$role,$id]); $msg='Role updated.';
            } elseif ($action === 'status') {
                $active=(string)($_POST['active'] ?? '');
                if (!in_array($active,['0','1'],true)) throw new RuntimeException('Invalid status.');
                if ($id === (int)current_user()['id'] && $active==='0') throw new RuntimeException('You cannot disable your own account.');
                if ($target['role']==='admin' && (int)$target['active']===1 && $active==='0' && count($admins)<=1) throw new RuntimeException('Cannot disable the last active administrator.');
                $q=$pdo->prepare('UPDATE app_users SET active=? WHERE id=?'); $q->execute([(int)$active,$id]); $msg='Account status updated.';
            } else {
                if (!is_string($password) || strlen($password)<12 || strlen($password)>1024) throw new RuntimeException('Password must be 12–1024 characters.');
                $q=$pdo->prepare('UPDATE app_users SET password_hash=? WHERE id=?'); $q->execute([password_hash($password,PASSWORD_DEFAULT),$id]); $msg='Password reset.';
            }
            $pdo->commit();
        } else throw new RuntimeException('Unknown action.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($e instanceof PDOException) { error_log('User management: '.$e->getMessage()); $error='Database operation failed (possibly duplicate username).'; }
        else $error=$e->getMessage();
    }
}
$rows=$pdo->query('SELECT id,username,role,active,created_at FROM app_users ORDER BY username')->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>VI Engineering | Manage Users</title><style>
body{margin:0;background:#f1f5f9;color:#172033;font:15px Arial,sans-serif}.wrap{max-width:1100px;margin:24px auto;padding:0 16px}h1{margin:15px 0}.panel{background:#fff;border-radius:10px;padding:20px;margin:18px 0;box-shadow:0 3px 15px #0001}input,select{padding:9px;border:1px solid #cbd5e1;border-radius:5px;max-width:100%;box-sizing:border-box}button,.btn{background:#1d4ed8;color:white;border:0;padding:9px 13px;border-radius:5px;cursor:pointer;text-decoration:none;display:inline-block}button.danger{background:#b91c1c}.row{display:flex;gap:10px;align-items:center;flex-wrap:wrap}table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:12px;border-bottom:1px solid #e2e8f0;vertical-align:top}form.inline{display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin:4px 0}.good{color:#166534}.error{color:#b91c1c}.muted{color:#64748b}label{font-weight:bold}@media(max-width:700px){.scroll{overflow-x:auto}}
</style></head><body>
<?php require __DIR__ . '/theme-ui.php'; ?><?php require __DIR__.'/auth-ui.php'; ?><main class="wrap"><a class="btn" href="jobs.php">← Jobs</a><h1>User Management</h1>
<?php if($msg): ?><p class="good" role="status"><?=uh($msg)?></p><?php endif; ?><?php if($error): ?><p class="error" role="alert"><?=uh($error)?></p><?php endif; ?>
<section class="panel"><h2>Add User</h2><form method="post" class="row"><input type="hidden" name="csrf" value="<?=uh($_SESSION['users_csrf'])?>"><input type="hidden" name="action" value="create"><label>Username <input name="username" required minlength="3" maxlength="80" autocomplete="off"></label><label>Password <input name="password" type="password" required minlength="12" autocomplete="new-password"></label><label>Role <select name="role"><option value="staff">Staff</option><option value="admin">Admin</option></select></label><button type="submit">Create User</button></form><p class="muted">Passwords must have at least 12 characters. No password is emailed or displayed after saving.</p></section>
<section class="panel"><h2>Existing Users</h2><div class="scroll"><table><thead><tr><th>Username</th><th>Role</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead><tbody>
<?php foreach($rows as $u): ?><tr><td><strong><?=uh($u['username'])?></strong><?=((int)$u['id']===(int)current_user()['id'])?' (you)':''?></td><td><?=uh(ucfirst($u['role']))?></td><td><?=((int)$u['active']===1)?'Active':'Disabled'?></td><td><?=uh($u['created_at'])?></td><td>
<form class="inline" method="post"><input type="hidden" name="csrf" value="<?=uh($_SESSION['users_csrf'])?>"><input type="hidden" name="action" value="role"><input type="hidden" name="id" value="<?=(int)$u['id']?>"><select name="role"><option value="staff" <?=$u['role']==='staff'?'selected':''?>>Staff</option><option value="admin" <?=$u['role']==='admin'?'selected':''?>>Admin</option></select><button>Save Role</button></form>
<form class="inline" method="post"><input type="hidden" name="csrf" value="<?=uh($_SESSION['users_csrf'])?>"><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?=(int)$u['id']?>"><input type="hidden" name="active" value="<?=((int)$u['active']===1)?'0':'1'?>"><button class="<?=((int)$u['active']===1)?'danger':''?>" onclick="return confirm('Change this user account status?')"><?=((int)$u['active']===1)?'Disable':'Enable'?></button></form>
<form class="inline" method="post"><input type="hidden" name="csrf" value="<?=uh($_SESSION['users_csrf'])?>"><input type="hidden" name="action" value="reset"><input type="hidden" name="id" value="<?=(int)$u['id']?>"><input name="password" type="password" placeholder="New password (12+ chars)" minlength="12" required autocomplete="new-password"><button onclick="return confirm('Reset this user password?')">Reset Password</button></form>
</td></tr><?php endforeach; ?></tbody></table></div></section></main></body></html>
