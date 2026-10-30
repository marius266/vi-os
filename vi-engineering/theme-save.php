<?php
require __DIR__ . '/config/database.php';
require_once __DIR__ . '/auth.php';
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit(json_encode(['error'=>'Method not allowed'])); }
if (!hash_equals((string)($_SESSION['theme_csrf'] ?? ''), (string)($_POST['csrf'] ?? ''))) { http_response_code(403); exit(json_encode(['error'=>'Invalid token'])); }
$theme = (string)($_POST['theme'] ?? '');
if (!in_array($theme, ['light','dark'], true)) { http_response_code(400); exit(json_encode(['error'=>'Invalid theme'])); }
try {
    $q = $pdo->prepare('INSERT INTO user_preferences (user_id, theme) VALUES (?, ?) ON DUPLICATE KEY UPDATE theme=VALUES(theme)');
    $q->execute([(int)current_user()['id'], $theme]);
    echo json_encode(['ok'=>true,'theme'=>$theme]);
} catch (Throwable $e) { error_log('Theme preference: '.$e->getMessage()); http_response_code(500); echo json_encode(['error'=>'Could not save preference']); }
