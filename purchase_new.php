<?php $page = 'purchase_new.php'; require_once __DIR__ . '/header.php'; ?>
<?php
require __DIR__.'/inc/config.php';
$page_title = "New Purchase";
$msg = null;
$last_purchase_id = null;

$suppliers = $pdo->query("SELECT id, name FROM suppliers ORDER BY name")->fetchAll();
$products  = $pdo->query("SELECT id, name, price FROM products ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $supplier_id = (int)($_POST['supplier_id'] ?? 0);
  $items = $_POST['items'] ?? [];
  $paid = (float)($_POST['paid'] ?? 0);

  if ($supplier_id <= 0) {
    $msg = "Select a supplier.";
  } else if (empty($items)) {
    $msg = "Add at least one item.";
  } else {
    $subtotal = 0.00;
    foreach($items as $it){
      $pid = (int)($it['product_id'] ?? 0);
      $qty = (int)($it['qty'] ?? 0);
      $price = (float)($it['price'] ?? 0);
      if ($pid<=0 || $qty<=0) continue;
      if ($price <= 0) {
        $p = $pdo->prepare("SELECT price FROM products WHERE id=?");
        $p->execute([$pid]);
        $row = $p->fetch();
        $price = (float)$row['price'];
      }
      $subtotal += $price * $qty;
    }

    $pdo->beginTransaction();
    try {
      $pdo->prepare("INSERT INTO purchases (supplier_id, subtotal, paid) VALUES (?,?,?)")
          ->execute([$supplier_id, $subtotal, $paid]);
      $purchase_id = (int)$pdo->lastInsertId();
      $last_purchase_id = $purchase_id;

      foreach($items as $it){
        $pid = (int)($it['product_id'] ?? 0);
        $qty = (int)($it['qty'] ?? 0);
        $price = (float)($it['price'] ?? 0);
        if ($pid<=0 || $qty<=0) continue;
        if ($price <= 0) {
          $p = $pdo->prepare("SELECT price FROM products WHERE id=?");
          $p->execute([$pid]);
          $row = $p->fetch();
          $price = (float)$row['price'];
        }
        $line = $price * $qty;
        $pdo->prepare("INSERT INTO purchase_items (purchase_id, product_id, qty, price, line_total) VALUES (?,?,?,?,?)")
            ->execute([$purchase_id, $pid, $qty, $price, $line]);

        // increment stock on purchase
        $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id=?")->execute([$qty, $pid]);
      }

      $pdo->commit();
      $msg = "Purchase saved. <a href='purchase_receipt.php?id={$last_purchase_id}'>Open receipt</a>";
    } catch (Exception $e) {
      $pdo->rollBack();
      $msg = "Error saving purchase: " . h($e->getMessage());
    }
  }
}

require __DIR__.'/inc/header.php';
?>
<div class="card">
  <h2>New Purchase</h2>
  <?php if ($msg): ?><p class="success"><?= $msg ?></p><?php endif; ?>
  <form method="post" action="purchase_new.php" id="purchaseForm">
    <div class="form-row">
      <label>Supplier:</label>
      <select name="supplier_id" required>
        <option value="">-- Select --</option>
        <?php foreach($suppliers as $s): ?>
          <option value="<?= (int)$s['id'] ?>"><?= h($s['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <label>Paid now:</label>
      <input type="number" step="0.01" name="paid" value="0">
      <button type="button" onclick="addRow()">+ Add Item</button>
      <button type="submit">Save Purchase</button>
    </div>
    <table id="itemsTbl">
      <thead><tr><th>Product</th><th>Price</th><th>Qty</th><th></th></tr></thead>
      <tbody></tbody>
    </table>
  </form>
</div>

<script>
const products = <?= json_encode($products) ?>;
function addRow(){
  const tb = document.querySelector('#itemsTbl tbody');
  const tr = document.createElement('tr');
  const idx = tb.children.length;
  tr.innerHTML = `
    <td>
      <select name="items[${idx}][product_id]" onchange="updateRow(this)">
        <option value="">-- Select --</option>
        ${products.map(p=>`<option value="${p.id}" data-price="${p.price}">${p.name}</option>`).join('')}
      </select>
    </td>
    <td>
      <input type="number" step="0.01" name="items[${idx}][price]" placeholder="Price">
    </td>
    <td><input type="number" name="items[${idx}][qty]" value="1" min="1"></td>
    <td><button type="button" onclick="this.closest('tr').remove()">Remove</button></td>
  `;
  tb.appendChild(tr);
}
function updateRow(sel){
  const opt = sel.selectedOptions[0];
  const tr = sel.closest('tr');
  const priceInput = tr.querySelector('input[name*="[price]"]');
  if (priceInput && (!priceInput.value || Number(priceInput.value)<=0)) {
    priceInput.value = Number(opt.dataset.price||0).toFixed(2);
  }
}
</script>
<?php require __DIR__.'/inc/footer.php'; ?>
<?php require_once __DIR__ . '/footer.php'; ?>
