<?php
require_once __DIR__ . '/auth.php';
require_login();
$activePage = 'clients';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Clients — bazzetv internal</title>
  <meta name="robots" content="noindex, nofollow">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php include '_nav.php'; ?>
<main>
  <div class="page-header">
    <h1>Base clients</h1>
    <button id="new-client-btn">+ Nouveau client</button>
  </div>
  <div id="clients-list"></div>
</main>

<div class="modal-backdrop" id="modal-backdrop" style="display:none">
  <div class="modal">
    <h2 id="modal-title">Nouveau client</h2>
    <form id="client-form">
      <input type="hidden" name="id">
      <div class="field">
        <label>Nom / Société *</label>
        <input type="text" name="name" required placeholder="Beauty Brands Investment…">
      </div>
      <div class="field">
        <label>Raison sociale (ligne 2)</label>
        <input type="text" name="company" placeholder="(International) Holdings Limited">
      </div>
      <div class="field">
        <label>Adresse de facturation</label>
        <textarea name="address" placeholder="Flat/Rm A, 12/F&#10;300 Lockhart Road, Wan Chai&#10;Hong Kong"></textarea>
      </div>
      <div class="field">
        <label>Email</label>
        <input type="email" name="email" placeholder="contact@brand.com">
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

<script>
(function () {
  const backdrop = document.getElementById('modal-backdrop');
  const modalTitle = document.getElementById('modal-title');
  const form = document.getElementById('client-form');
  const deleteBtn = document.getElementById('delete-btn');
  const list = document.getElementById('clients-list');
  let clients = [];

  async function api(action, body, method) {
    const opts = { method: method || 'POST', headers: { 'Content-Type': 'application/json' } };
    if (opts.method === 'POST') opts.body = JSON.stringify(body || {});
    const r = await fetch('api/clients.php?action=' + action, opts);
    return r.json();
  }

  function render() {
    if (!clients.length) {
      list.innerHTML = '<p style="color:var(--muted);margin-top:20px">Aucun client — ajoutez-en un avec le bouton +</p>';
      return;
    }
    list.innerHTML = `<table class="clients-table">
      <thead><tr><th>Nom</th><th>Raison sociale</th><th>Email</th><th>Adresse</th><th></th></tr></thead>
      <tbody>${clients.map(c => `
        <tr>
          <td><strong>${esc(c.name)}</strong></td>
          <td>${esc(c.company || '')}</td>
          <td>${esc(c.email || '')}</td>
          <td style="white-space:pre-line;font-size:12px">${esc(c.address || '')}</td>
          <td><button class="btn secondary edit-client" data-id="${c.id}" style="padding:4px 10px;font-size:12px">✎</button></td>
        </tr>`).join('')}
      </tbody>
    </table>`;
    list.querySelectorAll('.edit-client').forEach(btn => {
      btn.addEventListener('click', () => openModal(clients.find(c => c.id == btn.dataset.id)));
    });
  }

  function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;'); }

  function openModal(client) {
    form.reset();
    form.id.value = client ? client.id : '';
    modalTitle.textContent = client ? 'Modifier le client' : 'Nouveau client';
    deleteBtn.style.display = client ? '' : 'none';
    if (client) {
      form.name.value = client.name || '';
      form.company.value = client.company || '';
      form.address.value = client.address || '';
      form.email.value = client.email || '';
    }
    backdrop.style.display = 'flex';
  }

  function closeModal() { backdrop.style.display = 'none'; }

  async function load() {
    const data = await api('list', null, 'GET');
    clients = data.clients || [];
    render();
  }

  document.getElementById('new-client-btn').addEventListener('click', () => openModal(null));
  document.getElementById('cancel-btn').addEventListener('click', closeModal);
  backdrop.addEventListener('click', e => { if (e.target === backdrop) closeModal(); });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const payload = { name: form.name.value.trim(), company: form.company.value.trim(), address: form.address.value.trim(), email: form.email.value.trim() };
    if (form.id.value) { payload.id = Number(form.id.value); await api('update', payload); }
    else await api('create', payload);
    closeModal();
    await load();
  });

  deleteBtn.addEventListener('click', async () => {
    if (!form.id.value || !confirm('Supprimer ce client ?')) return;
    await api('delete', { id: Number(form.id.value) });
    closeModal();
    await load();
  });

  load();
})();
</script>
</body>
</html>
