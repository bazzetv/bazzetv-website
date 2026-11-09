<?php
require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/db.php';

$cardId = (int)($_GET['card_id'] ?? 0);
if (!$cardId) { header('Location: kanban.php'); exit; }

// Load card with client
$stmt = db()->prepare('
  SELECT k.*, c.name as client_name, c.company as client_company, c.address as client_address, c.email as client_email
  FROM kanban_cards k
  LEFT JOIN clients c ON c.id = k.client_id
  WHERE k.id = ?
');
$stmt->execute([$cardId]);
$card = $stmt->fetch();
if (!$card) { header('Location: kanban.php'); exit; }

// Auto-assign invoice number if not set
if (!$card['invoice_number']) {
  $year = date('Y');
  $key  = "invoice_last_num_$year";
  $s = db()->prepare('SELECT value FROM settings WHERE `key` = ?');
  $s->execute([$key]);
  $last = (int)($s->fetchColumn() ?: 0);
  $next = $last + 1;
  $invNum = 'BTV-' . $year . '-' . str_pad($next, 4, '0', STR_PAD_LEFT);
  db()->prepare('INSERT INTO settings (`key`, value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=?')->execute([$key, $next, $next]);
  db()->prepare('UPDATE kanban_cards SET invoice_number=? WHERE id=?')->execute([$invNum, $cardId]);
  $card['invoice_number'] = $invNum;
}

// Load profile settings
$settings = db()->query('SELECT `key`, value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
$s = function(string $key, string $fallback = '') use ($settings): string {
  return htmlspecialchars($settings[$key] ?? $fallback);
};

$invoiceDate = date('F j, Y');
$dueDate     = $card['deadline'] ? date('F j, Y', strtotime($card['deadline'])) : 'Upon receipt';
$amount      = $card['payment_amount'] !== null ? number_format((float)$card['payment_amount'], 2) : '0.00';
$currency    = $card['currency'] ?: 'EUR';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Invoice <?= htmlspecialchars($card['invoice_number']) ?></title>
  <meta name="robots" content="noindex, nofollow">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Helvetica Neue', Arial, sans-serif; font-size: 14px; color: #1a1a2e; background: #fff; }
    .page { max-width: 760px; margin: 0 auto; padding: 48px 48px 60px; }

    /* print button — hidden when printing */
    .print-bar { display: flex; gap: 10px; margin-bottom: 32px; }
    .print-bar button { cursor: pointer; padding: 8px 20px; border: none; border-radius: 6px; font-size: 14px; font-weight: 600; }
    .print-bar .btn-print { background: #6c47ff; color: #fff; }
    .print-bar .btn-back  { background: #f0f0f4; color: #333; }
    @media print { .print-bar { display: none; } }

    h1.invoice-title { font-size: 42px; font-weight: 900; letter-spacing: -1px; color: #1a1a2e; margin-bottom: 4px; }
    .invoice-meta { font-size: 13px; line-height: 1.8; }
    .invoice-meta strong { font-weight: 600; }

    .header-row { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; }

    .parties { display: grid; grid-template-columns: 1fr 1fr; gap: 32px; margin-bottom: 40px; }
    .party h3 { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #888; margin-bottom: 8px; }
    .party p { font-size: 13px; line-height: 1.7; white-space: pre-line; }

    table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
    thead th { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; padding: 10px 12px; background: #f4f4f8; border: 1px solid #e0e0e8; text-align: left; }
    thead th:last-child { text-align: right; }
    tbody td { padding: 14px 12px; border: 1px solid #e0e0e8; vertical-align: top; font-size: 13px; line-height: 1.6; }
    tbody td:last-child { text-align: right; white-space: nowrap; font-weight: 600; }
    tfoot td { padding: 12px; font-weight: 700; font-size: 15px; text-align: right; border-top: 2px solid #1a1a2e; }

    .vat-note { font-size: 12px; font-style: italic; color: #666; margin-bottom: 32px; }

    .payment-section { background: #f8f8fc; border: 1px solid #e0e0e8; border-radius: 8px; padding: 20px 24px; margin-bottom: 32px; }
    .payment-section h3 { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #888; margin-bottom: 14px; }
    .payment-row { display: flex; gap: 8px; margin-bottom: 6px; font-size: 13px; }
    .payment-row .label { font-weight: 700; min-width: 160px; }

    .thank-you { font-size: 13px; color: #555; margin-top: 20px; }
  </style>
</head>
<body>
<div class="page">
  <div class="print-bar">
    <button class="btn-print" onclick="window.print()">🖨 Print / Save PDF</button>
    <button class="btn-back" onclick="window.close()">✕ Fermer</button>
  </div>

  <div class="header-row">
    <h1 class="invoice-title">INVOICE</h1>
    <div class="invoice-meta">
      <strong>Invoice number:</strong> <?= htmlspecialchars($card['invoice_number']) ?><br>
      <strong>Invoice date:</strong> <?= $invoiceDate ?><br>
      <strong>Due date:</strong> <?= $dueDate ?>
    </div>
  </div>

  <div class="parties">
    <div class="party">
      <h3>From</h3>
      <p><?= $s('profile_name', 'BazzeTV') ?>
<?php if ($settings['profile_address'] ?? ''): ?>
<?= $s('profile_address') ?>
<?php endif; ?><?php if ($settings['profile_email'] ?? ''): ?>
<?= $s('profile_email') ?><?php endif; ?></p>
    </div>
    <div class="party">
      <h3>Bill to</h3>
      <p><?= htmlspecialchars($card['client_name'] ?? '—') ?>
<?php if ($card['client_company']): ?>
<?= htmlspecialchars($card['client_company']) ?>
<?php endif; ?><?php if ($card['client_address']): ?>
<?= htmlspecialchars($card['client_address']) ?>
<?php endif; ?><?php if ($card['client_email']): ?>
<?= htmlspecialchars($card['client_email']) ?><?php endif; ?></p>
    </div>
  </div>

  <table>
    <thead>
      <tr><th>Description</th><th>Qty</th><th>Amount</th></tr>
    </thead>
    <tbody>
      <tr>
        <td><?= nl2br(htmlspecialchars($card['service_description'] ?: $card['brand'])) ?></td>
        <td>1</td>
        <td><?= $currency === 'EUR' ? '€' : '$' ?><?= $amount ?></td>
      </tr>
    </tbody>
    <tfoot>
      <tr><td colspan="2">Total due</td><td><?= $currency === 'EUR' ? '€' : '$' ?><?= $amount ?> (<?= htmlspecialchars($currency) ?>)</td></tr>
    </tfoot>
  </table>

  <?php if ($settings['profile_vat_note'] ?? ''): ?>
    <p class="vat-note"><?= $s('profile_vat_note') ?></p>
  <?php endif; ?>

  <?php if ($settings['profile_iban'] ?? ''): ?>
  <div class="payment-section">
    <h3>Payment details</h3>
    <?php if ($settings['profile_account_name'] ?? ''): ?>
      <div class="payment-row"><span class="label">Account name</span><span><?= $s('profile_account_name') ?></span></div>
    <?php endif; ?>
    <div class="payment-row"><span class="label">IBAN</span><span><?= $s('profile_iban') ?></span></div>
    <?php if ($settings['profile_bic'] ?? ''): ?>
      <div class="payment-row"><span class="label">BIC / SWIFT</span><span><?= $s('profile_bic') ?></span></div>
    <?php endif; ?>
    <?php if ($settings['profile_bank_name'] ?? ''): ?>
      <div class="payment-row"><span class="label">Bank name</span><span><?= $s('profile_bank_name') ?></span></div>
    <?php endif; ?>
    <?php if ($settings['profile_bank_address'] ?? ''): ?>
      <div class="payment-row"><span class="label">Bank address</span><span><?= $s('profile_bank_address') ?></span></div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <p class="thank-you">Thank you for your business!</p>
</div>
</body>
</html>
