<?php
require __DIR__.'/inc/config.php';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { die("Invalid sale id"); }

// Fetch sale, customer
$h = $pdo->prepare("SELECT s.*, c.name AS customer_name, c.phone FROM sales s JOIN customers c ON c.id=s.customer_id WHERE s.id=?");
$h->execute([$id]);
$header = $h->fetch();
if (!$header) { die("Sale not found"); }

// Fetch items
$it = $pdo->prepare("SELECT si.*, p.name AS product_name FROM sale_items si JOIN products p ON p.id=si.product_id WHERE si.sale_id=?");
$it->execute([$id]);
$items = $it->fetchAll();

// Derived fields
$subtotal = (float)$header['subtotal'];
$paid = (float)$header['paid'];
$due = max(0, $subtotal - $paid);
$receipt_no = 'INV-' . date('Ymd', strtotime($header['sale_date'])) . '-' . $header['id'];

?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Receipt <?= h($receipt_no) ?></title>
<link rel="stylesheet" href="assets/styles.css">
<style>
.receipt{max-width:800px;margin:0 auto;background:#fff;color:#000;padding:16px;border:1px solid #ccc;border-radius:8px}
.header{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:1px solid #999;padding-bottom:8px;margin-bottom:12px}
.header h2{margin:0}
.meta small{display:block}
.totals{margin-top:12px}
.print-btn{margin:12px 0}
</style>
</head>
<body>
<div class="receipt">
  <div class="header">
    <div>
      <h2>Sales Receipt</h2>
      <small>Receipt No: <strong><?= h($receipt_no) ?></strong></small>
      <small>Date: <?= h($header['sale_date']) ?></small>
    </div>
    <div class="meta">
      <small><strong>Customer:</strong> <?= h($header['customer_name']) ?></small>
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
  <?php if (!empty($header['note'])): ?>
  <div style="margin:10px 0; padding:10px; border:1px dashed #999; border-radius:8px; background:#fafafa;">
    <strong>Note:</strong>
    <div><?= h($header['note']) ?></div>
  </div>
<?php endif; ?>


  <div class="totals">
    <p><strong>Subtotal:</strong> $<?= number_format($subtotal,2) ?></p>
    <p><strong>Paid now:</strong> $<?= number_format($paid,2) ?></p>
    <p><strong>Amount Due:</strong> $<?= number_format($due,2) ?></p>
    
  </div>

  <p><em>Thank you!</em></p>
  <div class="noprint">
    <button class="print-btn" onclick="window.print()">🖨️ Print</button>
    <a href="customer_view.php?id=<?= (int)$header['customer_id'] ?>">Back to customer</a>
  </div>
</div>
</body>
</html>
