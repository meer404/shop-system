<?php
// manual_receipt_new.php
$page = '3arz_system.php'; 

if (session_status() === PHP_SESSION_NONE) session_start();

// Core includes
require_once __DIR__ . '/header.php'; 
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/config.php';

function safe($v){ return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

$success = $_GET['success'] ?? '';
$error   = $_GET['error'] ?? '';
$page_title = "New Manual Receipt";

// ====== POST handler moved to the top before any HTML output ======
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

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("INSERT INTO manual_receipts (receipt_no, note) VALUES (?, ?)");
    $stmt->execute(['', $note]);
    $rid = (int)$pdo->lastInsertId();

    $receiptNo = 'MR-' . date('Ymd') . '-' . str_pad((string)$rid, 3, '0', STR_PAD_LEFT);

    $pdo->prepare("UPDATE manual_receipts SET receipt_no = ? WHERE id = ?")
        ->execute([$receiptNo, $rid]);

    $ins = $pdo->prepare("INSERT INTO manual_receipt_items (receipt_id, item_name, brand, qty, price, line_total)
                           VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($rows as $r) {
      $ins->execute([$rid, $r['name'], $r['brand'], $r['qty'], $r['price'], $r['line']]);
    }

    $pdo->commit();

    $url = 'manual_receipt.php?id=' . $rid;
    header('Location: manual_receipt_new.php?success=' . urlencode($url));
    exit;
  } catch (Throwable $e) {
    if (!empty($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    header('Location: manual_receipt_new.php?error=' . urlencode($e->getMessage()));
    exit;
  }
}

// Include the standard layout header
require __DIR__.'/inc/header.php'; 
?>

<link href="receipt.css?v=13" rel="stylesheet">

<div class="card">
  <h2 class="gradient-text">New Manual Receipt</h2>
  

  <?php if ($success): ?>
    <div class="success">Saved! <a class="btn btn-small" href="<?= safe($success) ?>">Open Receipt</a></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="danger"><?= safe($error) ?></div>
  <?php endif; ?>

  <form method="post" id="manualForm">
    <div class="form-row">
      <label>Note (optional):</label>
      <input type="text" name="note" placeholder="e.g., Walk-in customer">
      <button type="button" class="btn" onclick="addRow()">+ Add Item</button>
      <button type="submit" name="save" class="btn">Save & Open Receipt</button>
    </div>

    <table id="itemsTbl">
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
      </tbody>
    </table>
  </form>
</div>

<div class="form-row" style="justify-content: flex-end; margin-top: 1rem;">
  <span class="badge">Total: <strong id="total">0.00</strong></span>
</div>

<script>
const itemsTbody = document.getElementById('itemsBody');
const totalEl = document.getElementById('total');

function money(n){ 
  return (Math.round((n + Number.EPSILON) * 100) / 100).toFixed(2); 
}

function recalcRow(tr){
  const qty = parseFloat(tr.querySelector('input[name="qty[]"]').value || '0');
  const price = parseFloat(tr.querySelector('input[name="price[]"]').value || '0');
  const lineTotalInput = tr.querySelector('.line_total');
  if(lineTotalInput) {
    lineTotalInput.value = money(qty * price);
  }
}

function recalcAll(){
  let total = 0;
  itemsTbody.querySelectorAll('tr').forEach(tr => {
    const qty = parseFloat(tr.querySelector('input[name="qty[]"]').value || '0');
    const price = parseFloat(tr.querySelector('input[name="price[]"]').value || '0');
    total += qty * price;
  });
  totalEl.textContent = money(total);
}

function attachRowHandlers(tr){
  tr.querySelectorAll('input[name="qty[]"], input[name="price[]"]').forEach(inp => {
    inp.addEventListener('input', () => { 
      recalcRow(tr); 
      recalcAll(); 
    });
  });

  tr.querySelector('.addRow')?.addEventListener('click', addRow);
  
  tr.querySelector('.removeRow')?.addEventListener('click', () => {
    if (itemsTbody.querySelectorAll('tr').length > 1){
      tr.remove(); 
      recalcAll();
    }
  });
}

function addRow(){
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td><input type="text" name="item_name[]" placeholder="e.g., Notebook A5" required></td>
    <td><input type="text" name="brand[]" placeholder="e.g., Moleskine"></td>
    <td><input type="number" name="qty[]" min="1" step="1" value="1" required></td>
    <td><input type="number" name="price[]" min="0" step="0.01" value="0.00" required></td>
    <td><input type="text" class="line_total" value="0.00" readonly></td>
    <td class="row-actions">
      <button type="button" class="btn btn-secondary btn-small addRow">+ Add</button>
      <button type="button" class="btn btn-warning btn-small removeRow">Remove</button>
    </td>`;
  itemsTbody.appendChild(tr);
  attachRowHandlers(tr);
  tr.querySelector('input[name="item_name[]"]').focus();
}

// Add the first row on page load
document.addEventListener('DOMContentLoaded', () => {
  addRow();
  recalcAll();
});
</script>

<?php 
// Include the standard layout footer
require __DIR__.'/inc/footer.php'; 
?>