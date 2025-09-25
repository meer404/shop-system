<?php $page = 'receipt_edit_items.php'; require_once __DIR__ . '/header.php'; require_once __DIR__ . '/inc/auth.php';?>
<?php
// receipt_edit_items.php — Full editor for a SALE receipt: header + items
require __DIR__ . '/inc/config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { die("Invalid receipt id"); }

// ---- Fetch sale, items, and products ----
$sale_q = $pdo->prepare("
  SELECT s.*, c.name AS customer_name
  FROM sales s
  JOIN customers c ON c.id = s.customer_id
  WHERE s.id = ?
");
$sale_q->execute([$id]);
$sale = $sale_q->fetch();
if (!$sale) { die("Receipt not found"); }

$item_q = $pdo->prepare("
  SELECT si.id, si.product_id, si.qty, si.price, si.line_total, p.name AS product_name
  FROM sale_items si
  JOIN products p ON p.id = si.product_id
  WHERE si.sale_id = ?
  ORDER BY si.id
");
$item_q->execute([$id]);
$items = $item_q->fetchAll();

$products = $pdo->query("SELECT id, name, price, stock FROM products ORDER BY name")->fetchAll();

// Build product map for quick lookup
$prod_map = [];
foreach ($products as $p) { $prod_map[(int)$p['id']] = $p; }

$msg = null;
$err = null;

// ---- Handle POST: header + items ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_all') {
  // 1) Header fields
  $paid_in   = $_POST['paid'] ?? '';
  $paid      = ($paid_in === '' ? $sale['paid'] : (float)$paid_in);

  $note      = isset($_POST['note']) ? trim($_POST['note']) : $sale['note'];
  $sale_date = trim($_POST['sale_date'] ?? $sale['sale_date']);
  if ($sale_date !== '' && strlen($sale_date) <= 10) {
    $sale_date .= " 00:00:00";
  }

  // 2) Items payload (existing + new)
  // existing items come as items[<item_id>][product_id|qty]
  $posted_items = $_POST['items'] ?? [];     // keyed by existing si.id
  $new_items    = $_POST['new_items'] ?? []; // numeric indexes

  // Build current snapshot for diff
  $current = []; // item_id => ['product_id'=>.., 'qty'=>.., 'price'=>..]
  foreach ($items as $it) {
    $current[(int)$it['id']] = [
      'product_id' => (int)$it['product_id'],
      'qty'        => (int)$it['qty'],
      'price'      => (float)$it['price'],
    ];
  }

  // Prepare diff sets
  $to_delete = [];          // item_id[]
  $to_update = [];          // item_id => ['product_id','qty','price','line_total']
  $to_insert = [];          // list of ['product_id','qty','price','line_total']

  // Validate and compute stock delta plan:
  // We will "return" previous quantities to previous products, then "take" new quantities.
  // First compute intended target state for existing items:
  $after_existing = []; // item_id => ['product_id','qty']

  foreach ($current as $iid => $old) {
    if (!isset($posted_items[$iid])) {
      // Removed
      $to_delete[] = $iid;
    } else {
      $pid = (int)($posted_items[$iid]['product_id'] ?? 0);
      $qty = (int)($posted_items[$iid]['qty'] ?? 0);
      if ($pid <= 0 || $qty <= 0) {
        $to_delete[] = $iid; // invalid becomes delete
      } else {
        $after_existing[$iid] = ['product_id' => $pid, 'qty' => $qty];
      }
    }
  }

  // New rows
  foreach ($new_items as $row) {
    $pid = (int)($row['product_id'] ?? 0);
    $qty = (int)($row['qty'] ?? 0);
    if ($pid > 0 && $qty > 0) {
      $to_insert[] = ['product_id' => $pid, 'qty' => $qty];
    }
  }

  // Build product-level stock deltas:
  // Start by "returning" all old quantities (decrements done at sale time are undone first)
  $stock_delta = []; // product_id => delta (positive means stock increases)
  foreach ($current as $iid => $old) {
    $pid_old = $old['product_id']; $q_old = $old['qty'];
    $stock_delta[$pid_old] = ($stock_delta[$pid_old] ?? 0) + $q_old; // give back
  }
  // Then subtract the new target quantities (existing+new)
  foreach ($after_existing as $iid => $t) {
    $pid_new = $t['product_id']; $q_new = $t['qty'];
    $stock_delta[$pid_new] = ($stock_delta[$pid_new] ?? 0) - $q_new;
  }
  foreach ($to_insert as $ni) {
    $stock_delta[$ni['product_id']] = ($stock_delta[$ni['product_id']] ?? 0) - $ni['qty'];
  }

  // Validate stock availability for any negative deltas
  foreach ($stock_delta as $pid => $delta) {
    if ($delta < 0) {
      $pinfo = $prod_map[$pid] ?? null;
      if (!$pinfo) { $err = "Product not found during validation."; break; }
      $available = (int)$pinfo['stock'];
      if ($available + $delta < 0) {
        $err = "Not enough stock for product: " . htmlspecialchars($pinfo['name']) . ". Needed ".abs($delta).", available ".$available.".";
        break;
      }
    }
  }

  if (!$err) {
    // Ready to apply changes
    $pdo->beginTransaction();
    try {
      // 1) Apply stock deltas
      foreach ($stock_delta as $pid => $delta) {
        if ($delta !== 0) {
          $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?")
              ->execute([$delta, $pid]);
        }
      }

      // 2) Delete removed items
      if ($to_delete) {
        $in = implode(',', array_fill(0, count($to_delete), '?'));
        $pdo->prepare("DELETE FROM sale_items WHERE id IN ($in)")
            ->execute(array_values($to_delete));
      }

      // 3) Update existing items that remain (and changed)
      foreach ($after_existing as $iid => $tgt) {
        $pid_new = (int)$tgt['product_id'];
        $qty_new = (int)$tgt['qty'];

        // If truly unchanged (same product & qty), keep price as-is
        $unchanged = isset($current[$iid]) &&
                     $current[$iid]['product_id'] === $pid_new &&
                     $current[$iid]['qty'] === $qty_new;

        if ($unchanged) {
          // Only recompute line_total from stored price * qty (keeps original price)
          $price = (float)$current[$iid]['price'];
        } else {
          // Product or qty changed: use current product price as the unit price
          $pinfo = $prod_map[$pid_new] ?? null;
          if (!$pinfo) { throw new Exception("Product missing."); }
          $price = (float)$pinfo['price'];
        }
        $line = $price * $qty_new;

        $pdo->prepare("UPDATE sale_items SET product_id = ?, qty = ?, price = ?, line_total = ? WHERE id = ?")
            ->execute([$pid_new, $qty_new, $price, $line, $iid]);
      }

      // 4) Insert new items
      foreach ($to_insert as $ni) {
        $pid = (int)$ni['product_id'];
        $qty = (int)$ni['qty'];
        $pinfo = $prod_map[$pid] ?? null;
        if (!$pinfo) { throw new Exception("Product missing."); }
        $price = (float)$pinfo['price'];
        $line  = $price * $qty;

        $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, qty, price, line_total) VALUES (?,?,?,?,?)")
            ->execute([$sale['id'], $pid, $qty, $price, $line]);
      }

      // 5) Recalculate subtotal from items
      $sum = $pdo->prepare("SELECT COALESCE(SUM(line_total),0) AS s FROM sale_items WHERE sale_id = ?");
      $sum->execute([$sale['id']]);
      $subtotal = (float)$sum->fetchColumn();

      // 6) Update sale header (paid/note/date & credit flag)
      $is_credit = ($paid < $subtotal) ? 1 : 0;
      $upd = $pdo->prepare("UPDATE sales SET subtotal = ?, paid = ?, is_credit = ?, note = ?, sale_date = ? WHERE id = ?");
      $upd->execute([$subtotal, $paid, $is_credit, $note, $sale_date, $sale['id']]);

      $pdo->commit();
      $msg = "Receipt and items updated successfully.";
      // refresh sale & items for display
      $sale_q->execute([$id]); $sale = $sale_q->fetch();
      $item_q->execute([$id]); $items = $item_q->fetchAll();
      // refresh product stocks
      $products = $pdo->query("SELECT id, name, price, stock FROM products ORDER BY name")->fetchAll();
      $prod_map = [];
      foreach ($products as $p) { $prod_map[(int)$p['id']] = $p; }
    } catch (Exception $e) {
      $pdo->rollBack();
      $err = "Error: " . htmlspecialchars($e->getMessage());
    }
  }
}

$page_title = "Edit Receipt & Items #".$sale['id']." — ".$sale['customer_name'];
require __DIR__ . '/inc/header.php';

// helper for select options
function product_options($products, $selected_id = 0) {
  $out = '<option value="">-- Select --</option>';
  foreach ($products as $p) {
    $sel = ((int)$p['id'] === (int)$selected_id) ? ' selected' : '';
    $out .= '<option value="'.(int)$p['id'].'" data-price="'.htmlspecialchars($p['price']).'" data-stock="'.(int)$p['stock'].'"'.$sel.'>'
         .  htmlspecialchars($p['name'])
         .  '</option>';
  }
  return $out;
}
?>
<div class="card">
  <h2>Edit Receipt & Items</h2>
  <?php if ($msg): ?><p class="success"><?= h($msg) ?></p><?php endif; ?>
  <?php if ($err): ?><p class="danger"><?= h($err) ?></p><?php endif; ?>

  <form method="post" action="receipt_edit_items.php?id=<?= (int)$sale['id'] ?>" id="editForm">
    <input type="hidden" name="action" value="save_all">

    <div class="form-row" style="flex-wrap:wrap;gap:12px;align-items:flex-end">
      <div style="display:flex;flex-direction:column;min-width:220px">
      <label>Customer</label>
      <input style="height: 36px;" value="<?= h($sale['customer_name']) ?>" disabled>
      </div>
      <div style="display:flex;flex-direction:column;max-width:160px">
      <label>Subtotal</label>
      <input id="subtotal" style="height: 36px;" value="<?= number_format((float)$sale['subtotal'],2) ?>" disabled>
      </div>
      <div style="display:flex;flex-direction:column;max-width:160px">
      <label>Paid</label>
      <input type="number" step="0.01" style="height: 36px;" name="paid" value="<?= h($sale['paid']) ?>">
      </div>
      <div style="display:flex;flex-direction:column;max-width:200px">
      <label>Sale date</label>
      <input type="text" name="sale_date" style="height: 36px;" placeholder="YYYY-MM-DD or YYYY-MM-DD HH:MM:SS" value="<?= h($sale['sale_date']) ?>">
      </div>
      <div style="display:flex;flex-direction:column;min-width:300px;flex:1">
      <label>Note</label>
      <textarea name="note" rows="2" style="height: 72px;" placeholder="Write note..."><?= h($sale['note'] ?? '') ?></textarea>
      </div>
    </div>

    <div class="card" style="margin-top:12px">
      <h3>Items</h3>
      <table id="itemsTbl">
        <thead>
          <tr>
            <th>Product</th><th>Price</th><th>Stock</th><th>Qty</th><th>Line Total</th><th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $it): ?>
          <tr data-existing="1">
            <td>
              <select name="items[<?= (int)$it['id'] ?>][product_id]" onchange="updateRow(this); recalc();">
                <?= product_options($products, $it['product_id']) ?>
              </select>
            </td>
            <td class="price">$<?= number_format((float)$it['price'],2) ?></td>
            <td class="stock"><?= h($prod_map[(int)$it['product_id']]['stock'] ?? 0) ?></td>
            <td><input type="number" name="items[<?= (int)$it['id'] ?>][qty]" value="<?= (int)$it['qty'] ?>" min="1" oninput="recalc()"></td>
            <td class="lineTotal">$<?= number_format((float)$it['line_total'],2) ?></td>
            <td><button type="button" onclick="removeRow(this)">Remove</button></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <div class="form-row" style="margin-top:8px">
        <button type="button" onclick="addRow()">+ Add Item</button>
      </div>
    </div>

    <div class="form-row" style="justify-content:flex-start;gap:10px">
      <button type="submit">Save All</button>
      <a class="badge" href="sale_receipt.php?id=<?= (int)$sale['id'] ?>">View receipt</a>
      <a class="badge" href="receipts.php">Back to receipts</a>
    </div>
  </form>
</div>

<script>
const products = <?= json_encode($products) ?>;

function money(n){ return Number(n||0).toFixed(2); }

function addRow(){
  const tb = document.querySelector('#itemsTbl tbody');
  const tr = document.createElement('tr');
  const idx = tb.querySelectorAll('tr').length;
  tr.innerHTML = `
    <td>
      <select name="new_items[${idx}][product_id]" onchange="updateRow(this); recalc();">
        <option value="">-- Select --</option>
        ${products.map(p=>`<option value="${p.id}" data-price="${p.price}" data-stock="${p.stock}">${p.name}</option>`).join('')}
      </select>
    </td>
    <td class="price">$0.00</td>
    <td class="stock">0</td>
    <td><input type="number" name="new_items[${idx}][qty]" value="1" min="1" oninput="recalc()"></td>
    <td class="lineTotal">$0.00</td>
    <td><button type="button" onclick="removeRow(this)">Remove</button></td>
  `;
  tb.appendChild(tr);
}

function removeRow(btn){
  const tr = btn.closest('tr');
  // For existing lines, we simply remove the row in the UI; server will treat as delete
  tr.remove();
  recalc();
}

function updateRow(sel){
  const opt = sel.selectedOptions[0];
  const tr = sel.closest('tr');
  const price = Number(opt?.dataset?.price || 0);
  const stock = Number(opt?.dataset?.stock || 0);
  tr.querySelector('.price').textContent = '$' + money(price);
  tr.querySelector('.stock').textContent = stock;
  recalc();
}

function recalc(){
  const rows = Array.from(document.querySelectorAll('#itemsTbl tbody tr'));
  let subtotal = 0;
  rows.forEach(tr=>{
    const sel = tr.querySelector('select');
    const opt = sel && sel.selectedOptions ? sel.selectedOptions[0] : null;
    const price = Number(opt?.dataset?.price || 0);
    const qty = Number(tr.querySelector('input[type="number"]')?.value || 0);
    const line = price * qty;
    if (tr.querySelector('.lineTotal')) tr.querySelector('.lineTotal').textContent = '$' + money(line);
    subtotal += line;
  });
  const sub = document.getElementById('subtotal');
  if (sub) sub.value = money(subtotal);
}

// initial calc
document.addEventListener('DOMContentLoaded', recalc);
</script>

<?php require __DIR__ . '/inc/footer.php'; ?>
<?php require_once __DIR__ . '/footer.php'; ?>
