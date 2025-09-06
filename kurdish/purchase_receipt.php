<?php
require_once '../inc/config.php';
require_once '../inc/auth.php';
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

$dateCol = findColumn($pdo, 'purchases', ['date','created_at','purchase_date','bought_at','createdon','created_on','datetime','timestamp']);
$dateSelect = $dateCol ? "`p`.`$dateCol` AS purchase_date_col" : "NULL AS purchase_date_col";

$hasSubtotal = findColumn($pdo, 'purchases', ['subtotal']) !== null;
$hasDiscount = findColumn($pdo, 'purchases', ['discount']) !== null;
$hasTax      = findColumn($pdo, 'purchases', ['tax']) !== null;
$hasTotal    = findColumn($pdo, 'purchases', ['total']) !== null;

$selects = [
  "p.id",
  $dateSelect,
  $hasTotal    ? "p.total"    : "NULL AS total",
  $hasSubtotal ? "p.subtotal" : "NULL AS subtotal",
  $hasDiscount ? "p.discount" : "0 AS discount",
  $hasTax      ? "p.tax"      : "0 AS tax",
  "p.supplier_id",
  "s.name AS supplier_name",
  "s.phone AS supplier_phone"
];

$sql = "SELECT " . implode(", ", $selects) . "
        FROM `purchases` p
        LEFT JOIN `suppliers` s ON s.id = p.supplier_id
        WHERE p.id = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$pur = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$pur) { die('Receipt not found'); }

$items = $pdo->prepare("
  SELECT pi.product_id, pi.qty, pi.price,
         COALESCE(pr.name, CONCAT('Product #', pi.product_id)) AS product_name
  FROM purchase_items pi
  LEFT JOIN products pr ON pr.id = pi.product_id
  WHERE pi.purchase_id = ?
");
$items->execute([$id]);
$rows = $items->fetchAll(PDO::FETCH_ASSOC);

$computedSubtotal = 0.0;
foreach ($rows as $r) { $computedSubtotal += (float)$r['qty'] * (float)$r['price']; }

$subtotal = is_null($pur['subtotal']) ? $computedSubtotal : (float)$pur['subtotal'];
$discount = isset($pur['discount']) ? (float)$pur['discount'] : 0.0;
$tax      = isset($pur['tax']) ? (float)$pur['tax'] : 0.0;
$total    = isset($pur['total']) && $pur['total'] > 0 ? (float)$pur['total'] : max(0, $subtotal - $discount + $tax);

$rawDate = $pur['purchase_date_col'] ?? null;
$invDate = $rawDate ? date('Ymd', strtotime($rawDate)) : date('Ymd');
$invNo   = 'PUR-' . $invDate . '-' . $pur['id'];

$displayDate = $rawDate ? date('Y-m-d H:i', strtotime($rawDate)) : '—';

// Show overall Paid vs Credit from sales here as well, per your request
$summaryStmt = $pdo->query("
  SELECT 
    SUM(CASE WHEN is_credit = 1 THEN 1 ELSE 0 END) AS credit_count,
    SUM(CASE WHEN is_credit = 0 THEN 1 ELSE 0 END) AS paid_count
  FROM sales
");
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);
$paidCount   = (int)($summary['paid_count'] ?? 0);
$creditCount = (int)($summary['credit_count'] ?? 0);
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Purchase Receipt #<?= htmlspecialchars($invNo) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../receipt.css">
</head>
<body>
  <div class="receipt">
    <div class="brand">
      <h2>My Shop</h2>
      <span class="badge">PURCHASE RECEIPT</span>
    </div>

    <div class="meta">
      <div class="kv"><b>Receipt #:</b><span><?= htmlspecialchars($invNo) ?></span></div>
      <div class="kv"><b>Date:</b><span><?= htmlspecialchars($displayDate) ?></span></div>
    </div>
    <div class="hr"></div>

    <div class="block-title">Supplier</div>
    <div class="meta">
      <div class="kv"><b>Name:</b><span><?= htmlspecialchars($pur['supplier_name'] ?? '—') ?></span></div>
      <div class="kv"><b>Phone:</b><span><?= htmlspecialchars($pur['supplier_phone'] ?? '—') ?></span></div>
    </div>

    <div class="block-title">Items</div>
    <table class="table">
      <thead>
        <tr><th style="width:50%">Product</th><th>Qty</th><th>Cost</th><th>Subtotal</th></tr>
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
          <tr><th>Subtotal</th><td style="text-align:right"><?= number_format($subtotal, 2) ?></td></tr>
          <tr><th>Discount</th><td style="text-align:right">- <?= number_format($discount, 2) ?></td></tr>
          <tr><th>Tax</th><td style="text-align:right"><?= number_format($tax, 2) ?></td></tr>
          <tr><th>Total</th><td style="text-align:right"><strong><?= number_format($total, 2) ?></strong></td></tr>
        </tbody>
      </table>
    </div>

    <div class="hr"></div>
    <div class="block-title">Overall Sales Summary</div>
    <div class="meta">
      <div class="kv"><b>Paid Sales:</b><span><?= $paidCount ?></span></div>
      <div class="kv"><b>Credit Sales:</b><span><?= $creditCount ?></span></div>
    </div>

    <div class="note">Internal document for inventory and accounting.</div>

    <div class="actions">
      <button class="print-btn" onclick="window.print()">🖨 Print</button>
    </div>
  </div>

<script src="kurdish-ui.js?v=1"></script>
</body>
</html>
