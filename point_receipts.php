<?php
/*
 * point_receipts.php
 * This file has dual functionality:
 * 1. If 'view_receipt' is in the URL, it displays a standalone, printable receipt and exits.
 * 2. Otherwise, it displays the list of all point receipts within the main site layout.
 */

require_once __DIR__ . '/inc/config.php'; // Needed for both modes
require_once __DIR__ . '/inc/auth.php';   // Needed for both modes

function safe($v){ return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

// --- MODE 1: VIEW A SINGLE RECEIPT (Standalone Page) ---
if (isset($_GET['view_receipt'])) {
    $receipt_id = (int)$_GET['view_receipt'];
    if ($receipt_id <= 0) die("Invalid receipt ID.");
    
    $stmt = $pdo->prepare("SELECT * FROM point_receipts WHERE id = ?");
    $stmt->execute([$receipt_id]);
    $receipt = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$receipt) die("Receipt not found!");
    
    $stmt = $pdo->prepare("SELECT * FROM point_receipt_items WHERE receipt_id = ? ORDER BY id");
    $stmt->execute([$receipt_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $createdAt = $receipt['created_at'] ?? date('Y-m-d H:i:s');
    ?>
    <!doctype html>
    <html lang="en" dir="ltr">
    <head>
      <meta charset="utf-t">
      <title>Point Receipt #<?= safe($receipt['receipt_no']) ?></title>
      <link href="styles.css?v=2" rel="stylesheet"> <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, Tahoma, sans-serif; background: #f9f7f4; color: #000; font-size: 14px; line-height: 1.4; padding: 20px; }
        .receipt-wrapper { max-width: 800px; margin: 0 auto; background: #fffef9; border: 2px solid #2c5282; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header-section { border-bottom: 2px solid #2c5282; padding-bottom: 15px; margin-bottom: 15px; background: linear-gradient(to bottom, #e6f2ff, #f0f7ff); padding: 15px; border-radius: 4px 4px 0 0; margin: -20px -20px 15px -20px; }
        .company-header { text-align: center; margin-bottom: 10px; }
        .company-header h1 { font-size: 24px; font-weight: bold; margin-bottom: 5px; color: #1a365d; text-shadow: 1px 1px 2px rgba(0,0,0,0.1); }
        .company-info { font-size: 12px; line-height: 1.6; color: #2c5282; }
        .receipt-details { display: flex; justify-content: space-between; margin-bottom: 20px; border: 1px solid #5a88b8; background: #f0f7ff; border-radius: 4px; }
        .detail-group { flex: 1; padding: 10px; border-right: 1px solid #5a88b8; }
        .detail-group:last-child { border-right: none; }
        .detail-label { font-weight: bold; color: #1a365d; display: inline-block; margin-right: 5px; }
        .detail-value { color: #2c5282; font-weight: 600; }
        .customer-section { display: flex; gap: 20px; margin-bottom: 20px; padding: 10px; border: 1px solid #5a88b8; background: #fdfcfa; border-radius: 4px; }
        .customer-field { flex: 1; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; border: 1px solid #2c5282; background: #fff; }
        .items-table thead { background: #ffd000ff; color: black; }
        .items-table th { border: 1px solid #2c5282; padding: 8px; text-align: center; font-weight: bold; font-size: 13px; }
        .items-table td { border: 1px solid #5a88b8; padding: 6px 8px; text-align: center; background: #fff; }
        .items-table tbody tr:nth-child(even) { background: #f7fafc; }
        .items-table .item-name { text-align: left; }
        .items-table .number-col { text-align: right; font-weight: 500; }
        .totals-section { text-align: right; margin-bottom: 20px; }
        .total-row { display: flex; justify-content: flex-end; padding: 8px 15px; background: #f0f7ff; border: 1px solid #5a88b8; border-radius: 4px; font-size: 1.2em; font-weight: bold; color: #1a365d; }
        .total-label { margin-right: 20px; }
        .note-section { border: 1px solid #5a88b8; padding: 10px; margin-bottom: 20px; background: #fef9e7; min-height: 60px; border-radius: 4px; }
        .note-label { font-weight: bold; margin-bottom: 5px; color: #1a365d; }
        .footer-section { margin-top: 30px; padding-top: 20px; border-top: 2px solid #2c5282; background: #f0f7ff; margin: 30px -20px -20px -20px; padding: 20px; }
        .print-info { text-align: center; font-size: 11px; color: #4a5568; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid #cbd5e0; }
        .signatures { display: flex; justify-content: space-around; margin-top: 20px; }
        .signature-box { text-align: center; min-width: 200px; }
        .signature-line { border-bottom: 2px solid #2c5282; margin-bottom: 5px; min-height: 40px; }
        .signature-label { font-size: 12px; color: #1a365d; font-weight: bold; }
        .print-actions { text-align: center; margin: 20px 0; }
        .print-actions button, .print-actions a { padding: 8px 16px; margin: 0 5px; background: #3182ce; color: white; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; font-weight: bold; }
        .print-actions button:hover, .print-actions a:hover { background: #2c5282; }
        .bilingual { display: flex; justify-content: space-between; align-items: center; }
        @media print {
          body { padding: 0; background: #fff; }
          .receipt-wrapper { border: 1px solid #000; max-width: 100%; box-shadow: none; }
          .print-actions { display: none; }
          .items-table th, .items-table td { border: 1px solid #000; }
          .header-section, .footer-section { margin-left: 0; margin-right: 0; }
        }
      </style>
    </head>
    <body>
      <div class="receipt-wrapper">
        <div class="header-section">
          <div class="company-header"><h1>پسوڵەی خاڵ</h1><div class="bilingual"><img src="images/Logo.png" alt="Company Logo" style="max-width: 150px;"><div class="company-info">Control Board<br>سلێمانی - چوارباخ خوار فولکەی مامە ڕیشە<br>Phone: 07732828287 - 00722142666 <br>Email: jumaarasoul3@gmail.com</div></div></div>
        </div>
        <div class="receipt-details">
          <div class="detail-group"><span class="detail-label">Date:</span><span class="detail-value"><?= safe(date('d/m/Y', strtotime($receipt['receipt_date']))) ?></span></div>
          <div class="detail-group"><span class="detail-label">Receipt Type:</span><span class="detail-value">Points-Based</span></div>
          <div class="detail-group"><span class="detail-label">Receipt No:</span><span class="detail-value"><?= safe($receipt['receipt_no']) ?></span></div>
        </div>
        <div class="customer-section">
          <div class="customer-field"><span class="detail-label">Receipt Name:</span><span class="detail-value"><?= safe($receipt['receipt_name'] ?? '-') ?></span></div>
          <div class="customer-field"><span class="detail-label">Phone:</span><span class="detail-value"><?= safe($receipt['phone'] ?? '-') ?></span></div>
          <div class="customer-field"><span class="detail-label">Place:</span><span class="detail-value"><?= safe($receipt['place'] ?? '-') ?></span></div>
        </div>
        <table class="items-table">
            <thead><tr><th width="5%">#</th><th width="30%">Item</th><th width="10%">Qty</th><th width="13%">Points/Unit</th><th width="13%">Price/Point</th><th width="14%">Total Points</th><th width="15%">Line Total</th></tr></thead>
            <tbody>
                <?php $i=1; foreach ($items as $item): ?>
                <tr><td><?= $i++ ?></td><td class="item-name"><?= safe($item['item_name']) ?></td><td class="number-col"><?= number_format((float)$item['qty'], 2) ?></td><td class="number-col"><?= number_format((float)$item['item_points'], 2) ?></td><td class="number-col">$<?= number_format((float)$item['item_price'], 2) ?></td><td class="number-col"><?= number_format((float)$item['total_points'], 2) ?></td><td class="number-col"><strong>$<?= number_format((float)$item['line_total'], 2) ?></strong></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="totals-section"><div class="total-row"><span class="total-label">Grand Total:</span><span class="total-value">$<?= number_format((float)$receipt['grand_total'], 2) ?></span></div></div>
        <?php if (!empty($receipt['note'])): ?><div class="note-section"><div class="note-label">Note:</div><div><?= nl2br(safe($receipt['note'])) ?></div></div><?php endif; ?>
        <div class="footer-section">
          <div class="print-info">Page 1 of 1 | Time <?= date('d/m/Y h:i:s A') ?> | Print By: Admin</div>
          <div class="signatures"><div class="signature-box"><div class="signature-line"></div><div class="signature-label">Accountant</div></div><div class="signature-box"><div class="signature-line"></div><div class="signature-label">Customer Signature</div></div></div>
        </div>
      </div>
      <div class="print-actions"><button onclick="window.print()">Print Receipt</button><a href="point_receipts.php">All Point Receipts</a><a href="index.php">Dashboard</a></div>
    </body>
    </html>
    <?php
    exit; // IMPORTANT: Stop the script here for receipt view.
}


// --- MODE 2: LIST ALL RECEIPTS (Main Page View) ---

// This part will only run if we are NOT viewing a single receipt.
$page = 'point_receipts.php';
require_once __DIR__ . '/header.php'; // Include the main site header

// --- DELETE ACTION (for list view) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    try {
        $id = (int)$_POST['delete_id'];
        if ($id > 0) {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM point_receipt_items WHERE receipt_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM point_receipts WHERE id = ?")->execute([$id]);
            $pdo->commit();
            $_SESSION['flash_success'] = "Receipt #$id deleted.";
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['flash_error'] = "Delete failed: " . $e->getMessage();
    }
    // Redirect to the same page to show the list and the message
    echo "<script>window.location.href='point_receipts.php';</script>";
    exit;
}

// --- Filtering and Data Fetching for the List ---
$q = trim($_GET['q'] ?? '');
$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');

$where = [];
$args = [];
if ($q !== '') {
    $where[] = "(receipt_no LIKE ? OR receipt_name LIKE ? OR phone LIKE ? OR note LIKE ?)";
    array_push($args, "%$q%", "%$q%", "%$q%", "%$q%");
}
if ($from !== '') { $where[] = "receipt_date >= ?"; $args[] = $from; }
if ($to !== '') { $where[] = "receipt_date <= ?"; $args[] = $to; }
$whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

$sql = "SELECT * FROM point_receipts $whereSql ORDER BY receipt_date DESC, id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($args);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$flash_success = $_SESSION['flash_success'] ?? '';
$flash_error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$page_title = "Point Receipts";
require __DIR__ . '/inc/header.php'; // This is for the page title in the header
?>

<div class="card">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
        <h2 style="margin:0;">Point-Based Receipts</h2>
        <a href="point_receipt_new.php" class="btn">+ New Point Receipt</a>
    </div>

    <?php if ($flash_success): ?><p class="success"><?= safe($flash_success) ?></p><?php endif; ?>
    <?php if ($flash_error): ?><p class="danger"><?= safe($flash_error) ?></p><?php endif; ?>

    <form method="get" class="form-row" style="margin:12px 0;">
        <input type="text" name="q" value="<?= safe($q) ?>" placeholder="Search name, phone, note...">
        <input type="date" name="from" value="<?= safe($from) ?>">
        <input type="date" name="to" value="<?= safe($to) ?>">
        <button type="submit" class="btn">Filter</button>
        <a href="point_receipts.php" class="badge">Reset</a>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>#</th><th>Receipt No</th><th>Date</th><th>Name</th><th>Phone</th><th>Total</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="7" style="text-align:center">No point receipts found.</td></tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr>
                        <td><?= (int)$r['id'] ?></td>
                        <td><span class="badge info"><?= safe($r['receipt_no']) ?></span></td>
                        <td><?= safe($r['receipt_date']) ?></td>
                        <td><?= safe($r['receipt_name']) ?></td>
                        <td><?= safe($r['phone']) ?></td>
                        <td><strong>$<?= number_format((float)$r['grand_total'], 2) ?></strong></td>
                        <td class="actions" style="display:flex;gap:8px;flex-wrap:wrap;">
                            <a href="?view_receipt=<?= (int)$r['id'] ?>"  class="btn btn-small">View</a>
                            <form method="post" onsubmit="return confirm('Delete this receipt? This cannot be undone.');" style="display:inline; margin:0;">
                                <input type="hidden" name="delete_id" value="<?= (int)$r['id'] ?>">
                                <button class="btn btn-small btn-danger" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php 
require __DIR__ . '/inc/footer.php'; 
require_once __DIR__ . '/footer.php'; 
?>