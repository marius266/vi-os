<?php
require __DIR__ . '/client-common.php';verifyClientPost();
$id=filter_var($_POST['id']??0,FILTER_VALIDATE_INT) ?: 0;
$name=clientText('client_name',255);if($name===''){http_response_code(400);exit('Client name required');}
$phone=clientText('telephone',255);$addr=clientText('full_address',10000);$vat=clientText('vat_number',255);
$extras=['Cell Phone 1'=>clientText('cell',255),'Fax'=>clientText('fax',255),'Internet Addr'=>clientText('email',255),'Contact'=>clientText('contact',255)];
try {
 $pdo->beginTransaction();
 if($id){$s=$pdo->prepare('SELECT raw_data FROM clients WHERE id=? FOR UPDATE');$s->execute([$id]);$old=$s->fetchColumn();if($old===false){throw new RuntimeException('Client not found');}$raw=json_decode($old,true);if(!is_array($raw))$raw=[];}
 else $raw=[];
 foreach($extras as $k=>$v)$raw[$k]=$v;
 $raw['Client']=$name;$raw['Tel:']=$phone;$raw['VAT#']=$vat;
 $json=json_encode($raw,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);
 if($id){$s=$pdo->prepare('UPDATE clients SET client_name=?,telephone=?,full_address=?,vat_number=?,raw_data=? WHERE id=?');$s->execute([$name,$phone,$addr,$vat,$json,$id]);}
 else {$s=$pdo->prepare('INSERT INTO clients (client_name,telephone,full_address,vat_number,raw_data) VALUES (?,?,?,?,?)');$s->execute([$name,$phone,$addr,$vat,$json]);$id=(int)$pdo->lastInsertId();}
 $pdo->commit();header('Location: client-edit.php?id='.$id.'&saved=1',true,303);
} catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('Client save: '.$e->getMessage());http_response_code(500);exit('Could not save client. Check PHP error log and database schema.');}
