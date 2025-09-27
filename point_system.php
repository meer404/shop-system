<?php
$page = 'point_system.php'; // For the sidebar/header active state
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/config.php';

$page_title = "Points System Menu";
require __DIR__ . '/inc/header.php'; // Sets the title inside the page content
?>

<div class="card">
    <div class="card-header">
        <h2 style="margin:0;">Points System Control Panel</h2>
    </div>
    <div style="padding: 30px; display: flex; gap: 20px; flex-wrap: wrap; justify-content: center; align-items: center;">
        
        <a href="point_items.php" class="btn" style="padding: 20px 30px; font-size: 1.1em; text-align: center;">
            <div style="font-size: 2em; margin-bottom: 8px;">⚙️</div>
            Manage Point Items
        </a>
        
        <a href="point_receipt_new.php" class="btn" style="padding: 20px 30px; font-size: 1.1em; text-align: center;">
            <div style="font-size: 2em; margin-bottom: 8px;">➕</div>
            New Point Receipt
        </a>
        
        <a href="point_receipts.php" class="btn" style="padding: 20px 30px; font-size: 1.1em; text-align: center;">
            <div style="font-size: 2em; margin-bottom: 8px;">📋</div>
            View All Receipts
        </a>
        
    </div>
</div>

<?php 
require __DIR__ . '/inc/footer.php'; 
require_once __DIR__ . '/footer.php'; 
?>