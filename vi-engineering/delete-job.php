<?php
// Local development only: add authentication and role checks before deployment.
require __DIR__ . '/config/database.php';
require_once __DIR__ . '/auth.php';
require_admin();

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method not allowed'); }
$expected = (string)($_SESSION['job_csrf'] ?? '');
$provided = $_POST['csrf'] ?? null;
if ($expected === '' || !is_string($provided) || !hash_equals($expected, $provided)) {
    http_response_code(403); exit('Invalid form token');
}
$id = filter_var($_POST['job_id'] ?? null, FILTER_VALIDATE_INT);
if (!$id || $id < 1) { http_response_code(400); exit('Invalid job ID'); }
$jobNumber = $_POST['job_number'] ?? null;
if (!is_string($jobNumber) || $jobNumber === '') { http_response_code(400); exit('Invalid job number'); }
try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT legacy_job_number FROM job_cards WHERE id = ? FOR UPDATE');
    $stmt->execute([$id]);
    $job = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$job) { $pdo->rollBack(); http_response_code(404); exit('Job not found'); }
    if (!hash_equals((string)$job['legacy_job_number'], $jobNumber)) {
        $pdo->rollBack(); http_response_code(409); exit('Job number mismatch. No record deleted.');
    }
    $stmt = $pdo->prepare('DELETE FROM job_cards WHERE id = ?');
    $stmt->execute([$id]);
    $pdo->commit();
    header('Location: jobs.php?deleted=1', true, 303);
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Job deletion error: ' . $e->getMessage());
    http_response_code(500); exit('Unable to delete job. Check PHP error log.');
}
