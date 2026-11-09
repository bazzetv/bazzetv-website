<?php
require_once __DIR__ . '/../auth.php';
require_login_api();
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  echo json_encode(['settings' => db()->query('SELECT `key`, value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR)]);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $body = json_decode(file_get_contents('php://input'), true) ?: [];
  $stmt = db()->prepare('INSERT INTO settings (`key`, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?');
  foreach ($body as $k => $v) {
    if (is_string($k)) $stmt->execute([$k, $v, $v]);
  }
  echo json_encode(['ok' => true]);
  exit;
}

http_response_code(405);
echo json_encode(['error' => 'method not allowed']);
