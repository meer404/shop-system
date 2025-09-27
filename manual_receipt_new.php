<?php
// manual_receipt_new.php
// Create a manual receipt (saved to DB), then redirect to printable page.
// Uses your existing styles.css + receipt.css for UI.

if (session_status() === PHP_SESSION_NONE) session_start();

// Adjust if your config path differs:
require_once __DIR__ . '/inc/config.php'; // should set $pdo = new PDO(...)

function safe($v){ return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

$success = $_GET['success'] ?? '';
$error   = $_GET['error'] ?? '';

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>New Manual Receipt</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="styles.css?v=13" rel="stylesheet">
  <link href="receipt.css?v=13" rel="stylesheet">
  <style>
    .editor table{width:100%;border-collapse:separate;border-spacing:0;border-radius:8px;overflow:hidden;box-shadow:var(--shadow-sm)}
    .editor thead th{background:var(--bg-secondary);text-transform:uppercase;letter-spacing:.05em;border-bottom:2px solid var(--border-light)}
    .editor td,.editor th{padding:12px;border-bottom:1px solid var(--border-light)}
    .editor tbody tr:hover{background:var(--bg-hover)}
    .editor input[type="text"], .editor input[type="number"]{padding:10px;border-radius:8px;border:2px solid var(--border-light);width:100%}
    .row-actions{display:flex;gap:8px}
    .totals-inline{display:flex;justify-content:flex-end;margin-top:12px}
  </style>
</head>
<body>
<div class="app">
  <div class="content">
    <div class="card">
      <h2 class="gradient-text">New Manual Receipt (Saved to DB)</h2>
      <p>Enter items below. <b>Price Line</b> (Qty × Price) and <b>Total</b> update automatically.</p>

      <?php if ($success): ?>
        <div class="success">Saved! <a class="btn btn-small" href="<?= safe($success) ?>">Open Receipt</a></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="danger"><?= safe($error) ?></div>
      <?php endif; ?>

      <form method="post" id="manualForm">
        <div class="form-row">
          <div>
            <label>Note (optional)</label>
            <input type="text" name="note" placeholder="e.g., Walk-in customer">
          </div>
        </div>

        <div class="editor">
          <table>
            <thead>
            <tr>
              <th style="width:28%">Name of Item</th>
              <th style="width:22%">Brand</th>
              <th style="width:12%">Qty</th>
              <th style="width:18%">Price</th>
              <th style="width:18%">Price Line</th>
              <th style="width:12%">Actions</th>
            </tr>
            </thead>
            <tbody id="itemsBody">
            <tr>
              <td><input type="text" name="item_name[]" placeholder="e.g., Notebook A5" required></td>
              <td><input type="text" name="brand[]" placeholder="e.g., Moleskine"></td>
              <td><input type="number" name="qty[]" min="1" step="1" value="1" required></td>
              <td><input type="number" name="price[]" min="0" step="0.01" value="0.00" required></td>
              <td><input type="text" class="line_total" value="0.00" readonly></td>
              <td class="row-actions">
                <button type="button" class="btn btn-secondary btn-small addRow">+ Add</button>
                <button type="button" class="btn btn-warning btn-small removeRow">Remove</button>
              </td>
            </tr>
            </tbody>
          </table>
        </div>

        <div class="totals-inline mt-2">
          <span class="badge info">Total: <b id="liveTotal">0.00</b></span>
        </div>

        <div class="mt-3" style="display:flex;gap:8px;flex-wrap:wrap">
          <button type="button" class="btn btn-secondary" id="addRowBottom">+ Add another row</button>
          <button type="reset" class="btn btn-danger">Clear</button>
          <button type="submit" name="save" class="btn">Save & Open Receipt</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const bodyEl = document.getElementById('itemsBody');
const liveTotalEl = document.getElementById('liveTotal');
const addRowBottomBtn = document.getElementById('addRowBottom');

function money(n){ return (Math.round((n + Number.EPSILON) * 100) / 100).toFixed(2); }

function recalcRow(tr){
  const qty = parseFloat(tr.querySelector('input[name="qty[]"]').value || '0');
  const price = parseFloat(tr.querySelector('input[name="price[]"]').value || '0');
  tr.querySelector('.line_total').value = money(qty*price);
}

function recalcAll(){
  let total = 0;
  bodyEl.querySelectorAll('tr').forEach(tr => {
    const qty = parseFloat(tr.querySelector('input[name="qty[]"]').value || '0');
    const price = parseFloat(tr.querySelector('input[name="price[]"]').value || '0');
    total += qty * price;
  });
  liveTotalEl.textContent = money(total);
}

function attachRowHandlers(tr){
  tr.querySelectorAll('input[name="qty[]"], input[name="price[]"]').forEach(inp=>{
    inp.addEventListener('input', ()=>{ recalcRow(tr); recalcAll(); });
  });
  tr.querySelector('.addRow')?.addEventListener('click', addRow);
  tr.querySelector('.removeRow')?.addEventListener('click', ()=>{
    if (bodyEl.querySelectorAll('tr').length > 1){
      tr.remove(); recalcAll();
    }
  });
}

function addRow(){
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td><input type="text" name="item_name[]" placeholder="Item name" required></td>
    <td><input type="text" name="brand[]" placeholder="Brand"></td>
    <td><input type="number" name="qty[]" min="1" step="1" value="1" required></td>
    <td><input type="number" name="price[]" min="0" step="0.01" value="0.00" required></td>
    <td><input type="text" class="line_total" value="0.00" readonly></td>
    <td class="row-actions">
      <button type="button" class="btn btn-secondary btn-small addRow">+ Add</button>
      <button type="button" class="btn btn-warning btn-small removeRow">Remove</button>
    </td>`;
  bodyEl.appendChild(tr);
  attachRowHandlers(tr);
}

addRowBottomBtn?.addEventListener('click', addRow);
attachRowHandlers(bodyEl.querySelector('tr'));
recalcAll();
bodyEl.addEventListener('input', e=>{
  const tr = e.target.closest('tr'); if(!tr) return;
  recalcRow(tr); recalcAll();
});
</script>
</body>
</html>
<?php
// ====== POST handler (bottom of file so HTML loads styles even if POST fails early) ======
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
  try {
    if (empty($pdo) || !($pdo instanceof PDO)) {
      throw new Exception('Database connection ($pdo) not found. Check inc/config.php');
    }

    $names = $_POST['item_name'] ?? [];
    $brands= $_POST['brand'] ?? [];
    $qtys  = $_POST['qty'] ?? [];
    $prices= $_POST['price'] ?? [];
    $note  = trim($_POST['note'] ?? '');

    // Build clean rows
    $rows = [];
    $grand = 0.00;
    foreach ($names as $i => $nm) {
      $nm = trim($nm);
      if ($nm === '') continue;
      $q = isset($qtys[$i]) ? (float)$qtys[$i] : 0;
      $p = isset($prices[$i]) ? (float)$prices[$i] : 0;
      $b = isset($brands[$i]) ? trim($brands[$i]) : '';
      if ($q <= 0 && $p <= 0) continue;
      $line = $q * $p;
      $grand += $line;
      $rows[] = ['name'=>$nm, 'brand'=>$b, 'qty'=>$q, 'price'=>$p, 'line'=>$line];
    }

    if (count($rows) === 0) throw new Exception('Add at least one valid item.');

    // Create a unique receipt number
    $receiptNo = 'MR-' . date('Ymd-His') . '-' . substr(bin2hex(random_bytes(3)), 0, 6);

    $pdo->beginTransaction();

    // Insert parent receipt
    $stmt = $pdo->prepare("INSERT INTO manual_receipts (receipt_no, note) VALUES (?, ?)");
    $stmt->execute([$receiptNo, $note]);
    $rid = (int)$pdo->lastInsertId();

    // Insert items
    $ins = $pdo->prepare("INSERT INTO manual_receipt_items (receipt_id, item_name, brand, qty, price, line_total)
                           VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($rows as $r) {
      $ins->execute([$rid, $r['name'], $r['brand'], $r['qty'], $r['price'], $r['line']]);
    }

    $pdo->commit();

    // Redirect with success link
    $url = 'manual_receipt.php?id=' . $rid;
    header('Location: manual_receipt_new.php?success=' . urlencode($url));
    exit;
  } catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    header('Location: manual_receipt_new.php?error=' . urlencode($e->getMessage()));
    exit;
  }
}
