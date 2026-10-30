<?php
require __DIR__.'/client-common.php';
require __DIR__.'/client-master.php';
require_admin();
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT) ?: filter_var($_POST['id']??0,FILTER_VALIDATE_INT);
if (!$id) {http_response_code(400);exit('Invalid client');}
$root=masterId($pdo,(int)$id);
if (!$root) {http_response_code(404);exit('Client not found');}
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
 verifyClientPost();
 $action=(string)($_POST['action']??'');
 try {
  $pdo->beginTransaction();
  if ($action==='link') {
   $child=filter_var($_POST['child_id']??0,FILTER_VALIDATE_INT);
   if (!$child || $child===$root) throw new RuntimeException('Choose a different client');
   $s=$pdo->prepare('SELECT id,master_client_id FROM clients WHERE id IN (?,?) FOR UPDATE');$s->execute([$root,$child]);$found=[];foreach($s as $c)$found[(int)$c['id']]=$c;
   if(count($found)!==2 || !empty($found[$root]['master_client_id'])) throw new RuntimeException('Master is invalid');
   if (!empty($found[$child]['master_client_id'])) throw new RuntimeException('Client already linked; unlink it first');
   $s=$pdo->prepare('SELECT COUNT(*) FROM clients WHERE master_client_id=?');$s->execute([$child]);
   if((int)$s->fetchColumn()>0)throw new RuntimeException('This client is already a master; unlink its clients first');
   $s=$pdo->prepare('UPDATE clients SET master_client_id=? WHERE id=? AND master_client_id IS NULL');$s->execute([$root,$child]);
   if($s->rowCount()!==1)throw new RuntimeException('Unable to link client');
  } elseif ($action==='unlink') {
   $child=filter_var($_POST['child_id']??0,FILTER_VALIDATE_INT);
   $s=$pdo->prepare('UPDATE clients SET master_client_id=NULL WHERE id=? AND master_client_id=?');$s->execute([$child,$root]);
   if($s->rowCount()!==1)throw new RuntimeException('Client is not linked to this master');
  } else throw new RuntimeException('Invalid action');
  $pdo->commit();header('Location: client-master-manage.php?id='.$root.'&saved=1',true,303);exit;
 } catch(Throwable $e) {if($pdo->inTransaction())$pdo->rollBack();$error=$e->getMessage();}
}
$s=$pdo->prepare('SELECT id,client_name,telephone FROM clients WHERE id=?');$s->execute([$root]);$master=$s->fetch(PDO::FETCH_ASSOC);
$s=$pdo->prepare('SELECT id,client_name,telephone FROM clients WHERE master_client_id=? ORDER BY client_name');$s->execute([$root]);$linked=$s->fetchAll(PDO::FETCH_ASSOC);
$s=$pdo->prepare('SELECT id,client_name,telephone FROM clients WHERE id<>? AND master_client_id IS NULL AND id NOT IN (SELECT DISTINCT master_client_id FROM clients WHERE master_client_id IS NOT NULL) ORDER BY client_name');$s->execute([$root]);$candidates=$s->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Manage Linked Clients</title><style>body{font:15px Arial;background:#f4f6f8;color:#172033;padding:24px}.wrap{max-width:850px;margin:auto;background:white;padding:25px;border-radius:9px}button,select{padding:10px;margin:6px}button{background:#1d4ed8;color:white;border:0;border-radius:5px;cursor:pointer}li{margin:10px 0}.error{color:#b91c1c}</style></head><body>
<?php require __DIR__.'/theme-ui.php'; ?><div class="wrap"><a href="clients.php">← Clients</a><h1>Master: <?= h($master['client_name']) ?></h1><p>Client #<?= (int)$root ?> · <?= h($master['telephone']) ?></p><p>Linking does not delete records or change historical job IDs.</p>
<?php if($error):?><p class="error"><?=h($error)?></p><?php endif;?><?php if(isset($_GET['saved'])):?><p>Relationship updated.</p><?php endif;?>
<h2>Linked Clients (<?= count($linked) ?>)</h2><ul><?php foreach($linked as $c):?><li><?=h($c['client_name'])?> (#<?= (int)$c['id']?>) <form method="post" style="display:inline" onsubmit="return confirm('Unlink this client?')"><input type="hidden" name="csrf" value="<?=h($_SESSION['client_csrf'])?>"><input type="hidden" name="id" value="<?=$root?>"><input type="hidden" name="child_id" value="<?=(int)$c['id']?>"><button name="action" value="unlink">Unlink</button></form></li><?php endforeach;?></ul>
<h2>Link a duplicate</h2><form method="post" onsubmit="return confirm('Link this client to the selected master?')"><input type="hidden" name="csrf" value="<?=h($_SESSION['client_csrf'])?>"><input type="hidden" name="id" value="<?=$root?>"><select name="child_id" required><option value="">Select duplicate client</option><?php foreach($candidates as $c):?><option value="<?=(int)$c['id']?>"><?=h($c['client_name'].' — '.$c['telephone'].' (#'.$c['id'].')')?></option><?php endforeach;?></select><button name="action" value="link">Link to master</button></form><p><a href="client-history.php?id=<?=$root?>">Combined Job History</a></p></div></body></html>
