<?php
// Master relationships are one level deep. Only roots can be masters.
function masterId(PDO $pdo, int $id): int {
    $s=$pdo->prepare('SELECT id, master_client_id FROM clients WHERE id=?');
    $s->execute([$id]); $c=$s->fetch(PDO::FETCH_ASSOC);
    if (!$c) return 0;
    return (int)($c['master_client_id'] ?: $c['id']);
}
function masterClient(PDO $pdo, int $id): ?array {
    $root=masterId($pdo,$id);
    if (!$root) return null;
    $s=$pdo->prepare('SELECT * FROM clients WHERE id=?');$s->execute([$root]);
    return $s->fetch(PDO::FETCH_ASSOC) ?: null;
}
