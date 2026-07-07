<?php
require_once __DIR__ . '/../auth.php';
require_login_api();
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

const STAGES = ['lead', 'negotiating', 'confirmed', 'in_progress', 'delivered', 'paid'];
const PAYMENT_STATUSES = ['unpaid', 'pending', 'partial', 'paid'];

function bad_request(string $msg) {
  http_response_code(400);
  echo json_encode(['error' => $msg]);
  exit;
}

function not_found() {
  http_response_code(404);
  echo json_encode(['error' => 'not found']);
  exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ($method === 'GET' ? 'list' : null);
$body = [];
if ($method === 'POST') {
  $raw = file_get_contents('php://input');
  $body = json_decode($raw, true) ?: [];
}

switch ($action) {
  case 'list':
    $cards = db()->query('SELECT * FROM kanban_cards ORDER BY stage, position, id')->fetchAll();
    echo json_encode(['cards' => $cards]);
    break;

  case 'create': {
    $brand = trim($body['brand'] ?? '');
    if ($brand === '') bad_request('brand is required');

    $stage = $body['stage'] ?? 'lead';
    if (!in_array($stage, STAGES, true)) bad_request('invalid stage');

    $paymentStatus = $body['payment_status'] ?? 'unpaid';
    if (!in_array($paymentStatus, PAYMENT_STATUSES, true)) bad_request('invalid payment_status');

    $maxPos = db()->prepare('SELECT COALESCE(MAX(position), -1) FROM kanban_cards WHERE stage = ?');
    $maxPos->execute([$stage]);
    $position = (int)$maxPos->fetchColumn() + 1;

    $stmt = db()->prepare('INSERT INTO kanban_cards (brand, contact, deadline, payment_status, payment_amount, currency, notes, stage, position)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
      $brand,
      $body['contact'] ?: null,
      $body['deadline'] ?: null,
      $paymentStatus,
      $body['payment_amount'] !== '' && $body['payment_amount'] !== null ? $body['payment_amount'] : null,
      $body['currency'] ?: 'EUR',
      $body['notes'] ?: null,
      $stage,
      $position,
    ]);
    $id = db()->lastInsertId();
    $card = db()->prepare('SELECT * FROM kanban_cards WHERE id = ?');
    $card->execute([$id]);
    echo json_encode(['card' => $card->fetch()]);
    break;
  }

  case 'update': {
    $id = (int)($body['id'] ?? 0);
    if (!$id) bad_request('id is required');

    $existing = db()->prepare('SELECT id FROM kanban_cards WHERE id = ?');
    $existing->execute([$id]);
    if (!$existing->fetch()) not_found();

    $fields = ['brand', 'contact', 'deadline', 'payment_status', 'payment_amount', 'currency', 'notes', 'stage'];
    $set = [];
    $params = [];
    foreach ($fields as $f) {
      if (array_key_exists($f, $body)) {
        if ($f === 'stage' && !in_array($body[$f], STAGES, true)) bad_request('invalid stage');
        if ($f === 'payment_status' && !in_array($body[$f], PAYMENT_STATUSES, true)) bad_request('invalid payment_status');
        $set[] = "$f = ?";
        $val = $body[$f];
        if (in_array($f, ['contact', 'deadline', 'notes', 'payment_amount'], true) && $val === '') $val = null;
        $params[] = $val;
      }
    }
    if (!$set) bad_request('no fields to update');
    $params[] = $id;
    db()->prepare('UPDATE kanban_cards SET ' . implode(', ', $set) . ' WHERE id = ?')->execute($params);

    $card = db()->prepare('SELECT * FROM kanban_cards WHERE id = ?');
    $card->execute([$id]);
    echo json_encode(['card' => $card->fetch()]);
    break;
  }

  case 'move': {
    $id = (int)($body['id'] ?? 0);
    $stage = $body['stage'] ?? '';
    if (!$id) bad_request('id is required');
    if (!in_array($stage, STAGES, true)) bad_request('invalid stage');

    $maxPos = db()->prepare('SELECT COALESCE(MAX(position), -1) FROM kanban_cards WHERE stage = ? AND id != ?');
    $maxPos->execute([$stage, $id]);
    $position = (int)$maxPos->fetchColumn() + 1;

    $stmt = db()->prepare('UPDATE kanban_cards SET stage = ?, position = ? WHERE id = ?');
    $stmt->execute([$stage, $position, $id]);

    $card = db()->prepare('SELECT * FROM kanban_cards WHERE id = ?');
    $card->execute([$id]);
    if (!$card->rowCount()) not_found();
    echo json_encode(['card' => $card->fetch()]);
    break;
  }

  case 'delete': {
    $id = (int)($body['id'] ?? 0);
    if (!$id) bad_request('id is required');
    db()->prepare('DELETE FROM kanban_cards WHERE id = ?')->execute([$id]);
    echo json_encode(['ok' => true]);
    break;
  }

  default:
    bad_request('unknown action');
}
