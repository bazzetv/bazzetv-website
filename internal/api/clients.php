<?php
require_once __DIR__ . '/../auth.php';
require_login_api();
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ($method === 'GET' ? 'list' : null);
$body   = $method === 'POST' ? (json_decode(file_get_contents('php://input'), true) ?: []) : [];

function bad_req(string $msg) { http_response_code(400); echo json_encode(['error' => $msg]); exit; }

switch ($action) {
  case 'list':
    echo json_encode(['clients' => db()->query('SELECT * FROM clients ORDER BY name')->fetchAll()]);
    break;

  case 'create': {
    $name = trim($body['name'] ?? '');
    if (!$name) bad_req('name required');
    $stmt = db()->prepare('INSERT INTO clients (name, company, address, email) VALUES (?, ?, ?, ?)');
    $stmt->execute([$name, $body['company'] ?: null, $body['address'] ?: null, $body['email'] ?: null]);
    $id = db()->lastInsertId();
    $row = db()->prepare('SELECT * FROM clients WHERE id = ?');
    $row->execute([$id]);
    echo json_encode(['client' => $row->fetch()]);
    break;
  }

  case 'update': {
    $id = (int)($body['id'] ?? 0);
    if (!$id) bad_req('id required');
    $stmt = db()->prepare('UPDATE clients SET name=?, company=?, address=?, email=? WHERE id=?');
    $stmt->execute([
      trim($body['name'] ?? ''),
      $body['company'] ?: null,
      $body['address'] ?: null,
      $body['email'] ?: null,
      $id,
    ]);
    echo json_encode(['ok' => true]);
    break;
  }

  case 'delete': {
    $id = (int)($body['id'] ?? 0);
    if (!$id) bad_req('id required');
    db()->prepare('DELETE FROM clients WHERE id = ?')->execute([$id]);
    echo json_encode(['ok' => true]);
    break;
  }

  default: bad_req('unknown action');
}
