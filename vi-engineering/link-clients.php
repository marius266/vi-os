<?php
require __DIR__ . '/client-common.php';
require __DIR__ . '/auth-ui.php';
require_admin();
// A phone number must identify exactly one client. Never overwrite existing links.
$uniqueClients = "SELECT TRIM(telephone) AS phone, MIN(id) AS client_id FROM clients WHERE TRIM(COALESCE(telephone,'')) <> '' GROUP BY TRIM(telephone) HAVING COUNT(*) = 1";
$matching = "FROM job_cards j JOIN ($uniqueClients) c ON TRIM(j.customer_telephone) = c.phone WHERE j.client_id IS NULL";
$preview = $pdo->query("SELECT COUNT(*) $matching")->fetchColumn();
$unlinked = $pdo->query('SELECT COUNT(*) FROM job_cards WHERE client_id IS NULL')->fetchColumn();
$updated = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyClientPost();
    if (($_POST['confirm'] ?? '') !== 'LINK') { http_response_code(400); exit('Confirmation missing'); }
    try {
        $pdo->beginTransaction();
        $s = $pdo->prepare("UPDATE job_cards j JOIN ($uniqueClients) c ON TRIM(j.customer_telephone) = c.phone SET j.client_id = c.client_id WHERE j.client_id IS NULL");
        $s->execute();
        $updated = $s->rowCount();
        $pdo->commit();
        $preview = $pdo->query("SELECT COUNT(*) $matching")->fetchColumn();
        $unlinked = $pdo->query('SELECT COUNT(*) FROM job_cards WHERE client_id IS NULL')->fetchColumn();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Client linking failed: '.$e->getMessage());
        http_response_code(500); exit('Linking failed; no changes committed. Check PHP error log.');
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Link Historical Jobs to Clients</title><style>body{font:16px Arial,sans-serif;background:#f4f6f8;color:#172033;margin:30px}.wrap{max-width:750px;background:white;margin:auto;padding:28px;border-radius:10px}button{background:#1d4ed8;color:white;padding:12px 18px;border:0;border-radius:6px;cursor:pointer}a{color:#1d4ed8}</style></head><body>
<?php require __DIR__ . '/theme-ui.php'; ?><div class="wrap"><a href="clients.php">← Clients</a><h1>Link Existing Job Cards to Clients</h1>
<?php if ($updated !== null): ?><p><strong><?= (int)$updated ?> job(s) linked successfully.</strong></p><?php endif; ?>
<p>Jobs eligible for a unique telephone match: <strong><?= (int)$preview ?></strong></p>
<p>Jobs still without a permanent Client ID: <strong><?= (int)$unlinked ?></strong></p>
<p>This process updates <strong>only</strong> the <code>client_id</code> field for jobs with no existing link, where a telephone number belongs to exactly one client. It does not modify client names, financial values, or raw FileMaker data. Unmatched and shared telephone numbers remain unlinked for manual review.</p>
<form method="post" onsubmit="return confirm('Link uniquely matched historical jobs to clients?');"><input type="hidden" name="csrf" value="<?= h($_SESSION['client_csrf']) ?>"><input type="hidden" name="confirm" value="LINK"><button type="submit" <?= (int)$preview === 0 ? 'disabled' : '' ?>>Link <?= (int)$preview ?> Matching Jobs</button></form>
</div></body></html>
