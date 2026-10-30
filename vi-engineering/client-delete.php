<?php
require __DIR__ . '/client-common.php';

require_admin();verifyClientPost();
$id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT);if(!$id){http_response_code(400);exit('Invalid client ID');}
try{
 $pdo->beginTransaction();
 $s=$pdo->prepare('SELECT id,telephone FROM clients WHERE id=? FOR UPDATE');$s->execute([$id]);$c=$s->fetch(PDO::FETCH_ASSOC);if(!$c){$pdo->rollBack();http_response_code(404);exit('Client not found');}
 $s=$pdo->prepare('SELECT COUNT(*) FROM job_cards WHERE client_id=?');$s->execute([$id]);$linked=(int)$s->fetchColumn();
 // Imported jobs may not have a client_id. Be conservative when the telephone matches.
 $phone=trim((string)$c['telephone']);
 if($phone!==''){$s=$pdo->prepare('SELECT COUNT(*) FROM job_cards WHERE TRIM(customer_telephone)=?');$s->execute([$phone]);$linked+=(int)$s->fetchColumn();}
 if($linked){$pdo->rollBack();http_response_code(409);exit('Cannot delete: this client has linked or matching job cards. Preserve the history or reassign those jobs first.');}
 $s=$pdo->prepare('SELECT COUNT(*) FROM clients WHERE master_client_id=?');$s->execute([$id]);
 if ((int)$s->fetchColumn()>0) {$pdo->rollBack();http_response_code(409);exit('Cannot delete a master with linked clients. Unlink them first.');}
 $s=$pdo->prepare('DELETE FROM clients WHERE id=?');$s->execute([$id]);$pdo->commit();header('Location: clients.php?deleted=1',true,303);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('Client delete: '.$e->getMessage());http_response_code(500);exit('Could not delete client. Check PHP error log.');}
