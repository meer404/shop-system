<?php
// Core includes - needed for authentication and database connection
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/config.php';

$page = 'db_backup.php';
require_once __DIR__ . '/header.php';

$page_title = "Database Backup";
?>

<div class="card">
    <div class="card-header">
        <h2 style="margin:0;">Database Backup</h2>
    </div>
    
    <div style="padding: 20px;">
        <p>This tool allows you to create a complete backup of your application's database.</p>
        <p style="margin-top: 10px;">Clicking the button below will generate a single <strong>.sql</strong> file containing all your data  and download it to your computer.</p>
        <p style="margin-top: 10px;"><strong>It is highly recommended to perform backups regularly and store the downloaded file in a secure, separate location.</strong></p>
        
        <div style="margin-top: 25px; text-align: center;">
            <a href="download_backup.php" class="btn btn-primary" >Download Database Backup</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
