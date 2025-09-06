<?php
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/auth.php';

$saleId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($saleId <= 0) die("Invalid sale id.");

//
// 1) Load core sale fields
//
$sale = null;
$stmt = $pdo->prepare("
  SELECT s.id, s.customer_id, s.is_credit, s.note,
         /* try common date column; adjust below if you use another */
         s.sale_date 
  FROM sales s
  WHERE s.id = ?
  LIMIT 1
");
$stmt->execute([$saleId]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$sale) die("Sale not found.");

//
// 2) Try to read sales.discount if the column exists
//
$discount = 0.0;
try {
  $q = $pdo->prepare("SELECT COALESCE(discount,0) FROM sales WHERE id = ?");
  $q->execute([$saleId]);
  $v = $q->fetchColumn();
  if ($v !== false && $v !== null) $discount = (float)$v;
} catch (Throwable $e) {
  // Column doesn't exist yet; leave $discount = 0.0
}

//
// 3) Customer
//
$customer = ['id'=>$sale['customer_id'], 'name'=>'Walk-in', 'phone'=>null];
$cst = $pdo->prepare("SELECT id,name,phone FROM customers WHERE id=? LIMIT 1");
$cst->execute([$sale['customer_id']]);
if ($r = $cst->fetch(PDO::FETCH_ASSOC)) $customer = $r;

//
// 4) Items + subtotal
//
$it = $pdo->prepare("
  SELECT si.product_id, si.qty, si.price,
         (si.qty * si.price) AS subtotal,
         p.name AS product_name
  FROM sale_items si
  LEFT JOIN products p ON p.id = si.product_id
  WHERE si.sale_id = ?
  ORDER BY si.id ASC
");
$it->execute([$saleId]);
$items = $it->fetchAll(PDO::FETCH_ASSOC);

$subtotal = 0.0;
foreach ($items as $row) $subtotal += (float)($row['subtotal'] ?? 0);

//
// 5) Totals
//
$tax = 0.0;                    // keep 0 unless you add tax
$total = $subtotal - $discount;
if ($total < 0) $total = 0.0;

//
// 6) Paid for THIS sale (payments.sale_id -> SUM(amount)); fallback to sales.paid if present
//
$paidThisSale = 0.0;
try {
  $ps = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE sale_id = ?");
  $ps->execute([$saleId]);
  $paidThisSale = (float)$ps->fetchColumn();
} catch (Throwable $e) { /* ignore */ }

if ($paidThisSale <= 0) {
  try {
    $ps2 = $pdo->prepare("SELECT COALESCE(paid,0) FROM sales WHERE id = ?");
    $ps2->execute([$saleId]);
    $v = $ps2->fetchColumn();
    if ($v !== false && $v !== null) $paidThisSale = (float)$v;
  } catch (Throwable $e) { /* ignore */ }
}
$dueThisSale = max($total - $paidThisSale, 0.0);

//
// 7) Customer overall balance (view v_customer_balance if exists; else compute)
//
$customerBalance = 0.0;
try {
  $vb = $pdo->prepare("SELECT COALESCE(balance,0) FROM v_customer_balance WHERE customer_id = ?");
  $vb->execute([$customer['id']]);
  $val = $vb->fetchColumn();
  if ($val !== false && $val !== null) $customerBalance = (float)$val;
} catch (Throwable $e) {
  $calc = $pdo->prepare("
    SELECT
      COALESCE((
        SELECT SUM(si.qty * si.price)
        FROM sales s
        JOIN sale_items si ON si.sale_id = s.id
        WHERE s.customer_id = ? AND COALESCE(s.is_credit,0) = 1
      ),0)
      -
      COALESCE((
        SELECT SUM(p.amount) FROM payments p WHERE p.customer_id = ?
      ),0)
  ");
  $calc->execute([$customer['id'], $customer['id']]);
  $customerBalance = (float)$calc->fetchColumn();
}

//
// 8) Meta
//
$createdAt = $sale['created_at'] ?? date('Y-m-d H:i:s');   // adjust if your date column has a different name
$invoiceNo = sprintf("INV-%s-%d", date('Ymd', strtotime($createdAt)), $saleId);
$isCredit = !empty($sale['is_credit']) ? (int)$sale['is_credit'] : 0;
$note = $sale['note'] ?? '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Receipt #<?= htmlspecialchars($invoiceNo) ?></title>
  <link rel="stylesheet" href="styles.css">
  <style>
    .receipt{max-width:900px;margin:0 auto;background:#111827;border:1px solid #20293a;border-radius:12px;padding:18px}
    .header{display:flex;justify-content:space-between;align-items:flex-start;gap:16px}
    .muted{opacity:.8}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
    .totals{max-width:360px;margin-left:auto}
    .right{text-align:right}
    .big{font-size:1.25rem}
    .mono{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace}
    .print-actions{display:flex;gap:8px;margin:14px 0}
    @media print{ .print-actions{display:none} body{background:#fff;color:#000} }
  </style>
</head>
<body>
  <div class="content">
    <div class="receipt">
      <div class="header">
        <div>
          <h1>Sale Receipt</h1>
          <div class="muted">Invoice: <b class="mono"><?= htmlspecialchars($invoiceNo) ?></b></div>
          <div class="muted">Date: <b><?= htmlspecialchars(date('Y-m-d H:i', strtotime($createdAt))) ?></b></div>
        </div>
        <div class="right">
          <div class="big"><b><?= htmlspecialchars($customer['name'] ?? 'Walk-in') ?></b></div>
          <?php if (!empty($customer['phone'])): ?>
            <div class="muted"><?= htmlspecialchars($customer['phone']) ?></div>
          <?php endif; ?>
          <div class="muted">
            Type:
            <?php if ($isCredit): ?>
              <span class="badge">Credit</span>
            <?php else: ?>
              <span class="badge">Cash</span>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <?php if ($note !== ''): ?>
        <div class="card"><b>Note:</b> <?= nl2br(htmlspecialchars($note)) ?></div>
      <?php endif; ?>

      <div class="card">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Product</th>
              <th class="right">Qty</th>
              <th class="right">Price</th>
              <th class="right">Subtotal</th>
            </tr>
          </thead>
          <tbody>
          <?php if ($items): $i=1; foreach($items as $it): ?>
            <tr>
              <td><?= $i++ ?></td>
              <td><?= htmlspecialchars($it['product_name'] ?? ('#'.$it['product_id'])) ?></td>
              <td class="right"><?= number_format((float)$it['qty'], 2) ?></td>
              <td class="right"><?= number_format((float)$it['price'], 2) ?></td>
              <td class="right"><?= number_format((float)$it['subtotal'], 2) ?></td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="5">No items</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="grid">
        <div class="card">
          <h3>Customer Balance</h3>
          <p class="muted">Overall balance for this customer (credits − payments):</p>
          <p class="big mono"><?= number_format((float)$customerBalance, 2) ?></p>
        </div>

        <div class="card totals">
          <table>
            <tbody>
              <tr>
                <th>Subtotal</th>
                <td class="right mono"><?= number_format((float)$subtotal, 2) ?></td>
              </tr>
              <tr>
                <th>Discount</th>
                <td class="right mono"><?= number_format((float)$discount, 2) ?></td>
              </tr>
             
              <tr>
                <th>Total</th>
                <td class="right mono"><b><?= number_format((float)$total, 2) ?></b></td>
              </tr>
              <tr>
                <th>Paid (this sale)</th>
                <td class="right mono"><?= number_format((float)$paidThisSale, 2) ?></td>
              </tr>
              <tr>
                <th>Due (this sale)</th>
                <td class="right mono"><b><?= number_format((float)$dueThisSale, 2) ?></b></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="print-actions">
        <button onclick="window.print()">🖨️ Print</button>
        <a href="receipts.php" class="badge">← All receipts</a>
        <a href="index.php" class="badge">🏠 Dashboard</a>
      </div>

      <div class="muted">Thank you for your purchase.</div>
    </div>
  </div>
</body>
</html>
