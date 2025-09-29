<?php
$page = '3arz_system.php'; // For the sidebar/header active state
require_once 'header.php';
require_once '../inc/auth.php';
require_once '../inc/config.php';

$page_title = "3arz Menu";
?>

<div class="card">
    <div class="card-header">
        <h2 style="margin:0;" dir="rtl">بەشەکانی عەرز</h2>
    </div>
    <div style="padding: 30px; display: flex; gap: 20px; flex-wrap: wrap; justify-content: center; align-items: center;">
        
        <a href="manual_receipt_new.php" class="btn" style="padding: 20px 30px; font-size: 1.1em; text-align: center;">
            <div style="font-size: 2em; margin-bottom: 8px;">➕</div>
            عەرزی نوێ 
        </a>
        
        <a href="manual_receipts.php" class="btn" style="padding: 20px 30px; font-size: 1.1em; text-align: center;">
            <div style="font-size: 2em; margin-bottom: 8px;">📋</div>
            وەسڵی عەرز  
        </a>

    </div>
</div>

<?php require_once 'footer.php'; ?>
