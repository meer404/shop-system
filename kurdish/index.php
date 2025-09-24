<?php $page = 'index.php'; require_once 'header.php'; require_once '../inc/auth.php';?>
<?php
require '../inc/config.php'; $page_title="داشبۆرد"; require '../inc/header.php';
$total_customers = $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
$total_products  = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$total_sales     = $pdo->query("SELECT COUNT(*) FROM sales")->fetchColumn();
$total_payments  = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments")->fetchColumn();
$balances = $pdo->query("SELECT * FROM v_customer_balance ORDER BY balance DESC")->fetchAll();
?>
<div class="card">
  <h2> گشتی</h2>
  <div class="form-row">
    <span class="badge">كڕیاران: <?= (int)$total_customers ?></span>
    <span class="badge">کالاكان: <?= (int)$total_products ?></span>
    <span class="badge">فرۆشتن: <?= (int)$total_sales ?></span>
    <span class="badge">پارەدانەکان: $<?= number_format((float)$total_payments,2) ?></span>
  </div>
</div>
<div class="card">
  <h3>كڕیارەکان</h3>
  <table><thead><tr><th>كڕیار</th><th>کۆی گشتی کڕین</th><th>کۆی گشتی پارەی دراو</th><th>قەرز</th><th>کردارەکان</th></tr></thead><tbody>
  <?php foreach($balances as $b): ?>
    <tr>
      <td><?= h($b['name']) ?></td>
      <td>$<?= number_format((float)$b['total_purchased'],2) ?></td>
      <td>$<?= number_format((float)$b['total_paid'],2) ?></td>
      <td class="<?= $b['balance']>0?'danger':'' ?>">$<?= number_format((float)$b['balance'],2) ?></td>
      <td class="actions">
        <a href="customer_view.php?id=<?= (int)$b['customer_id'] ?>">بینین</a>
        <a href="payments.php?customer_id=<?= (int)$b['customer_id'] ?>"> پارەدان</a>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table>
</div>
<?php require '../inc/footer.php'; ?>
