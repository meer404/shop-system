<?php
// --- Active link helper ---
$page = $page ?? basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) ?: 'index.php';
function is_active($needle){ global $page; return (strpos($page, $needle) !== false) ? ' class="active"' : ''; }

if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!doctype html>
<html lang="ku"  >
<head>
  <meta charset="utf-8">
  <title>Control Board</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- relative path so it works in any subfolder; cache-bust -->
  <link href="styles.css?v=9" rel="stylesheet">
</head>
<body>

  <button class="mobile-menu-toggle">☰</button>

<div class="app">
  <aside class="sidebar">
    <h2 class="logo">
      <img src="../images/new_logo.png" alt="Logo" style="width: 60px; height: 60px; vertical-align: middle;">
      Control Board
    </h2>

    <nav  dir="rtl">
      <a href="index.php"<?=is_active('index.php')?>><span>🏠</span><b>پەڕەی سەرەکی</b></a>
      <a href="customers.php"<?=is_active('customers.php')?>><span>👤</span><b>كڕیاران</b></a>
      <a href="products.php"<?=is_active('products.php')?>><span>📦</span><b>کالاكان</b></a>
      <a href="sale_system.php"<?=is_active('sale_system.php')?>><span>🧾</span><b> فرۆشتن</b></a>
      
      <a href="purchase_system.php"<?=is_active('purchase_system.php')?>><span>🛒</span><b>کڕین</b></a>
      
      <a href="3arz_system.php"<?=is_active('3arz_system.php')?>><span>📋</span><b> عەرز</b></a>
      <a href="point_system.php"<?=is_active('point_system.php')?> ><span>⭐</span><b> زەرعە</b></a>
      <a href="stats.php"<?=is_active('stats.php')?> ><span>📈</span><b>ئامار</b></a>
      <a href="db_backup.php"<?=is_active('db_backup.php')?>><span>💾</span><b>باکئەپ</b></a>

      <div class="dropdown lang-dropdown">
      <a href="#" class="dropdown-toggle"><span>🌐</span><b>زمانەکان</b></a>
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
