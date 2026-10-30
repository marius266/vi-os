<?php
// CLI only: C:\wamp64\bin\php\php8.5.0\php.exe create-user.php admin admin
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if ($argc !== 3 || !preg_match('/^[A-Za-z0-9_.-]{3,80}$/', $argv[1]) || !in_array($argv[2], ['admin','staff'], true)) { exit("Usage: php create-user.php USERNAME admin|staff\n"); }
require __DIR__ . '/config/database.php';
echo "Password (input will be visible in this terminal): ";
$password = rtrim((string)fgets(STDIN), "\r\n");
if (strlen($password) < 12) exit("Password must be at least 12 characters.\n");
$hash = password_hash($password, PASSWORD_DEFAULT);
$q = $pdo->prepare('INSERT INTO app_users (username,password_hash,role) VALUES (?,?,?)');
try { $q->execute([$argv[1],$hash,$argv[2]]); echo "User created.\n"; }
catch (PDOException $e) { exit("Could not create user. Check the SQL tables and that username is unique.\n"); }
