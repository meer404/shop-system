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
function findColumn(PDO $pdo, string $table, array $candidates): ?string {
  $stmt = $pdo->prepare("DESCRIBE `$table`");
  $stmt->execute();
  $cols = array_map(fn($r) => strtolower($r['Field']), $stmt->fetchAll(PDO::FETCH_ASSOC));
  foreach ($candidates as $c) {
    if (in_array(strtolower($c), $cols, true)) return $c;
  }
  return null;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { die('Invalid receipt ID'); }

// ---- discover sales date col
$dateCol = findColumn($pdo, 'sales', ['date','created_at','sale_date','sold_at','createdon','created_on','datetime','sale_time','timestamp']);
$dateSelect = $dateCol ? "`s`.`$dateCol` AS sale_date_col" : "NULL AS sale_date_col";

// ---- discover numeric cols
$colSubtotal = findColumn($pdo, 'sales', ['subtotal']);
$colDiscount = findColumn($pdo, 'sales', ['discount']);
$colTax      = findColumn($pdo, 'sales', ['tax']);
$colTotal    = findColumn($pdo, 'sales', ['total']);
$colIsCredit = findColumn($pdo, 'sales', ['is_credit']);

$selects = [
  "s.id",
  $dateSelect,
  $colTotal    ? "s.`$colTotal` AS total"      : "NULL AS total",
  $colSubtotal ? "s.`$colSubtotal` AS subtotal": "NULL AS subtotal",
  $colDiscount ? "s.`$colDiscount` AS discount": "0 AS discount",
  $colTax      ? "s.`$colTax` AS tax"          : "0 AS tax",
  $colIsCredit ? "s.`$colIsCredit` AS is_credit" : "0 AS is_credit",
  "s.customer_id",
  "c.name AS customer_name",
  "c.phone AS customer_phone"
];

$sql = "SELECT " . implode(", ", $selects) . "
        FROM `sales` s
        LEFT JOIN `customers` c ON c.id = s.customer_id
        WHERE s.id = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$sale) { die('Receipt not found'); }

// ---- items
$items = $pdo->prepare("
  SELECT si.product_id, si.qty, si.price,
         COALESCE(p.name, CONCAT('Product #', si.product_id)) AS product_name
  FROM sale_items si
  LEFT JOIN products p ON p.id = si.product_id
  WHERE si.sale_id = ?
");
$items->execute([$id]);
$rows = $items->fetchAll(PDO::FETCH_ASSOC);

// ---- compute totals if missing
$computedSubtotal = 0.0;
foreach ($rows as $r) { $computedSubtotal += (float)$r['qty'] * (float)$r['price']; }
$subtotal = is_null($sale['subtotal']) ? $computedSubtotal : (float)$sale['subtotal'];
$discount = isset($sale['discount']) ? (float)$sale['discount'] : 0.0;
$tax      = isset($sale['tax']) ? (float)$sale['tax'] : 0.0;
$total    = isset($sale['total']) && $sale['total'] > 0 ? (float)$sale['total'] : max(0, $subtotal - $discount + $tax);

// ---- invoice meta
$rawDate = $sale['sale_date_col'] ?? null;
$invDate = $rawDate ? date('Ymd', strtotime($rawDate)) : date('Ymd');
$invNo   = 'INV-' . $invDate . '-' . $sale['id'];
$displayDate = $rawDate ? date('Y-m-d H:i', strtotime($rawDate)) : '—';
$isCredit    = !empty($sale['is_credit']) ? 'Credit' : 'Paid';

// ---- Total Paid (THIS receipt)
// Strategy (A): payments has a sale link column
$paymentsLinkCol = findColumn($pdo, 'payments', ['sale_id','receipt_id','invoice_id','saleid','ref_sale_id','ref_id']);
$paidThis = 0.0;
if ($paymentsLinkCol) {
  $sqlPaid = "SELECT COALESCE(SUM(amount),0) FROM payments WHERE `$paymentsLinkCol` = ?";
  $ps = $pdo->prepare($sqlPaid);
  $ps->execute([$sale['id']]);
  $paidThis = (float)$ps->fetchColumn();
} else {
  // Strategy (B): sales table has its own "paid" field
  $salesPaidCol = findColumn($pdo, 'sales', ['paid','paid_amount','amount_paid','received','cash','advance','deposit']);
  if ($salesPaidCol) {
    $tmp = $pdo->prepare("SELECT COALESCE(`$salesPaidCol`,0) FROM sales WHERE id = ?");
    $tmp->execute([$sale['id']]);
    $paidThis = (float)$tmp->fetchColumn();
  } else {
    $paidThis = 0.0; // no clear linkage; safer to show 0.00
  }
}

$outstanding = max(0, $total - $paidThis);
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Sale Receipt #<?= htmlspecialchars($invNo) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="receipt.css">
</head>
<body>
  <div class="receipt">
    <div class="brand">
      <h2>My Shop</h2>
      <span class="badge">SALE RECEIPT</span>
    </div>
    <div class="meta">
      <div class="kv"><b>Receipt #:</b><span><?= htmlspecialchars($invNo) ?></span></div>
      <div class="kv"><b>Date:</b><span><?= htmlspecialchars($displayDate) ?></span></div>
      <div class="kv"><b>Payment:</b><span><?= htmlspecialchars($isCredit) ?></span></div>
    </div>
    <div class="hr"></div>

    <div class="block-title">Customer</div>
    <div class="meta">
      <div class="kv"><b>Name:</b><span><?= htmlspecialchars($sale['customer_name'] ?? '—') ?></span></div>
      <div class="kv"><b>Phone:</b><span><?= htmlspecialchars($sale['customer_phone'] ?? '—') ?></span></div>
    </div>

    <div class="block-title">Items</div>
    <table class="table">
      <thead>
        <tr><th style="width:50%">Product</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $it): $line = (float)$it['qty'] * (float)$it['price']; ?>
        <tr>
          <td><?= htmlspecialchars($it['product_name']) ?></td>
          <td><?= (float)$it['qty'] ?></td>
          <td><?= number_format((float)$it['price'], 2) ?></td>
          <td><?= number_format($line, 2) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="totals">
      <table class="table">
        <tbody>
          <tr><th>Subtotal</th><td style="text-align:right">$<?= number_format($subtotal, 2) ?></td></tr>
          <tr><th>Discount</th><td style="text-align:right">-$<?= number_format($discount, 2) ?></td></tr>
          <tr><th>Tax</th><td style="text-align:right">$<?= number_format($tax, 2) ?></td></tr>
          <tr><th><strong>Total Paid (this receipt)</strong></th><td style="text-align:right"><strong>$<?= number_format($paidThis, 2) ?></strong></td></tr>
          <tr><th>Total</th><td style="text-align:right"><strong>$<?= number_format($total, 2) ?></strong></td></tr>
          <tr><th>Outstanding</th><td style="text-align:right">$<?= number_format($outstanding, 2) ?></td></tr>
        </tbody>
      </table>
    </div>

    <div class="note">Thank you for your purchase.</div>

    <div class="actions">
      <button class="print-btn" onclick="window.print()">🖨 Print</button>
    </div>
  </div>
</body>
</html>
