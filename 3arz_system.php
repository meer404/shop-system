<?php
$page = '3arz_system.php'; // For the sidebar/header active state
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/config.php';

$page_title = "3arz Menu";
require __DIR__ . '/inc/header.php'; // Sets the title inside the page content
?>

<div class="card">
    <div class="card-header">
        <h2 style="margin:0;">3arz Control Panel</h2>
    </div>
    <div style="padding: 30px; display: flex; gap: 20px; flex-wrap: wrap; justify-content: center; align-items: center;">
        
        <a href="manual_receipt_new.php" class="btn" style="padding: 20px 30px; font-size: 1.1em; text-align: center;">
            <div style="font-size: 2em; margin-bottom: 8px;">➕</div>
            New 3arz
        </a>
        
        <a href="manual_receipts.php" class="btn" style="padding: 20px 30px; font-size: 1.1em; text-align: center;">
            <div style="font-size: 2em; margin-bottom: 8px;">📋</div>
            View All Receipts
        </a>
        
        
        
        
        
    </div>
</div>

<?php 
require __DIR__ . '/inc/footer.php'; 
require_once __DIR__ . '/footer.php'; 
?>