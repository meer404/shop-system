<?php
// --- Active link helper ---
$page = $page ?? basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) ?: 'index.php';
function is_active($needle){ global $page; return (strpos($page, $needle) !== false) ? ' class="active"' : ''; }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Purchase & Sales Manager</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- relative path so it works in any subfolder; cache-bust -->
  <link href="styles.css?v=8" rel="stylesheet">
</head>
<body>
<div class="app">
  <aside class="sidebar">
    <h2 class="logo">📊 Manager</h2>
    <nav>
      <a href="index.php"<?=is_active('index.php')?>><span>🏠</span><b>Dashboard</b></a>
      <a href="customers.php"<?=is_active('customers.php')?>><span>👤</span><b>Customers</b></a>
      <a href="products.php"<?=is_active('products.php')?>><span>📦</span><b>Products</b></a>
      <a href="sale_new.php"<?=is_active('sale_new.php')?>><span>🧾</span><b>New Sale</b></a>
      <a href="purchases.php"<?=is_active('purchases.php')?>><span>📥</span><b>Purchases</b></a>
      <a href="payments.php"<?=is_active('payments.php')?>><span>💰</span><b>Payments</b></a>
      <a href="receipts.php"<?=is_active('receipts.php')?>><span>🖨️</span><b>Receipts</b></a>
      <a href="suppliers.php"<?=is_active('suppliers.php')?>><span>🏭</span><b>Suppliers</b></a>
    </nav>
  </aside>
  <main class="content">
