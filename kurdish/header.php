<?php
// --- Active link helper ---
$page = $page ?? basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) ?: 'index.php';
function is_active($needle){ global $page; return (strpos($page, $needle) !== false) ? ' class="active"' : ''; }

if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>کڕین & فرۆشتن Manager</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- relative path so it works in any subfolder; cache-bust -->
  <link href="styles.css?v=9" rel="stylesheet">
</head>
<body>
<div class="app">
  <aside class="sidebar">
    <h2 class="logo">📊 Manager</h2>

    <nav>
      <a href="index.php"<?=is_active('index.php')?>><span>🏠</span><b>داشبۆرد</b></a>
      <a href="customers.php"<?=is_active('customers.php')?>><span>👤</span><b>كڕیاران</b></a>
      <a href="products.php"<?=is_active('products.php')?>><span>📦</span><b>کالاكان</b></a>
      <a href="sale_new.php"<?=is_active('sale_new.php')?>><span>🧾</span><b>New فرۆشتن</b></a>
      <a href="purchases.php"<?=is_active('purchases.php')?>><span>📥</span><b>کڕین</b></a>
      <a href="payments.php"<?=is_active('payments.php')?>><span>💰</span><b>پارەدانەکان</b></a>
      <a href="receipts.php"<?=is_active('receipts.php')?>><span>🖨️</span><b>وەسڵەکان</b></a>
      <a href="suppliers.php"<?=is_active('suppliers.php')?>><span>🏭</span><b>دابینکەرەکان</b></a>
      <a href="purchase_new.php"<?=is_active('purchase_new.php')?>><span>🛒</span><b>Buy Items</b></a>
      <a href="stats.php"<?=is_active('stats.php')?>><span>📈</span><b>Stats</b></a>

      <div class="dropdown lang-dropdown">
      <a href="#" class="dropdown-toggle"><span>🌐</span><b>Language</b></a>
      <div class="dropdown-menu">
        <a href="../"><b>English</b></a>
        <a href="./"><b>کوردی</b></a>
      </div>
    </div>
    </nav>

    <!-- bottom area (sticks to bottom thanks to flex) -->
    <div class="sidebar-bottom">
      <?php if (!empty($_SESSION['user_id'])): ?>
        <div class="user-row">
          <span class="badge">👤 <?= htmlspecialchars($_SESSION['username']) ?></span>
        </div>
        <a href="logout.php" class="logout-btn"><span>🚪</span><b>چوونەدەرەوە</b></a>
      <?php else: ?>
        <a href="login.php" class="login-btn"><span>🔑</span><b>چوونەژوورەوە</b></a>
      <?php endif; ?>
    </div>
  </aside>

  <main class="content">
