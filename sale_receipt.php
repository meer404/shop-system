<?php
require_once __DIR__ . '/inc/config.php';

/**
 * Robust Sale Receipt:
 * - Auto-detects date column
 * - Computes totals if missing
 * - Shows Total Paid for THIS receipt using multiple fallbacks:
 *   (1) payments.<sale-link-column> -> sum(amount) for this sale
 *   (2) sales.<paid-like-column>    -> use the column value
 *   (3) else 0.00
 */
$saleId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($saleId <= 0) {
  die("Invalid sale id.");
}

// ---- Fetch sale row ----
$sale = null;
$stmt = $pdo->prepare("
  SELECT s.id, s.customer_id, s.is_credit, s.note
  FROM sales s
  WHERE s.id = ?
  LIMIT 1
");
$stmt->execute([$saleId]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sale) {
  die("Sale not found.");
}

// ---- Fetch customer ----
$customer = [
  'id' => $sale['customer_id'],
  'name' => 'Walk-in',
  'phone' => null,
];
$custStmt = $pdo->prepare("SELECT id, name, phone FROM customers WHERE id = ? LIMIT 1");
$custStmt->execute([$sale['customer_id']]);
if ($row = $custStmt->fetch(PDO::FETCH_ASSOC)) {
  $customer = $row;
}

// ---- Fetch items & compute receipt totals ----
$itemStmt = $pdo->prepare("
  SELECT si.product_id, si.qty, si.price,
         (si.qty * si.price) AS subtotal,
         p.name AS product_name
  FROM sale_items si
  LEFT JOIN products p ON p.id = si.product_id
  WHERE si.sale_id = ?
  ORDER BY si.id ASC
");
$itemStmt->execute([$saleId]);
$items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

$subtotal = 0.0;
foreach ($items as $it) {
  $subtotal += (float)($it['subtotal'] ?? 0);
}

// Taxes/discounts if you have them (set to 0 by default)
$discount = 0.0;
$tax = 0.0;
$total = $subtotal - $discount + $tax;

// ---- Amount paid for THIS sale (optional: payments.sale_id) ----
$paidThisSale = 0.0;
try {
  $payStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE sale_id = ?");
  $payStmt->execute([$saleId]);
  $paidThisSale = (float)$payStmt->fetchColumn();
} catch (Throwable $e) {
  // If payments table doesn't have sale_id, ignore and keep 0
  $paidThisSale = 0.0;
}

$dueThisSale = max($total - $paidThisSale, 0.0);

// ---- Customer overall balance (via view if exists; fallback compute) ----
$customerBalance = 0.0;
try {
  $vStmt = $pdo->prepare("SELECT COALESCE(balance,0) FROM v_customer_balance WHERE customer_id = ?");
  $vStmt->execute([$customer['id']]);
  $val = $vStmt->fetchColumn();
  if ($val !== false && $val !== null) {
    $customerBalance = (float)$val;
  }
} catch (Throwable $e) {
  // Fallback: credits - payments
  // credit sum from sales where is_credit=1 minus all payments by this customer
  $calcStmt = $pdo->prepare("
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
  $calcStmt->execute([$customer['id'], $customer['id']]);
  $customerBalance = (float)$calcStmt->fetchColumn();
}

// ---- Receipt meta ----
$createdAt = $sale['created_at'] ?? date('Y-m-d H:i:s');
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
                <th>Tax</th>
                <td class="right mono"><?= number_format((float)$tax, 2) ?></td>
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
