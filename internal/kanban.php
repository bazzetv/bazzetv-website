<?php
require_once __DIR__ . '/auth.php';
require_login();

$stages = [
  'lead'        => 'À contacter',
  'negotiating' => 'Négociation',
  'confirmed'   => 'Accepté',
  'in_progress' => 'En cours',
  'delivered'   => 'Livré',
  'paid'        => 'Payé',
];
$stagesPairs = array_map(fn($k, $v) => [$k, $v], array_keys($stages), array_values($stages));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Kanban — bazzetv internal</title>
  <meta name="robots" content="noindex, nofollow">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <nav>
    <div class="brand">bazzetv internal</div>
    <div class="links">
      <a href="dashboard.php">Dashboard</a>
      <a href="kanban.php" class="active">Kanban</a>
      <a href="logout.php" class="logout">Déconnexion</a>
    </div>
  </nav>
  <main>
    <h1>Collaborations</h1>
    <div class="board-toolbar">
      <button id="new-card-btn">+ Nouvelle collaboration</button>
    </div>
    <div class="board" id="board" data-stages='<?= htmlspecialchars(json_encode($stagesPairs), ENT_QUOTES) ?>'></div>
  </main>

  <div class="modal-backdrop" id="modal-backdrop" style="display:none">
    <div class="modal">
      <h2 id="modal-title">Nouvelle collaboration</h2>
      <form id="card-form">
        <input type="hidden" name="id">
        <div class="field">
          <label>Marque / collab</label>
          <input type="text" name="brand" required>
        </div>
        <div class="field">
          <label>Contact</label>
          <input type="text" name="contact" placeholder="email, insta, discord...">
        </div>
        <div class="row2">
          <div class="field">
            <label>Deadline</label>
            <input type="date" name="deadline">
          </div>
          <div class="field">
            <label>Étape</label>
            <select name="stage">
              <?php foreach ($stages as $key => $label): ?>
                <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="row2">
          <div class="field">
            <label>Statut paiement</label>
            <select name="payment_status">
              <option value="unpaid">Non payé</option>
              <option value="pending">En attente</option>
              <option value="partial">Partiel</option>
              <option value="paid">Payé</option>
            </select>
          </div>
          <div class="field">
            <label>Montant</label>
            <input type="number" step="0.01" name="payment_amount" placeholder="0.00">
          </div>
        </div>
        <div class="field">
          <label>Notes</label>
          <textarea name="notes"></textarea>
        </div>
        <div class="actions">
          <button type="button" class="btn danger" id="delete-btn" style="display:none">Supprimer</button>
          <div style="flex:1"></div>
          <button type="button" class="btn secondary" id="cancel-btn">Annuler</button>
          <button type="submit">Enregistrer</button>
        </div>
      </form>
    </div>
  </div>

  <script src="assets/kanban.js"></script>
</body>
</html>
