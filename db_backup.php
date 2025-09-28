<?php
// Core includes - needed for authentication and database connection
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/config.php';

// This block of code handles the backup generation and download when the button is clicked.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['download_backup'])) {
    
    // Set headers to force a download
    $filename = "backup-" . date('Y-m-d_H-i-s') . ".sql";
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    
    // Open the output stream
    $handle = fopen('php://output', 'w');
    
    // Write header info
    fwrite($handle, "-- Database Backup\n");
    fwrite($handle, "-- Host: " . $db_host . "\n");
    fwrite($handle, "-- Database: " . $db_name . "\n");
    fwrite($handle, "-- Generation Time: " . date('Y-m-d H:i:s') . "\n");
    fwrite($handle, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

    // Get all tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    // Loop through tables
    foreach ($tables as $table) {
        // Get table structure
        fwrite($handle, "DROP TABLE IF EXISTS `$table`;\n");
        $create_table_stmt = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
        fwrite($handle, $create_table_stmt['Create Table'] . ";\n\n");
        
        // Get table data
        $rows_stmt = $pdo->query("SELECT * FROM `$table`");
        $num_fields = $rows_stmt->columnCount();

        while ($row = $rows_stmt->fetch(PDO::FETCH_ASSOC)) {
            fwrite($handle, "INSERT INTO `$table` VALUES(");
            $values = [];
            foreach ($row as $value) {
                if (is_null($value)) {
                    $values[] = "NULL";
                } else {
                    // Escape special characters and wrap in single quotes
                    $values[] = "'" . addslashes($value) . "'";
                }
            }
            fwrite($handle, implode(', ', $values));
            fwrite($handle, ");\n");
        }
        fwrite($handle, "\n\n");
    }
    
    fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
    fclose($handle);
    
    // Stop the script from outputting anything else
    exit;
}


// This part of the code displays the page itself.
$page = 'backup.php';
require_once __DIR__ . '/header.php';

$page_title = "Database Backup";
require __DIR__ . '/inc/header.php';
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
            <a href="download_backup.php" class="btn" style="padding: 15px 30px; font-size: 1.2em;">
            💾 Download Database Backup
            </a>
        </div>
    </div>
</div>

<?php 
require __DIR__ . '/inc/footer.php'; 
require_once __DIR__ . '/footer.php'; 
?>