<?php
$page = 'point_system.php'; // For the sidebar/header active state
require_once  'header.php';
require_once  '../inc/auth.php';
require_once  '../inc/config.php';

$page_title = "Points System Menu";
?>

<div class="card">
    <div class="card-header">
        <h2 style="margin:0;" dir="rtl">بەشەکانی زەرعە</h2>
    </div>
    <div style="padding: 30px; display: flex; gap: 20px; flex-wrap: wrap; justify-content: center; align-items: center;">
        
        <a href="point_items.php" class="btn" style="padding: 20px 30px; font-size: 1.1em; text-align: center;">
            <div style="font-size: 2em; margin-bottom: 8px;">⚙️</div>
            زیادکردنی کاڵا
        </a>
        
        <a href="point_receipt_new.php" class="btn" style="padding: 20px 30px; font-size: 1.1em; text-align: center;">
            <div style="font-size: 2em; margin-bottom: 8px;">➕</div>
            زەرعەی نوێ
        </a>
        
        <a href="point_receipts.php" class="btn" style="padding: 20px 30px; font-size: 1.1em; text-align: center;">
            <div style="font-size: 2em; margin-bottom: 8px;">📋</div>
            وەسڵی زەرعەکان
        </a>

    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
