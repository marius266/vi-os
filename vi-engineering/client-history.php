<?php
require __DIR__ . '/client-common.php';
require __DIR__ . '/client-master.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) { http_response_code(400); exit('Invalid client ID'); }
$id=masterId($pdo,(int)$id);
$s = $pdo->prepare('SELECT id, client_name, telephone FROM clients WHERE id = ?');
$s->execute([$id]);
$client = $s->fetch(PDO::FETCH_ASSOC);
if (!$client) { http_response_code(404); exit('Client not found'); }
// Show only permanent links; ambiguous legacy records must be reviewed before linking.
$s = $pdo->prepare('SELECT id, legacy_job_number, date_in_raw, machine_type, serial_number, fault, job_status FROM job_cards WHERE client_id = ? OR client_id IN (SELECT id FROM clients WHERE master_client_id=?) ORDER BY id DESC');
$s->execute([$id,$id]);
$jobs = $s->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Job History | <?= h($client['client_name']) ?></title>
<style>body{font:15px Arial,sans-serif;background:#f4f6f8;color:#172033;margin:24px}.wrap{max-width:1150px;margin:auto}a{color:#1d4ed8}.btn{display:inline-block;background:#1d4ed8;color:white;padding:10px 15px;border-radius:6px;text-decoration:none;margin-right:8px}table{border-collapse:collapse;width:100%;background:white;margin-top:18px}th,td{padding:11px;border-bottom:1px solid #ddd;text-align:left}th{background:#e8edf3}small{color:#64748b}</style></head><body>
<?php require __DIR__ . '/theme-ui.php'; ?><main class="wrap">
<a class="btn" href="clients.php">← Clients</a><a class="btn" href="client-edit.php?id=<?= $id ?>">Edit Client</a>
<h1><?= h($client['client_name']) ?> — Job History</h1><p>Telephone: <?= h($client['telephone']) ?> · <?= count($jobs) ?> linked job(s)</p>
<?php if (!$jobs): ?><p>No permanently linked jobs yet. Use <a href="link-clients.php">Link Existing Jobs</a> to safely connect legacy records.</p><?php else: ?>
<table><thead><tr><th>Job #</th><th>Date In</th><th>Machine</th><th>Serial</th><th>Fault</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($jobs as $j): ?><tr><td><?= h($j['legacy_job_number']) ?></td><td><?= h($j['date_in_raw']) ?></td><td><?= h($j['machine_type']) ?></td><td><?= h($j['serial_number']) ?></td><td><?= h($j['fault']) ?></td><td><?= h($j['job_status']) ?></td><td><a href="job-card.php?id=<?= (int)$j['id'] ?>">Open Job</a></td></tr><?php endforeach; ?>
</tbody></table><?php endif; ?><p><small>Job history includes permanently linked records only. Unmatched or ambiguous historical jobs are excluded until reviewed.</small></p></main></body></html>
