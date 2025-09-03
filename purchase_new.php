<?php
// purchase_new.php
// Create a new purchase: choose existing products or add manual products inline.

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/inc/config.php';

if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

$page_title = "New Purchase";
$msg = null;
$last_purchase_id = null;

// Load suppliers & products
$suppliers = $pdo->query("SELECT id, name FROM suppliers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$products  = $pdo->query("SELECT id, name, price FROM products ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $supplier_id = (int)($_POST['supplier_id'] ?? 0);
  $items = $_POST['items'] ?? [];
  $paid = (float)($_POST['paid'] ?? 0);

  // Basic validation
  if ($supplier_id <= 0) {
    $msg = "<span class='danger'>Please select a supplier.</span>";
  } elseif (empty($items)) {
    $msg = "<span class='danger'>Add at least one item.</span>";
  } else {
    // Compute subtotal from payload (server-side authoritative)
    $subtotal = 0.00;
    foreach ($items as $it) {
      $qty   = max(0, (int)($it['qty'] ?? 0));
      $price = (float)($it['price'] ?? 0);
      if ($qty > 0 && $price >= 0) {
        $subtotal += $qty * $price;
      }
    }

    $pdo->beginTransaction();
    try {
      // Insert purchase shell
      $stmt = $pdo->prepare("INSERT INTO purchases (supplier_id, subtotal, paid) VALUES (?,?,?)");
      $stmt->execute([$supplier_id, $subtotal, $paid]);
      $purchase_id = (int)$pdo->lastInsertId();
      $last_purchase_id = $purchase_id;

      // Prepare common statements
      $qProdPrice = $pdo->prepare("SELECT price FROM products WHERE id=?");
      $insItemGen = $pdo->prepare("INSERT INTO purchase_items (purchase_id, product_id, qty, price) VALUES (?,?,?,?)");
      // If your purchase_items.line_total is NOT a generated column, comment the above and use this:
      // $insItem = $pdo->prepare("INSERT INTO purchase_items (purchase_id, product_id, qty, price, line_total) VALUES (?,?,?,?,?)");

      $updStock   = $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id=?");
      $insProduct = $pdo->prepare("INSERT INTO products (name, price, stock) VALUES (?,?,0)");

      foreach ($items as $it) {
        $pid         = isset($it['product_id']) ? (int)$it['product_id'] : 0;
        $manual_name = trim($it['manual_name'] ?? '');
        $qty         = max(0, (int)($it['qty'] ?? 0));
        $price       = (float)($it['price'] ?? 0);

        if ($qty <= 0) continue;

        if ($pid > 0) {
          // Existing product: if price not given, fetch default
          if ($price <= 0) {
            $qProdPrice->execute([$pid]);
            if ($row = $qProdPrice->fetch(PDO::FETCH_ASSOC)) {
              $price = (float)$row['price'];
            }
          }
        } elseif ($manual_name !== '') {
          // Manual product: create it first
          // Ensure non-negative price
          if ($price < 0) $price = 0.00;
          $insProduct->execute([$manual_name, $price]);
          $pid = (int)$pdo->lastInsertId();
        } else {
          // Invalid row (neither product selected nor manual name)
          continue;
        }

        // Insert into purchase_items
        $insItemGen->execute([$purchase_id, $pid, $qty, $price]);
        // If NOT generated column:
        // $line = $qty * $price;
        // $insItem->execute([$purchase_id, $pid, $qty, $price, $line]);

        // Update stock
        $updStock->execute([$qty, $pid]);
      }

      $pdo->commit();
      $msg = "<span class='success'>Purchase saved.</span> <a class='badge' href='purchase_receipt.php?id={$last_purchase_id}'>Open receipt</a>";
    } catch (Throwable $e) {
      $pdo->rollBack();
      $msg = "<span class='danger'>Error saving purchase: ".h($e->getMessage())."</span>";
    }
  }
}
?>
<div class="card">
  <h2><?= h($page_title) ?></h2>
  <?php if ($msg): ?><p><?= $msg ?></p><?php endif; ?>

  <form method="post" action="purchase_new.php" id="purchaseForm" autocomplete="off">
    <div class="form-row">
      <label for="supplier_id" style="display:flex;align-items:center;gap:6px">
        Supplier:
        <select id="supplier_id" name="supplier_id" required>
          <option value="">-- Select --</option>
          <?php foreach ($suppliers as $s): ?>
            <option value="<?= (int)$s['id'] ?>"><?= h($s['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <label for="paid" style="display:flex;align-items:center;gap:6px">
        Paid now:
        <input id="paid" type="number" step="0.01" name="paid" value="0">
      </label>

      <button type="button" onclick="addRow()" title="Add item">+ Add Item</button>
      <button type="submit">Save Purchase</button>

      <div style="margin-left:auto;display:flex;align-items:center;gap:10px">
        <b>Subtotal: <span id="subtotal">0.00</span></b>
      </div>
    </div>

    <table id="itemsTbl">
      <thead>
        <tr>
          <th style="width:110px">Type</th>
          <th>Product</th>
          <th style="width:150px">Price</th>
          <th style="width:120px">Qty</th>
          <th style="width:140px">Line Total</th>
          <th style="width:70px"></th>
        </tr>
      </thead>
      <tbody></tbody>
      <tfoot>
        <tr>
          <th colspan="4" style="text-align:right">Subtotal</th>
          <th><span id="subtotal2">0.00</span></th>
          <th></th>
        </tr>
      </tfoot>
    </table>
  </form>
</div>

<script>
const products = <?= json_encode($products, JSON_UNESCAPED_UNICODE) ?>;
let rowSeq = 0;

function money(n){
  return (Number(n||0)).toFixed(2);
}

function addRow(){
  const tb = document.querySelector('#itemsTbl tbody');
  const idx = rowSeq++;
  const tr = document.createElement('tr');
  tr.dataset.idx = idx;

  tr.innerHTML = `
    <td>
      <select name="mode_${idx}" onchange="toggleMode(this, ${idx})">
        <option value="existing">Existing</option>
        <option value="manual">Manual</option>
      </select>
    </td>
    <td class="prodCell">
      <select name="items[${idx}][product_id]" required onchange="onProductChange(this, ${idx})">
        <option value="">-- Select --</option>
        ${products.map(p => `<option value="${p.id}" data-price="${p.price}">${escapeHtml(p.name)}</option>`).join('')}
      </select>
    </td>
    <td>
      <input type="number" step="0.01" name="items[${idx}][price]" value="" oninput="recalcRow(${idx})" placeholder="0.00">
    </td>
    <td>
      <input type="number" step="1" min="1" name="items[${idx}][qty]" value="1" oninput="recalcRow(${idx})" required>
    </td>
    <td><b id="lt_${idx}">0.00</b></td>
    <td><button type="button" onclick="removeRow(this)">✖</button></td>
  `;
  tb.appendChild(tr);
}

function toggleMode(sel, idx){
  const tr = sel.closest('tr');
  const td = tr.querySelector('.prodCell');
  if (sel.value === 'manual') {
    td.innerHTML = `<input type="text" name="items[${idx}][manual_name]" placeholder="Product name" required oninput="onManualName(${idx})">`;
    const priceInput = tr.querySelector(`input[name="items[${idx}][price]"]`);
    if (priceInput && !priceInput.value) priceInput.value = '';
  } else {
    td.innerHTML = `
      <select name="items[${idx}][product_id]" required onchange="onProductChange(this, ${idx})">
        <option value="">-- Select --</option>
        ${products.map(p => `<option value="${p.id}" data-price="${p.price}">${escapeHtml(p.name)}</option>`).join('')}
      </select>`;
  }
  recalcRow(idx);
}

function onManualName(idx){
  // no-op; reserved for future validations
}

function onProductChange(sel, idx){
  const opt = sel.selectedOptions[0];
  const p = Number(opt?.dataset?.price || 0);
  const tr = sel.closest('tr');
  const priceInput = tr.querySelector(`input[name="items[${idx}][price]"]`);
  if (priceInput && (!priceInput.value || Number(priceInput.value) <= 0)) {
    priceInput.value = money(p);
  }
  recalcRow(idx);
}

function recalcRow(idx){
  const tr = document.querySelector(`tr[data-idx="${idx}"]`);
  if (!tr) return;
  const qty = Number(tr.querySelector(`input[name="items[${idx}][qty]"]`)?.value || 0);
  const price = Number(tr.querySelector(`input[name="items[${idx}][price]"]`)?.value || 0);
  const lt = qty * price;
  const cell = tr.querySelector(`#lt_${idx}`);
  if (cell) cell.textContent = money(lt);
  recalcSubtotal();
}

function recalcSubtotal(){
  let sum = 0;
  document.querySelectorAll('[id^="lt_"]').forEach(b => sum += Number(b.textContent || 0));
  const s1 = document.getElementById('subtotal');
  const s2 = document.getElementById('subtotal2');
  if (s1) s1.textContent = money(sum);
  if (s2) s2.textContent = money(sum);
}

function removeRow(btn){
  btn.closest('tr')?.remove();
  recalcSubtotal();
}

function escapeHtml(s){
  return String(s).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
}
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
