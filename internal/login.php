<?php
require_once __DIR__ . '/auth.php';

if (is_logged_in()) {
  header('Location: dashboard.php');
  exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $user = $_POST['username'] ?? '';
  $pass = $_POST['password'] ?? '';
  if (hash_equals(ADMIN_USER, $user) && password_verify($pass, ADMIN_PASSWORD_HASH)) {
    session_regenerate_id(true);
    $_SESSION['authed'] = true;
    header('Location: dashboard.php');
    exit;
  }
  $error = 'Identifiants incorrects.';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Connexion — bazzetv internal</title>
  <meta name="robots" content="noindex, nofollow">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <div class="login-wrap">
    <div class="login-card">
      <h1>bazzetv — internal</h1>
      <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <form method="post">
        <div class="field">
          <label for="username">Utilisateur</label>
          <input type="text" id="username" name="username" autocomplete="username" required>
        </div>
        <div class="field">
          <label for="password">Mot de passe</label>
          <input type="password" id="password" name="password" autocomplete="current-password" required>
        </div>
        <button type="submit" style="width:100%">Se connecter</button>
      </form>
    </div>
  </div>
</body>
</html>
