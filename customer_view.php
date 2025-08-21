<?php
require __DIR__.'/inc/config.php';
$id=isset($_GET['id'])?(int)$_GET['id']:0; if($id<=0) die("Invalid id");
$c=$pdo->prepare("SELECT * FROM customers WHERE id=?"); $c->execute([$id]); $customer=$c->fetch(); if(!$customer) die("Customer not found");
$bal=$pdo->prepare("SELECT * FROM v_customer_balance WHERE customer_id=?"); $bal->execute([$id]); $balance=$bal->fetch();
$items=$pdo->prepare("SELECT s.id AS sale_id, s.sale_date, si.qty, si.price, si.line_total, p.name AS product_name FROM sales s JOIN sale_items si ON si.sale_id=s.id JOIN products p ON p.id=si.product_id WHERE s.customer_id=? ORDER BY s.sale_date DESC, s.id DESC"); $items->execute([$id]); $sale_items=$items->fetchAll();
$p=$pdo->prepare("SELECT * FROM payments WHERE customer_id=? ORDER BY paid_at DESC, id DESC"); $p->execute([$id]); $payments=$p->fetchAll();
$page_title="Customer View - ".$customer['name']; require __DIR__.'/inc/header.php'; ?>
<div class="card">
  <h2><?= h($customer['name']) ?></h2>
  <p>Phone: <?= h($customer['phone']) ?></p>
  <div class="form-row">
    <span class="badge">Total Purchased: $<?= number_format((float)($balance['total_purchased'] ?? 0),2) ?></span>
    <span class="badge">Total Paid: $<?= number_format((float)($balance['total_paid'] ?? 0),2) ?></span>
    <span class="badge <?= ($balance['balance'] ?? 0)>0?'danger':'success' ?>">Balance: $<?= number_format((float)($balance['balance'] ?? 0),2) ?></span>
  </div>
</div>
<div class="card">
  <h3>Purchased Items</h3>
  <table><thead><tr><th>Sale #</th><th>Date</th><th>Product</th><th>Qty</th><th>Price</th><th>Line Total</th><th>Receipt</th></tr></thead><tbody>
  <?php foreach($sale_items as $si): ?>
    <tr>
      <td><?= (int)$si['sale_id'] ?></td>
      <td><?= h($si['sale_date']) ?></td>
      <td><?= h($si['product_name']) ?></td>
      <td><?= (int)$si['qty'] ?></td>
      <td>$<?= number_format((float)$si['price'],2) ?></td>
      <td>$<?= number_format((float)$si['line_total'],2) ?></td>
      <td><a href="sale_receipt.php?id=<?= (int)$si['sale_id'] ?>">Receipt</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table>
</div>
<div class="card">
  <h3>Payments</h3>
  <table><thead><tr><th>#</th><th>Amount</th><th>When</th><th>Note</th></tr></thead><tbody>
  <?php foreach($payments as $pm): ?>
    <tr>
      <td><?= (int)$pm['id'] ?></td>
      <td>$<?= number_format((float)$pm['amount'],2) ?></td>
      <td><?= h($pm['paid_at']) ?></td>
      <td><?= h($pm['note']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table>
</div>
<?php require __DIR__.'/inc/footer.php'; ?>
