<?php
// Test if manual_receipt.php is accessible
$id = $_GET['id'] ?? 1;
header("Location: manual_receipt.php?id=$id");
exit;