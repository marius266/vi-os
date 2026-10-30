<?php
require __DIR__ . '/config/database.php';
require_once __DIR__ . '/auth.php';
require __DIR__ . '/auth-ui.php';

$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;

$where = '';
$params = [];

if ($search !== '') {
    $where = "WHERE
        legacy_job_number LIKE :s1 OR
        customer_name LIKE :s2 OR
        customer_telephone LIKE :s3 OR
        serial_number LIKE :s4 OR
        job_status LIKE :s5";

    $term = '%' . $search . '%';

    foreach (['s1', 's2', 's3', 's4', 's5'] as $key) {
        $params[$key] = $term;
    }
}

$countStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM job_cards $where"
);
$countStmt->execute($params);

$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$sql = "SELECT
            id,
            legacy_job_number,
            customer_name,
            customer_telephone,
            date_in_raw,
            machine_type,
            serial_number,
            job_status,
            priority
        FROM job_cards
        $where
        ORDER BY CAST(
            REGEXP_REPLACE(legacy_job_number, '[^0-9]', '')
            AS UNSIGNED
        ) DESC, id DESC
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);

foreach ($params as $key => $value) {
    $stmt->bindValue(':' . $key, $value);
}

$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

function e($value): string {
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function pageUrl(int $number, string $search): string {
    return '?' . http_build_query([
        'page' => $number,
        'search' => $search
    ]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>VI Engineering - Job Cards</title>

<style>
* { box-sizing: border-box; }

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f2f4f7;
    color: #222;
}

header {
    background: #172b4d;
    color: white;
    padding: 20px 30px;
}

header h1 { margin: 0; font-size: 24px; }

.container {
    max-width: 1500px;
    margin: 25px auto;
    padding: 0 20px;
}

.toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.search-form {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

input[type="search"] {
    width: 350px;
    max-width: 100%;
    padding: 11px;
    border: 1px solid #ccd2da;
    border-radius: 5px;
}

button, .btn {
    display: inline-block;
    padding: 11px 18px;
    border: 0;
    border-radius: 5px;
    background: #0066cc;
    color: white;
    text-decoration: none;
    cursor: pointer;
}

.btn-secondary { background: #64748b; }

.table-wrap {
    overflow-x: auto;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px #00000012;
}

table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

th, td {
    padding: 13px 12px;
    text-align: left;
    border-bottom: 1px solid #e7eaf0;
}

th {
    background: #e9eef5;
    white-space: nowrap;
}

tr:hover td { background: #f5f9ff; }

.status {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 5px;
    background: #e7edf5;
}

.pagination {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
    margin-top: 20px;
}

.pagination a {
    padding: 9px 14px;
    background: white;
    border: 1px solid #ccd2da;
    border-radius: 5px;
    text-decoration: none;
    color: #172b4d;
}

.pagination .disabled {
    opacity: .5;
}

@media (max-width: 600px) {
    input[type="search"] { width: 100%; }
    .search-form { width: 100%; }
}
.main-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    padding: 20px 30px;
    background: #172b4d;
}

.main-header h1 {
    margin: 0;
}

.header-buttons {
    display: flex;
    align-items: center;
    gap: 10px;
}

.header-buttons .btn {
    white-space: nowrap;
}

.btn-new {
    background: #16a34a;
}

.btn-clients {
    background: #2563eb;
}

@media (max-width: 650px) {
    .main-header {
        flex-wrap: wrap;
    }
}
</style>
</head>

<body>
<?php require __DIR__ . '/theme-ui.php'; ?>

<header class="main-header">
    <h1>VI Engineering — Job Cards</h1>

    <div class="header-buttons">
        <a href="new-job.php" class="btn btn-new">+ New Job Card</a>
        <a href="monthly-report.php" class="btn" style="background:#0f766e">Monthly Reports</a>
        <a href="clients.php" class="btn btn-clients">Clients</a>
    </div>
</header>

<div class="container">

    <div class="toolbar">
        <form method="get" class="search-form">
            <input
                type="search"
                name="search"
                placeholder="Search job, client, serial, status..."
                value="<?= e($search) ?>"
            >
            <button type="submit">Search</button>
            <a class="btn btn-secondary" href="jobs.php">Clear</a>
        </form>

        <div>
            <strong><?= number_format($total) ?></strong>
            job cards found
        </div>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Job No.</th>
                    <th>Date In</th>
                    <th>Client</th>
                    <th>Telephone</th>
                    <th>Machine</th>
                    <th>Serial No.</th>
                    <th>Status</th>
                    <th>Priority</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
            <?php foreach ($jobs as $job): ?>
                <tr>
                    <td><strong><?= e($job['legacy_job_number']) ?></strong></td>
                    <td><?= e($job['date_in_raw']) ?></td>
                    <td><?= e($job['customer_name']) ?></td>
                    <td><?= e($job['customer_telephone']) ?></td>
                    <td><?= e($job['machine_type']) ?></td>
                    <td><?= e($job['serial_number']) ?></td>
                    <td>
                        <span class="status">
                            <?= e($job['job_status']) ?>
                        </span>
                    </td>
                    <td><?= e($job['priority']) ?></td>
                    <td>
                        <a class="btn"
                           href="job-card.php?id=<?= (int)$job['id'] ?>">
                            Open
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>

            <?php if (!$jobs): ?>
                <tr>
                    <td colspan="9">No matching job cards found.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="<?= e(pageUrl($page - 1, $search)) ?>">Previous</a>
        <?php else: ?>
            <span class="disabled">Previous</span>
        <?php endif; ?>

        <span>
            Page <?= $page ?> of <?= $totalPages ?>
        </span>

        <?php if ($page < $totalPages): ?>
            <a href="<?= e(pageUrl($page + 1, $search)) ?>">Next</a>
        <?php else: ?>
            <span class="disabled">Next</span>
        <?php endif; ?>
    </div>

</div>
</body>
</html>
