<?php
require __DIR__ . '/config/database.php';
require_once __DIR__ . '/auth.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['client_csrf'])) $_SESSION['client_csrf'] = bin2hex(random_bytes(32));
function h($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function verifyClientPost(): void {
 if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method not allowed'); }
 $token = $_POST['csrf'] ?? null;
 if (!is_string($token) || !hash_equals($_SESSION['client_csrf'] ?? '', $token)) { http_response_code(403); exit('Invalid form token'); }
}
function clientText(string $key, int $limit=5000): string {
 $v = $_POST[$key] ?? '';
 if (!is_string($v) || strlen($v)>$limit) { http_response_code(400); exit('Invalid field'); }
 return trim($v);
}
