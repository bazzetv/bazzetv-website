<?php
require_once __DIR__ . '/auth.php';
require_login();
$activePage = 'profile';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Profil — bazzetv internal</title>
  <meta name="robots" content="noindex, nofollow">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php include '_nav.php'; ?>
<main>
  <div class="page-header">
    <h1>Profil &amp; Infos de paiement</h1>
  </div>
  <div class="form-card">
    <p class="form-hint">Ces infos apparaissent dans le bloc <strong>From</strong> et <strong>Payment details</strong> de chaque facture.</p>
    <form id="profile-form">
      <div class="field">
        <label>Nom (facture)</label>
        <input type="text" name="profile_name" placeholder="Issam Bazze – BazzeTV">
      </div>
      <div class="field">
        <label>Adresse</label>
        <textarea name="profile_address" placeholder="2 rue des Vignes&#10;67270 Kienheim, France"></textarea>
      </div>
      <div class="row2">
        <div class="field">
          <label>Email</label>
          <input type="email" name="profile_email" placeholder="contact@bazzetv.com">
        </div>
        <div class="field">
          <label>Téléphone</label>
          <input type="text" name="profile_phone" placeholder="+33 7 67 31 10 81">
        </div>
      </div>
      <div class="field">
        <label>Nom du compte bancaire</label>
        <input type="text" name="profile_account_name" placeholder="Mohamed Issam Bazze">
      </div>
      <div class="row2">
        <div class="field">
          <label>IBAN</label>
          <input type="text" name="profile_iban" placeholder="FR76 2823 3000 …">
        </div>
        <div class="field">
          <label>BIC / SWIFT</label>
          <input type="text" name="profile_bic" placeholder="REVOFRP2">
        </div>
      </div>
      <div class="row2">
        <div class="field">
          <label>Banque</label>
          <input type="text" name="profile_bank_name" placeholder="Revolut Bank UAB">
        </div>
        <div class="field">
          <label>Adresse banque</label>
          <input type="text" name="profile_bank_address" placeholder="10 avenue Kléber, 75116 Paris">
        </div>
      </div>
      <div class="field">
        <label>Note TVA</label>
        <input type="text" name="profile_vat_note" placeholder="VAT not applicable – freelancer in France, not VAT-registered.">
      </div>
      <div class="actions" style="margin-top:20px">
        <button type="submit" id="save-btn">Enregistrer</button>
        <span id="save-status" style="font-size:13px;color:var(--green);display:none">✓ Enregistré</span>
      </div>
    </form>
  </div>
</main>
<script>
(function () {
  const form = document.getElementById('profile-form');
  const status = document.getElementById('save-status');

  async function api(method, body) {
    const opts = { method, headers: { 'Content-Type': 'application/json' } };
    if (body) opts.body = JSON.stringify(body);
    const r = await fetch('api/settings.php', opts);
    return r.json();
  }

  api('GET').then(({ settings }) => {
    for (const [k, v] of Object.entries(settings || {})) {
      const el = form.elements[k];
      if (el) el.value = v;
    }
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const payload = {};
    for (const el of form.elements) {
      if (el.name) payload[el.name] = el.value;
    }
    await api('POST', payload);
    status.style.display = '';
    setTimeout(() => { status.style.display = 'none'; }, 2000);
  });
})();
</script>
</body>
</html>
