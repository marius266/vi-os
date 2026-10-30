<?php
// Local WAMP development: require authentication/roles before public deployment.
require __DIR__ . '/config/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/parts-cart-server.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method not allowed'); }
$token = $_POST['csrf'] ?? null;
if (!is_string($token) || empty($_SESSION['job_csrf']) || !hash_equals($_SESSION['job_csrf'], $token)) { http_response_code(403); exit('Invalid form token'); }
$clientId = filter_var($_POST['client_id'] ?? null, FILTER_VALIDATE_INT);
if (!$clientId || $clientId < 1) { http_response_code(400); exit('Select a client'); }
$fields = ["client" => "CUSTOMER NAME", "telephone" => "CUSTOMER TELEPHONE", "cell" => "CUSTOMER CELL", "fax" => "CUSTOMER FAX", "email" => "CUSTOMER EMAIL", "contact" => "CUSTOMER CONTACT", "order_number" => "CUSTOMER ORDER Nº", "address" => "CUSTOMER ADDRESS", "client_vat" => "CUSTOMER VAT", "warranty" => "WARRANTY STATUS", "date_in" => "DATE IN", "time_in" => "TIME IN", "machine" => "MACHINE TYPE", "job_serial" => "SERIAL Nº", "job_type" => "JOB TYPE", "model" => "MODEL", "problem" => "FAULT", "priority" => "PRIORITY", "job_status" => "JOB STATUS", "out_for_repair" => "OutForRepair", "repair_reference" => "RepairRefNum", "job_accessories" => "ACTION TAKEN 2", "technician" => "ACTION TAKEN 1", "invoice_number" => "Invoice", "work_date" => "ACTION DATE", "time_spent" => "TIME SPENT HRS", "paid" => "PAID", "action" => "ACTION TAKEN", "parts_description" => "PARTS USED", "labour" => "Labour", "parts_cost" => "Parts", "subtotal" => "Sub", "vat" => "VAT", "total" => "Total"];
function value(string $name, int $max = 200000): string {
    $v = $_POST[$name] ?? '';
    if (!is_string($v) || strlen($v) > $max) { http_response_code(400); exit('Invalid field: '.$name); }
    return trim($v);
}
function validDate(string $v): string {
    if ($v === '') return '';
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $v);
    if (!$d || $d->format('Y-m-d') !== $v) { http_response_code(400); exit('Invalid date'); }
    return $v;
}
$locked = false;
try {
    // A named database lock serializes job-number allocation across simultaneous new-job requests.
    $q = $pdo->query("SELECT GET_LOCK('vi_engineering_new_job_number', 10)");
    if ((int)$q->fetchColumn() !== 1) throw new RuntimeException('Could not reserve job-number lock');
    $locked = true;
    $pdo->beginTransaction();
    $q = $pdo->prepare('SELECT id, client_name, telephone, full_address, vat_number, raw_data FROM clients WHERE id = ?');
    $q->execute([$clientId]);
    $client = $q->fetch(PDO::FETCH_ASSOC);
    if ($client) {
        $m=$pdo->prepare('SELECT master_client_id FROM clients WHERE id=?');$m->execute([$clientId]);
        $masterId=$m->fetchColumn();
        if ($masterId) {
            $clientId=(int)$masterId;
            $q->execute([$clientId]);$client=$q->fetch(PDO::FETCH_ASSOC);
        }
    }
    if (!$client) { http_response_code(400); throw new RuntimeException('Client not found'); }
    $q = $pdo->query("SELECT COALESCE(MAX(CAST(legacy_job_number AS UNSIGNED)),0) FROM job_cards WHERE legacy_job_number REGEXP '^[0-9]+$'");
    $next = (int)$q->fetchColumn() + 1;
    [$partItems, $partTotal, $partDescription] = viReadPartsCart();
    $raw = [];
    $values = [];
    foreach ($fields as $input => $fm) {
        $v = $input === 'out_for_repair' ? (isset($_POST[$input]) ? 'Yes' : 'No') : value($input);
        if (in_array($input, ['date_in','work_date'], true)) $v = validDate($v);
        if (in_array($input, ['labour','parts_cost'], true) && $v !== '' && !preg_match('/^\d{1,12}(?:\.\d{1,2})?$/', $v)) { http_response_code(400); throw new RuntimeException('Invalid amount'); }
        if ($input === 'technician') {
            $v = trim((string)(current_user()['username'] ?? ''));
            if ($v === '') { http_response_code(403); throw new RuntimeException('Technician login required'); }
        }
        if ($input === 'parts_cost') $v = $partTotal;
        if ($input === 'parts_description') $v = $partDescription;
        $values[$input] = $v;
        $raw[$fm] = $v;
    }
    $raw['VI_PARTS_CART'] = $partItems;
    // Server-calculated amounts; never trust browser totals.
    $labour = (float)($values['labour'] ?: 0);
    $parts = (float)($values['parts_cost'] ?: 0);
    $subtotal = round($labour+$parts,2);
    $vat = round($subtotal*.15,2);
    $total = round($subtotal+$vat,2);
    $raw['Sub'] = number_format($subtotal,2,'.','');
    $raw['VAT'] = number_format($vat,2,'.','');
    $raw['Total'] = number_format($total,2,'.','');
    $raw['JOB NUMBER'] = (string)$next;
    $raw['CUSTOMER NAME'] = (string)$client['client_name'];
    $raw['CUSTOMER TELEPHONE'] = (string)$client['telephone'];
    $raw['MACHINE TYPE'] = $values['machine'] !== '' ? $values['machine'] : $values['model'];
    $raw['MODEL'] = $raw['MACHINE TYPE'];
    $raw['SERIAL Nº'] = $values['job_serial'];
    $raw['JOB STATUS'] = $values['job_status'] ?: 'Open';
    $json = json_encode($raw, JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);
    $stmt = $pdo->prepare('INSERT INTO job_cards (migration_row, legacy_job_number, client_id, customer_name, customer_telephone, date_in_raw, time_in_raw, machine_type, serial_number, fault, job_status, job_type, priority, warranty_status, labour_raw, parts_raw, subtotal_raw, vat_raw, total_raw, raw_data) VALUES (NULL,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([(string)$next, $clientId, $client['client_name'], $client['telephone'], $values['date_in'], $values['time_in'], $raw['MACHINE TYPE'], $values['job_serial'], $values['problem'], $raw['JOB STATUS'], $values['job_type'], $values['priority'], $values['warranty'], number_format($labour,2,'.',''), number_format($parts,2,'.',''), $raw['Sub'], $raw['VAT'], $raw['Total'], $json]);
    $id = (int)$pdo->lastInsertId();
    $pdo->commit();
    $pdo->query("SELECT RELEASE_LOCK('vi_engineering_new_job_number')");
    header('Location: job-card.php?id='.$id.'&created=1', true, 303);
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($locked) { try { $pdo->query("SELECT RELEASE_LOCK('vi_engineering_new_job_number')"); } catch (Throwable $ignored) {} }
    error_log('New job error: '.$e->getMessage());
    http_response_code(500); exit('Could not create job. Check PHP error log.');
}
