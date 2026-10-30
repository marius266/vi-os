<?php
// This endpoint is intended for trusted LOCAL WAMP development only.
require __DIR__ . '/config/database.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/parts-cart-server.php';


if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method not allowed'); }
if (!hash_equals($_SESSION['job_csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) { http_response_code(403); exit('Invalid form token'); }
$id = filter_var($_POST['job_id'] ?? null, FILTER_VALIDATE_INT);
if (!$id || $id < 1) { http_response_code(400); exit('Invalid job ID'); }
$fields = [
 'client'=>'CUSTOMER NAME', 'telephone'=>'CUSTOMER TELEPHONE',
 'cell'=>'CUSTOMER CELL', 'fax'=>'CUSTOMER FAX', 'email'=>'CUSTOMER EMAIL',
 'contact'=>'CUSTOMER CONTACT', 'order_number'=>'CUSTOMER ORDER Nº',
 'address'=>'CUSTOMER ADDRESS', 'client_vat'=>'CUSTOMER VAT',
 'warranty'=>'WARRANTY STATUS', 'date_in'=>'DATE IN', 'time_in'=>'TIME IN',
 'machine'=>'MACHINE TYPE', 'job_serial'=>'SERIAL Nº', 'job_type'=>'JOB TYPE',
 'model'=>'MODEL', 'problem'=>'FAULT', 'priority'=>'PRIORITY',
 'job_status'=>'JOB STATUS', 'out_for_repair'=>'OutForRepair',
 'repair_reference'=>'RepairRefNum', 'job_accessories'=>'ACTION TAKEN 2',
 'technician'=>'ACTION TAKEN 1', 'invoice_number'=>'Invoice',
 'work_date'=>'ACTION DATE', 'book_reference'=>'BOOK REF :',
 'time_spent'=>'TIME SPENT HRS', 'paid'=>'PAID', 'action'=>'ACTION TAKEN',
 'comments'=>'Comments', 'parts_description'=>'PARTS USED',
 'labour'=>'Labour', 'parts_cost'=>'Parts', 'subtotal'=>'Sub',
 'vat'=>'VAT', 'total'=>'Total'
];
function field(string $key, int $max = 200000): string {
 $v = $_POST[$key] ?? '';
 if (!is_string($v)) { http_response_code(400); exit('Invalid field'); }
 if (strlen($v) > $max) { http_response_code(400); exit('Field too long'); }
 return trim($v);
}
function money(string $v): string {
 if ($v === '') return '';
 if (!preg_match('/^-?\d{1,12}(?:\.\d{1,2})?$/', $v)) { http_response_code(400); exit('Invalid monetary value'); }
 return number_format((float)$v, 2, '.', '');
}
function dateVal(string $v): string {
 if ($v === '') return '';
 $d = DateTimeImmutable::createFromFormat('!Y-m-d', $v);
 if (!$d || $d->format('Y-m-d') !== $v) { http_response_code(400); exit('Invalid date'); }
 return $v;
}
try {
 $pdo->beginTransaction();
 $q = $pdo->prepare('SELECT * FROM job_cards WHERE id=? FOR UPDATE');
 $q->execute([$id]);
 $job = $q->fetch(PDO::FETCH_ASSOC);
 if (!$job) { $pdo->rollBack(); http_response_code(404); exit('Job not found'); }
 if (!hash_equals(hash('sha256', $job['raw_data']), field('record_hash', 100))) {
   $pdo->rollBack(); http_response_code(409); exit('This job has changed since it was opened. Reload before saving.');
 }
 $raw = json_decode($job['raw_data'], true, 512, JSON_THROW_ON_ERROR);
 if (!is_array($raw)) throw new RuntimeException('Invalid original record');
 $values = [];
 [$partItems, $partTotal, $partDescription] = viReadPartsCart();
 foreach ($fields as $input => $key) {
   $value = $input === 'out_for_repair' ? (isset($_POST[$input]) ? 'Yes' : 'No') : field($input);
   if (in_array($input, ['date_in','work_date'], true)) $value = dateVal($value);
   if (in_array($input, ['labour','parts_cost','subtotal','vat','total'], true)) $value = money($value);
   if ($input === 'parts_cost') $value = $partTotal;
   if ($input === 'parts_description') $value = $partDescription;
   $values[$input] = $value;
   // Do not erase unrelated original fields or duplicate action history.
   $raw[$key] = $value;
 }
 $raw['VI_PARTS_CART'] = $partItems;
 $labourAmount = (float)($values['labour'] ?: 0);
 $sub = round($labourAmount + (float)$partTotal, 2);
 $values['subtotal'] = number_format($sub,2,'.','');
 $values['vat'] = number_format(round($sub*.15,2),2,'.','');
 $values['total'] = number_format($sub+round($sub*.15,2),2,'.','');
 $raw['Sub']=$values['subtotal']; $raw['VAT']=$values['vat']; $raw['Total']=$values['total'];
 // Machine is now entered only once, under Job Sheet > Model.
 $values['machine'] = $values['model'];
 $raw['MACHINE TYPE'] = $values['model'];
 $json = json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
 $update = $pdo->prepare('UPDATE job_cards SET customer_name=?, customer_telephone=?, date_in_raw=?, time_in_raw=?, machine_type=?, serial_number=?, fault=?, job_status=?, job_type=?, priority=?, warranty_status=?, labour_raw=?, parts_raw=?, subtotal_raw=?, vat_raw=?, total_raw=?, raw_data=? WHERE id=?');
 $update->execute([
   $values['client'],$values['telephone'],$values['date_in'],$values['time_in'],
   $values['machine'],$values['job_serial'],$values['problem'],$values['job_status'],
   $values['job_type'],$values['priority'],$values['warranty'],$values['labour'],
   $values['parts_cost'],$values['subtotal'],$values['vat'],$values['total'],$json,$id
 ]);
 $pdo->commit();
 header('Location: job-card.php?id='.$id.'&saved=1', true, 303);
 exit;
} catch (Throwable $e) {
 if ($pdo->inTransaction()) $pdo->rollBack();
 error_log('Job save error: '.$e->getMessage());
 http_response_code(500); exit('Unable to save job. Check PHP error log.');
}
