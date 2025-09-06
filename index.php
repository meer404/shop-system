<?php $page = 'index.php'; require_once __DIR__ . '/header.php'; require_once __DIR__ . '/inc/auth.php';?>
<?php
require __DIR__.'/inc/config.php'; $page_title="Dashboard"; require __DIR__.'/inc/header.php';
$total_customers = $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
$total_products  = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$total_sales     = $pdo->query("SELECT COUNT(*) FROM sales")->fetchColumn();
$total_payments  = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments")->fetchColumn();
$balances = $pdo->query("SELECT * FROM v_customer_balance ORDER BY balance DESC")->fetchAll();
?>
<div class="card">
  <h2>Overview</h2>
  <div class="form-row">
    <span class="badge">Customers: <?= (int)$total_customers ?></span>
    <span class="badge">Products: <?= (int)$total_products ?></span>
    <span class="badge">Sales: <?= (int)$total_sales ?></span>
    <span class="badge">Payments: $<?= number_format((float)$total_payments,2) ?></span>
  </div>
</div>
<div class="card">
  <h3>Customer Balances</h3>
  <table><thead><tr><th>Customer</th><th>Total Purchased</th><th>Total Paid</th><th>Balance</th><th>Actions</th></tr></thead><tbody>
  <?php foreach($balances as $b): ?>
    <tr>
      <td><?= h($b['name']) ?></td>
      <td>$<?= number_format((float)$b['total_purchased'],2) ?></td>
      <td>$<?= number_format((float)$b['total_paid'],2) ?></td>
      <td class="<?= $b['balance']>0?'danger':'' ?>">$<?= number_format((float)$b['balance'],2) ?></td>
      <td class="actions">
        <a href="customer_view.php?id=<?= (int)$b['customer_id'] ?>">View</a>
        <a href="payments.php?customer_id=<?= (int)$b['customer_id'] ?>">Add Payment</a>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table>
</div>
<?php require __DIR__.'/inc/footer.php'; ?>
