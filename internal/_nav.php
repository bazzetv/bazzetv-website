<?php $activePage ??= ''; ?>
<nav>
  <div class="nav-left">
    <div class="avatar-wrap" id="avatar-wrap">
      <img src="../avatar.jpg" class="nav-avatar" id="avatar-btn" alt="Menu">
      <div class="avatar-dropdown" id="avatar-dropdown">
        <a href="profile.php">⚙ Profil</a>
      </div>
    </div>
    <span class="brand">bazzetv internal</span>
  </div>
  <div class="links">
    <a href="dashboard.php" <?= $activePage === 'dashboard' ? 'class="active"' : '' ?>>Dashboard</a>
    <a href="kanban.php" <?= $activePage === 'kanban' ? 'class="active"' : '' ?>>Kanban</a>
    <a href="clients.php" <?= $activePage === 'clients' ? 'class="active"' : '' ?>>Clients</a>
    <a href="logout.php" class="logout">Déconnexion</a>
  </div>
</nav>
<script>
(function () {
  const btn = document.getElementById('avatar-btn');
  const dd  = document.getElementById('avatar-dropdown');
  btn.addEventListener('click', function (e) { e.stopPropagation(); dd.classList.toggle('open'); });
  document.addEventListener('click', function () { dd.classList.remove('open'); });
})();
</script>
