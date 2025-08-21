<?php
require __DIR__.'/inc/config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { die("Invalid purchase id"); }

$h = $pdo->prepare("SELECT p.*, s.name AS supplier_name, s.phone
                    FROM purchases p JOIN suppliers s ON s.id=p.supplier_id
                    WHERE p.id=?");
$h->execute([$id]);
$header = $h->fetch();
if (!$header) { die("Purchase not found"); }

$it = $pdo->prepare("SELECT pi.*, pr.name AS product_name
                     FROM purchase_items pi
                     JOIN products pr ON pr.id=pi.product_id
                     WHERE pi.purchase_id=?");
$it->execute([$id]);
$items = $it->fetchAll();

$subtotal = (float)$header['subtotal'];
$paid = (float)$header['paid'];
$due = max(0, $subtotal - $paid);
$receipt_no = 'INV-PUR-' . date('Ymd', strtotime($header['purchase_date'])) . '-' . $header['id'];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Purchase Receipt <?= h($receipt_no) ?></title>
<link rel="stylesheet" href="assets/styles.css">
<style>
.receipt{max-width:800px;margin:0 auto;background:#fff;color:#000;padding:16px;border:1px solid #ccc;border-radius:8px}
.header{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:1px solid #999;padding-bottom:8px;margin-bottom:12px}
.header h2{margin:0}
.meta small{display:block}
.totals{margin-top:12px}
.print-btn{margin:12px 0}
@media print{ .noprint{ display:none!important; } body{ background:#fff; color:#000; } }
</style>
</head>
<body>
<div class="receipt">
  <div class="header">
    <div>
      <h2>Purchase Receipt</h2>
      <small>Receipt No: <strong><?= h($receipt_no) ?></strong></small>
      <small>Date: <?= h($header['purchase_date']) ?></small>
    </div>
    <div class="meta">
      <small><strong>Supplier:</strong> <?= h($header['supplier_name']) ?></small>
      <?php if(!empty($header['phone'])): ?><small><strong>Phone:</strong> <?= h($header['phone']) ?></small><?php endif; ?>
    </div>
  </div>

  <table>
    <thead><tr><th>#</th><th>Item</th><th>Qty</th><th>Price</th><th>Total</th></tr></thead>
    <tbody>
    <?php $i=1; foreach($items as $it): ?>
      <tr>
        <td><?= $i++ ?></td>
        <td><?= h($it['product_name']) ?></td>
        <td><?= (int)$it['qty'] ?></td>
        <td>$<?= number_format((float)$it['price'],2) ?></td>
        <td>$<?= number_format((float)$it['line_total'],2) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <div class="totals">
    <p><strong>Subtotal:</strong> $<?= number_format($subtotal,2) ?></p>
    <p><strong>Paid:</strong> $<?= number_format($paid,2) ?></p>
    <p><strong>Balance:</strong> $<?= number_format($due,2) ?></p>
  </div>

  <div class="noprint">
    <button class="print-btn" onclick="window.print()">🖨️ Print</button>
    <a href="purchases.php">Back to purchases</a>
  </div>
</div>
</body>
</html>
