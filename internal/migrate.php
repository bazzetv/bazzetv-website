<?php
require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/db.php';

$sqls = [
  "CREATE TABLE IF NOT EXISTS settings (
    `key` VARCHAR(100) PRIMARY KEY,
    value TEXT
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

  "CREATE TABLE IF NOT EXISTS clients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    company VARCHAR(255) DEFAULT NULL,
    address TEXT,
    email VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

// Add columns to kanban_cards only if they don't exist
$existingCols = db()->query('SHOW COLUMNS FROM kanban_cards')->fetchAll(PDO::FETCH_COLUMN);
$alters = [];
if (!in_array('client_id', $existingCols))           $alters[] = 'ADD COLUMN client_id INT UNSIGNED DEFAULT NULL';
if (!in_array('service_description', $existingCols)) $alters[] = 'ADD COLUMN service_description TEXT';
if (!in_array('invoice_number', $existingCols))      $alters[] = 'ADD COLUMN invoice_number VARCHAR(50) DEFAULT NULL';
if ($alters) {
  $sqls[] = 'ALTER TABLE kanban_cards ' . implode(', ', $alters);
}

$errors = [];
foreach ($sqls as $sql) {
  try { db()->exec($sql); } catch (Throwable $e) { $errors[] = $e->getMessage(); }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Migration — bazzetv internal</title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php include '_nav.php'; ?>
<main>
  <h1>Migration base de données</h1>
  <?php if ($errors): ?>
    <?php foreach ($errors as $e): ?>
      <p class="error">❌ <?= htmlspecialchars($e) ?></p>
    <?php endforeach; ?>
  <?php else: ?>
    <p style="color:var(--green);margin-bottom:16px">✅ Migration réussie — tables settings, clients créées et kanban_cards mis à jour.</p>
  <?php endif; ?>
  <a class="btn secondary" href="kanban.php">← Retour au Kanban</a>
</main>
</body>
</html>
