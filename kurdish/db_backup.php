<?php
// Core includes - needed for authentication and database connection
require_once  '../inc/auth.php';
require_once '../inc/config.php';


// This part of the code displays the page itself.
$page = 'db_backup.php';
require_once __DIR__ . '/header.php';

$page_title = "Database Backup";
?>

<div class="card">
    <div class="card-header">
        <h2 style="margin:0;" dir="rtl">پاشەکەوتکردنی دەیتابەیس</h2>
    </div>
    
    <div style="padding: 20px;">
        <p dir="rtl">ئەم ئامرازە ڕێگەت پێدەدات پاشەکەوتێکی تەواو لە دەیتابەیسەکەت دروست بکەیت.</p>
        <p style="margin-top: 10px;" dir="rtl">کلیککردن لەسەر دوگمەی خوارەوە فایلێکی  <strong>.sql</strong> دروست دەکات کە هەموو داتاکانتان لەخۆدەگرێت و دایبەزێنە بۆ کۆمپیوتەرەکەت</p>
        <p style="margin-top: 10px;" dir="rtl"><strong>پێشنیار دەکەین کە بە بەردەوامی پاشەکەوتەکان ئەنجام بدەیت و فایلە دابەزێنراوەکە لە شوێنێکی پارێزراو و جیاوازدا هەڵبگریت.</strong></p>
        
        <div style="margin-top: 25px; text-align: center;">
            <div style="margin-top: 25px; text-align: center;">
            <a href="../download_backup.php" class="btn btn-primary" >پاشەکەوتکردنی دەیتابەیس  </a>
        </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
