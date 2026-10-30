<?php
require __DIR__ . '/config/database.php';
require_once __DIR__ . '/auth.php';

function esc($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function parseJobDate($value): ?DateTimeImmutable {
    $s = trim((string)$value);
    if ($s === '') return null;
    foreach (['!Y-m-d', '!d/m/Y', '!d-m-Y', '!Y/m/d', '!d.m.Y', '!Y-m-d H:i:s', '!Y-m-d\TH:i:s'] as $format) {
        $d = DateTimeImmutable::createFromFormat($format, $s);
        if ($d && $d->format(ltrim($format, '!')) === $s) return $d;
    }
    try { return new DateTimeImmutable($s); } catch (Throwable $e) { return null; }
}
function jobHours($value): float {
    $s = trim(str_replace(',', '.', (string)$value));
    // Blank and nonnumeric values contribute zero hours.
    return preg_match('/^\d+(?:\.\d+)?$/', $s) ? (float)$s : 0.0;
}

$today = new DateTimeImmutable('today');
$defaultStart = $today->format('Y-m-01');
$defaultEnd = $today->format('Y-m-t');
$startValue = (string)($_GET['start_date'] ?? $defaultStart);
$endValue = (string)($_GET['end_date'] ?? $defaultEnd);
function reportDate(string $value): ?DateTimeImmutable {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value ? $date : null;
}
$reportStart = reportDate($startValue);
$reportEndInclusive = reportDate($endValue);
if (!$reportStart || !$reportEndInclusive || $reportStart > $reportEndInclusive) {
    http_response_code(400);
    exit('Invalid report date range. Start Date must be on or before End Date.');
}
$reportEnd = $reportEndInclusive->modify('+1 day');
$selectedTech = trim((string)($_GET['technician'] ?? ''));

// Use the stored FileMaker field names, which are also used for newly created jobs.
// Keep all data processing on the server; no change to the database schema is needed.
$stmt = $pdo->query('SELECT id, legacy_job_number, customer_name, customer_telephone, job_status, job_type, machine_type, date_in_raw, raw_data FROM job_cards');
$all = [];
$technicians = [];
while ($job = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $raw = json_decode((string)($job['raw_data'] ?? ''), true);
    if (!is_array($raw)) $raw = [];
    $tech = trim((string)($raw['ACTION TAKEN 1'] ?? ''));
    $job['technician'] = $tech;
    $job['hours'] = jobHours($raw['TIME SPENT HRS'] ?? '');
    $job['date'] = parseJobDate($job['date_in_raw'] ?: ($raw['DATE IN'] ?? ''));
    if ($job['date'] && $job['date'] >= $reportStart && $job['date'] < $reportEnd && $tech !== '') {
        $technicians[strtolower($tech)] = $tech;
    }
    $job['status'] = trim((string)($job['job_status'] ?: ($raw['JOB STATUS'] ?? '')));
    $job['job_type_display'] = $job['job_type'] ?: ($raw['JOB TYPE'] ?? '');
    $all[] = $job;
}
natcasesort($technicians);

$rows = [];
$totalHours = 0.0;
foreach ($all as $job) {
    if ($selectedTech !== '' && strcasecmp($selectedTech, $job['technician']) !== 0) continue;
    $date = $job['date'];
    // Only Job Cards whose Date In falls within the inclusive selected date range.
    if (!$date || $date < $reportStart || $date >= $reportEnd) continue;
    $rows[] = $job;
    $totalHours += $job['hours'];
}
usort($rows, static function ($a, $b) {
    $cmp = $a['date'] <=> $b['date'];
    return $cmp ?: strnatcasecmp((string)$a['legacy_job_number'], (string)$b['legacy_job_number']);
});
$reportTitle = $reportStart->format('d/m/Y') . ' – ' . $reportEndInclusive->format('d/m/Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Technician Report — <?= esc($reportTitle) ?></title>
<style>
*{box-sizing:border-box}body{margin:0;font:14px Arial,sans-serif;background:#f2f4f7;color:#1f2937}
.top{background:#172b4d;color:white;padding:18px 25px}.top h1{margin:0;font-size:23px}
.wrap{max-width:1450px;margin:24px auto;padding:0 20px}.filters{display:flex;gap:12px;align-items:end;flex-wrap:wrap;background:white;padding:18px;border-radius:8px;margin-bottom:18px}
.filters label{display:flex;flex-direction:column;gap:6px;font-weight:bold}.filters select,.filters input{padding:9px;border:1px solid #cbd5e1;border-radius:5px;min-width:110px}
.btn{display:inline-block;background:#2563eb;color:white;border:0;border-radius:5px;padding:11px 15px;text-decoration:none;cursor:pointer;font:inherit}.muted{background:#64748b}.green{background:#0f766e}
.report{background:white;padding:22px;border-radius:8px;box-shadow:0 2px 10px #0001}.report-head{display:flex;justify-content:space-between;gap:15px;align-items:start;flex-wrap:wrap}.report h2{margin:0 0 8px}.summary{margin:12px 0 18px;color:#475569}.table-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:12px}th,td{padding:9px 8px;border-bottom:1px solid #e5e7eb;text-align:left;vertical-align:top}th{background:#e9eef5;white-space:nowrap}td.hours{text-align:right;white-space:nowrap}.print-only{display:none}.total{text-align:right;font-weight:bold;font-size:16px;padding-top:16px}.foot{font-size:11px;color:#64748b;margin-top:14px}
@media print{@page{size:A4 landscape;margin:9mm}body{background:white;color:black}.top,.filters,.no-print{display:none!important}.print-only{display:inline}.wrap{max-width:none;margin:0;padding:0}.report{box-shadow:none;padding:0}.report h2{font-size:17px}table{font-size:9px}th,td{padding:5px 4px}.table-wrap{overflow:visible}thead{display:table-header-group}tr{break-inside:avoid}.summary{font-size:10px;margin:8px 0}.foot{font-size:9px}}

/* Force the printed technician report to use the light theme. */
@media print {
  :root, html, html.vi-dark { color-scheme: light !important; }
  html.vi-dark body, body, html.vi-dark .wrap, .wrap,
  html.vi-dark .report, .report, html.vi-dark .section,
  html.vi-dark table, table, html.vi-dark .table-wrap, .table-wrap,
  html.vi-dark tbody, tbody, html.vi-dark tr, tr,
  html.vi-dark td, td {
    background: #fff !important;
    color: #1f2937 !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  html.vi-dark th, th {
    background: #e9eef5 !important;
    color: #1f2937 !important;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  html.vi-dark .summary, .summary,
  html.vi-dark .foot, .foot,
  html.vi-dark .total, .total,
  html.vi-dark .report h2, .report h2,
  html.vi-dark .report strong, .report strong {
    color: #1f2937 !important;
  }
  html.vi-dark th, html.vi-dark td, th, td {
    border-bottom: 1px solid #e5e7eb !important;
  }
  #vi-theme-toggle { display: none !important; }
}
@media print {
  html.vi-dark, html.vi-dark body { color-scheme: light !important; }
  html.vi-dark .report, html.vi-dark .wrap, html.vi-dark .table-wrap, html.vi-dark table,
  html.vi-dark .table-wrap tbody tr td, html.vi-dark .table-wrap tbody tr:hover td {
    background: #fff !important; color: #1f2937 !important;
  }
  html.vi-dark th { background: #e9eef5 !important; color: #1f2937 !important; }
  html.vi-dark .summary, html.vi-dark .foot { color: #475569 !important; }
}
</style>
</head>
<body>
<?php require __DIR__ . '/theme-ui.php'; ?>
<header class="top"><h1>VI Engineering — Technician Reports</h1></header>
<div class="wrap">
<form method="get" class="filters">
<label>Technician
<select name="technician"><option value="">All Technicians</option>
<?php foreach ($technicians as $tech): ?><option value="<?= esc($tech) ?>" <?= strcasecmp($tech, $selectedTech) === 0 ? 'selected' : '' ?>><?= esc($tech) ?></option><?php endforeach; ?>
</select></label>
<label>Start Date<input type="date" name="start_date" value="<?= esc($startValue) ?>" required onchange="refreshTechnicians(this.form)"></label>
<label>End Date<input type="date" name="end_date" value="<?= esc($endValue) ?>" required onchange="refreshTechnicians(this.form)"></label>
<button type="submit" class="btn">Generate Report</button>
<button type="button" class="btn green" onclick="window.print()">Print / Save PDF</button>
<a href="jobs.php" class="btn muted">← Back to Jobs</a>
</form>
<section class="report">
<div class="report-head"><div><h2><?= esc($reportTitle) ?> — <?= esc($selectedTech !== '' ? $selectedTech : 'All Technicians') ?></h2><strong>Visual Impact — Engineering Job Card Report</strong></div><div class="no-print">Jobs: <strong><?= count($rows) ?></strong></div></div>
<div class="summary">Jobs in selected date range: <strong><?= count($rows) ?></strong></div>
<div class="table-wrap"><table><thead><tr><th>Job Number</th><th>Client</th><th>Customer Telephone</th><th>Job Status</th><th>Job Type</th><th>Machine Type</th><th>Person</th><th>Date In</th><th style="text-align:right">Time Spent Hrs</th></tr></thead><tbody>
<?php foreach ($rows as $job): ?><tr>
<td><a class="no-print" href="job-card.php?id=<?= (int)$job['id'] ?>"><?= esc($job['legacy_job_number']) ?></a><span class="print-only"><?= esc($job['legacy_job_number']) ?></span></td>
<td><?= esc($job['customer_name']) ?></td><td><?= esc($job['customer_telephone']) ?></td><td><?= esc($job['status']) ?></td><td><?= esc($job['job_type_display']) ?></td><td><?= esc($job['machine_type']) ?></td><td><?= esc($job['technician']) ?></td><td><?= esc($job['date']->format('d/m/Y')) ?></td><td class="hours"><?= $job['hours'] > 0 ? esc(rtrim(rtrim(number_format($job['hours'], 2, '.', ''),'0'),'.')) : '' ?></td>
</tr><?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="9">No matching Job Cards found for this period.</td></tr><?php endif; ?>
</tbody></table></div>
<div class="total">Total Hrs: <?= esc(rtrim(rtrim(number_format($totalHours, 2, '.', ''),'0'),'.')) ?></div>
<p class="foot">Includes only Job Cards with Date In within the selected date range, regardless of job status. Blank or nonnumeric time values count as zero.</p>
</section></div>
<script>
function refreshTechnicians(form) {
    form.elements['technician'].value = '';
    form.submit();
}
</script>
<script>
// Print the actual light theme, then restore the user's on-screen theme.
(() => {
  let restoreDark = false;
  window.addEventListener('beforeprint', () => {
    restoreDark = document.documentElement.classList.contains('vi-dark');
    document.documentElement.classList.remove('vi-dark');
  });
  window.addEventListener('afterprint', () => {
    if (restoreDark) document.documentElement.classList.add('vi-dark');
    restoreDark = false;
  });
})();
</script>
</body></html>
