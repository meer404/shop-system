<?php
$page = '3arz_system.php';
// header.php is no longer required here. It will be loaded later for the list view.
require_once '../inc/auth.php';
require_once '../inc/config.php';

/* ---- Normalize PDO handle ($pdo) ---- */
if (!isset($pdo) || !($pdo instanceof PDO)) {
  if (isset($conn) && ($conn instanceof PDO)) {
    $pdo = $conn; // fallback if your config defines $conn
  } else {
    http_response_code(500);
    die('Database connection missing: $pdo (PDO) is not defined.');
  }
}

/* ---- Helpers ---- */
function safe($v){ return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

/* ---- Delete (optional) ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
  try {
    $id = (int)$_POST['delete_id'];
    if ($id > 0) {
      $stmt = $pdo->prepare("DELETE FROM manual_receipts WHERE id = ?");
      $stmt->execute([$id]);
      $_SESSION['flash_success'] = "پسوڵە #$id سڕایەوە.";
    }
  } catch (Throwable $e) {
    $_SESSION['flash_error'] = "سڕینەوە سەرکەوتوو نەبوو: ".$e->getMessage();
  }
  header("Location: manual_receipts.php");
  exit;
}

/* ---- View Receipt (Alternative method) ---- */
// This part is self-contained and generates a full HTML page.
if (isset($_GET['view_receipt'])) {
  $receipt_id = (int)$_GET['view_receipt'];

  // Fetch receipt data
  $stmt = $pdo->prepare("
    SELECT mr.*,
           COALESCE(SUM(mri.line_total), 0) as total
    FROM manual_receipts mr
    LEFT JOIN manual_receipt_items mri ON mri.receipt_id = mr.id
    WHERE mr.id = ?
    GROUP BY mr.id
  ");
  $stmt->execute([$receipt_id]);
  $receipt = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$receipt) {
    die("پسوڵە نەدۆزرایەوە!");
  }

  // Fetch receipt items
  $stmt = $pdo->prepare("
    SELECT * FROM manual_receipt_items
    WHERE receipt_id = ?
    ORDER BY id
  ");
  $stmt->execute([$receipt_id]);
  $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Display receipt - UI Updated to match sale_receipt.php
  ?>
  <!DOCTYPE html>
  <html lang="ku" dir="rtl">
  <head>
    <meta charset="utf-8">
    <title>پسوڵەی دەستی #<?= safe($receipt['receipt_no']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
    /* --- Copied styles from sale_receipt.php --- */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: Arial, Tahoma, sans-serif;
      background: #f9f7f4;
      color: #000;
      font-size: 14px;
      line-height: 1.4;
      padding: 20px;
    }

    .receipt-wrapper {
      max-width: 800px;
      margin: 0 auto;
      background: #fffef9;
      border: 2px solid #2c5282;
      padding: 20px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }

    /* Header Section */
    .header-section {
      border-bottom: 2px solid #2c5282;
      padding-bottom: 15px;
      margin-bottom: 15px;
      background: linear-gradient(to bottom, #e6f2ff, #f0f7ff);
      padding: 15px;
      border-radius: 4px 4px 0 0;
      margin: -20px -20px 15px -20px;
    }

    .company-header {
      text-align: center;
      margin-bottom: 10px;
    }

    .company-header h1 {
      font-size: 24px;
      font-weight: bold;
      margin-bottom: 5px;
      color: #1a365d;
      text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
    }

    .company-info {
      font-size: 12px;
      line-height: 1.6;
      color: #2c5282;
    }

    /* Receipt Details */
    .receipt-details {
      display: flex;
      justify-content: space-between;
      margin-bottom: 20px;
      border: 1px solid #5a88b8;
      background: #f0f7ff;
      border-radius: 4px;
    }

    .detail-group {
      flex: 1;
      padding: 10px;
      border-left: 1px solid #5a88b8;
    }

    .detail-group:first-child {
      border-left: none;
    }

    .detail-label {
      font-weight: bold;
      color: #1a365d;
      display: inline-block;
      margin-right: 5px;
    }

    .detail-value {
      color: #2c5282;
      font-weight: 600;
    }

    /* Items Table */
    .items-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 20px;
      border: 1px solid #2c5282;
      background: #fff;
    }

    .items-table thead {
      background: #ffd000ff;
      color: black;
    }

    .items-table th {
      border: 1px solid #2c5282;
      padding: 8px;
      text-align: center;
      font-weight: bold;
      font-size: 13px;
    }

    .items-table td {
      border: 1px solid #5a88b8;
      padding: 6px 8px;
      text-align: center;
      background: #fff;
    }

    .items-table tbody tr:nth-child(even) {
      background: #f7fafc;
    }

    .items-table .item-name {
      text-align: right;
    }

    .items-table .number-col {
      text-align: center;
      font-weight: 500;
    }

    /* Totals Section */
    .totals-section {
      display: flex;
      justify-content: flex-start; /* Align to the left for RTL */
      margin-bottom: 20px;
    }

    .totals-box {
      flex: 0 1 50%; /* Take up half the width */
      border: 1px solid #5a88b8;
      padding: 15px;
      background: linear-gradient(to bottom, #fef9e7, #fffef9);
      border-radius: 4px;
    }

    .total-row {
      display: flex;
      justify-content: space-between;
      padding: 5px 0;
      border-bottom: 1px solid #d4d4d8;
    }

    .total-row:last-child {
      border-bottom: none;
      font-weight: bold;
      font-size: 16px;
      padding-top: 10px;
      color: #1a365d;
    }

    .total-label {
      font-weight: bold;
      color: #2c5282;
    }

    .total-value {
      text-align: left;
      min-width: 100px;
      color: #1a365d;
      font-weight: 600;
    }

    /* Note Section */
    .note-section {
      border: 1px solid #5a88b8;
      padding: 10px;
      margin-bottom: 20px;
      background: #fef9e7;
      min-height: 60px;
      border-radius: 4px;
    }

    .note-label {
      font-weight: bold;
      margin-bottom: 5px;
      color: #1a365d;
    }

    /* Footer Section */
    .footer-section {
      margin-top: 30px;
      padding-top: 20px;
      border-top: 2px solid #2c5282;
      background: #f0f7ff;
      margin: 30px -20px -20px -20px;
      padding: 20px;
    }

    .print-info {
      text-align: center;
      font-size: 11px;
      color: #4a5568;
      margin-bottom: 20px;
      padding-bottom: 10px;
      border-bottom: 1px solid #cbd5e0;
    }

    .signatures {
      display: flex;
      justify-content: space-around;
      margin-top: 20px;
    }

    .signature-box {
      text-align: center;
      min-width: 200px;
    }

    .signature-line {
      border-bottom: 2px solid #2c5282;
      margin-bottom: 5px;
      min-height: 40px;
    }

    .signature-label {
      font-size: 12px;
      color: #1a365d;
      font-weight: bold;
    }

    /* Print Actions */
    .print-actions {
      text-align: center;
      margin: 20px 0;
    }

    .print-actions button,
    .print-actions a {
      padding: 8px 16px;
      margin: 0 5px;
      background: #3182ce;
      color: white;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      text-decoration: none;
      display: inline-block;
      font-weight: bold;
    }

    .print-actions button:hover,
    .print-actions a:hover {
      background: #2c5282;
    }

    /* Print Styles */
    @media print {
      /* This rule forces browsers to print background colors and images */
      * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
      }

      body {
        padding: 0;
      }

      .receipt-wrapper {
        max-width: 100%;
        box-shadow: none;
        /* The original colored border will be used automatically */
      }

      .print-actions {
        display: none;
      }

      /* The original colored borders for the table and other elements will be used */

      .header-section {
        margin: -20px -20px 15px -20px;
      }

      .footer-section {
        margin: 30px -20px -20px -20px;
      }
    }

    .bilingual { display: flex; justify-content: space-between; align-items: center; }
  </style>
  </head>
  <body>
    <div class="receipt-wrapper">
      <div class="header-section">
        <div class="company-header">
          <h1>پسوڵەی عەرز</h1>
          <div class="bilingual">
             <img src="../images/new_logo.png" alt="Company Logo" style="max-width: 150px; ">
            <div class="company-info">
               CONTROL BOARD<br>
              سلێمانی - چوارباخ خوار فلکەی مامە ڕیشە<br>
              ژمارەی مۆبایل: 07732828287 - 00722142666 <br>
               ئیمەیڵ: jumaarasoul3@gmail.com
            </div>
          </div>
           <p>کۆمپانیایی CONTROL BOARD بۆ دروستکردنی بۆردی کارەبایی و دیزاین و جێبەجێکردنی پڕۆژەکان</p>
        </div>
      </div>

      <div class="receipt-details">
        <div class="detail-group">
          <span class="detail-label">بەروار:</span>
          <span class="detail-value"><?= safe(date('Y-m-d H:i', strtotime($receipt['created_at']))) ?></span>
        </div>
        <div class="detail-group">
          <span class="detail-label">ژمارەی پسوڵە:</span>
          <span class="detail-value">#<?= safe($receipt['receipt_no']) ?></span>
        </div>
      </div>

      <table class="items-table">
        <thead>
          <tr>
            <th style="width:36%">کاڵا</th>
            <th style="width:24%">براند</th>
            <th style="width:10%">بڕ</th>
            <th style="width:15%">نرخ</th>
            <th style="width:15%">کۆی گشتی</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($items): foreach ($items as $item): ?>
          <tr>
            <td class="item-name"><?= safe($item['item_name'] ?? 'N/A') ?></td>
            <td><?= safe($item['brand'] ?? '') ?></td>
            <td class="number-col"><?= safe($item['qty'] ?? 1) ?></td>
            <td class="number-col">$<?= number_format((float)($item['price'] ?? 0), 2) ?></td>
            <td class="number-col">$<?= number_format((float)($item['line_total'] ?? 0), 2) ?></td>
          </tr>
          <?php endforeach; else: ?>
          <tr>
            <td colspan="5">هیچ کاڵایەک لەم پسوڵەیەدا نییە.</td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>

      <div class="totals-section">
        <div class="totals-box">
          <div class="total-row">
            <span class="total-label">کۆ:</span>
            <span class="total-value">$<?= number_format((float)$receipt['total'], 2) ?></span>
          </div>
        </div>
      </div>

      <?php if (!empty($receipt['note'])): ?>
      <div class="note-section">
        <div class="note-label">تێبینی:</div>
        <div><?= nl2br(safe($receipt['note'])) ?></div>
      </div>
      <?php endif; ?>

      <div class="footer-section">
        <div class="print-info">
          لاپەڕە ١ لە ١ | کات <?= date('d/m/Y h:i:s A') ?> | عەرز 
        </div>
        <div class="signatures">
          <div class="signature-box">
            <div class="signature-line"></div>
            <div class="signature-label">ژمێریار</div>
          </div>
          <div class="signature-box">
            <div class="signature-line"></div>
            <div class="signature-label">تۆمارکەر</div>
          </div>
        </div>
      </div>
    </div>

    <div class="print-actions">
      <button onclick="window.print()">چاپکردنی پسوڵە</button>
      <a href="manual_receipts.php">گەڕانەوە بۆ لیست</a>
    </div>

    <script>
      // Auto-print if print parameter is set
      if (window.location.search.includes('print=1')) {
        window.print();
      }
    </script>
  </body>
  </html>
  <?php
  exit; // <-- This is important. It stops the script after showing the receipt.
}


// The code below is only for the list view.
// We include the header here, so it doesn't show on the receipt view.
require_once 'header.php';


/* ---- Filters & pagination ---- */
$q       = trim($_GET['q'] ?? '');
$from    = trim($_GET['from'] ?? '');
$to      = trim($_GET['to'] ?? '');
$minTot  = trim($_GET['min_total'] ?? '');
$maxTot  = trim($_GET['max_total'] ?? '');

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(100, max(5, (int)($_GET['per_page'] ?? 20)));
$offset  = ($page - 1) * $perPage;

$where = []; $wargs = [];
if ($q   !== '') { $where[]="(mr.receipt_no LIKE ? OR mr.note LIKE ?)"; $wargs[]="%$q%"; $wargs[]="%$q%"; }
if ($from!== '') { $where[]="mr.created_at >= ?"; $wargs[]="$from 00:00:00"; }
if ($to  !== '') { $where[]="mr.created_at <= ?"; $wargs[]="$to 23:59:59"; }
$whereSql = $where ? ("WHERE ".implode(" AND ", $where)) : "";

$having = []; $hargs = [];
if ($minTot !== '' && is_numeric($minTot)) { $having[]="COALESCE(SUM(mri.line_total),0) >= ?"; $hargs[]=(float)$minTot; }
if ($maxTot !== '' && is_numeric($maxTot)) { $having[]="COALESCE(SUM(mri.line_total),0) <= ?"; $hargs[]=(float)$maxTot; }
$havingSql = $having ? ("HAVING ".implode(" AND ", $having)) : "";

/* ---- Count ---- */
$sqlCount = "
SELECT COUNT(*) FROM (
  SELECT mr.id
  FROM manual_receipts mr
  LEFT JOIN manual_receipt_items mri ON mri.receipt_id = mr.id
  $whereSql
  GROUP BY mr.id
  $havingSql
) t";
$st = $pdo->prepare($sqlCount);
$st->execute(array_merge($wargs, $hargs));
$totalRows = (int)$st->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

/* ---- Page rows ---- */
$sql = "
SELECT mr.id, mr.receipt_no, mr.note, mr.created_at,
       COALESCE(SUM(mri.line_total),0) AS grand_total,
       COUNT(mri.id) AS item_count
FROM manual_receipts mr
LEFT JOIN manual_receipt_items mri ON mri.receipt_id = mr.id
$whereSql
GROUP BY mr.id
$havingSql
ORDER BY mr.created_at DESC, mr.id DESC
LIMIT $perPage OFFSET $offset";
$st = $pdo->prepare($sql);
$st->execute(array_merge($wargs, $hargs));
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

/* ---- Flash ---- */
$flash_success = $_SESSION['flash_success'] ?? '';
$flash_error   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$page_title = "پسوڵەی دەستی";

?>

<div class="card">
  <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:12px;">
    <h2 style="margin:0;display:flex;align-items:center;gap:8px;">📄 پسوڵەی دەستی</h2>
    <a href="manual_receipt_new.php" class="btn">+ پسوڵەی دەستی نوێ</a>
  </div>

  <?php if ($flash_success): ?><p class="success" style="margin-top:0;"><?= safe($flash_success) ?></p><?php endif; ?>
  <?php if ($flash_error):   ?><p class="danger"  style="margin-top:0;"><?= safe($flash_error) ?></p><?php endif; ?>

  <form method="get" action="manual_receipts.php" class="form-row" style="margin:12px 0; row-gap:10px;">
    <input type="text" name="q" value="<?= safe($q) ?>" placeholder="گەڕان بەدوای پسوڵە یان تێبینی">
    <input type="date" name="from" value="<?= safe($from) ?>" placeholder="لە بەرواری">
    <input type="date" name="to" value="<?= safe($to) ?>" placeholder="بۆ بەرواری">
    <input type="number" step="0.01" name="min_total" value="<?= safe($minTot) ?>" placeholder="کەمترین کۆ">
    <input type="number" step="0.01" name="max_total" value="<?= safe($maxTot) ?>" placeholder="زۆرترین کۆ">
    <select name="per_page">
      <option value="20"  <?= $perPage==20 ?'selected':'' ?>>٢٠ لە لاپەڕەیەک</option>
      <option value="50"  <?= $perPage==50 ?'selected':'' ?>>٥٠ لە لاپەڕەیەک</option>
      <option value="100" <?= $perPage==100?'selected':'' ?>>١٠٠ لە لاپەڕەیەک</option>
    </select>
    <button type="submit" class="btn">فلتەرکردن</button>
    <a href="manual_receipts.php" class="badge">ڕێکخستنەوە</a>
  </form>

  <div class="form-row" style="align-items:center;gap:10px;margin-bottom:4px;">
    <span class="badge info">کۆی پسوڵەکان: <strong><?= number_format($totalRows) ?></strong></span>
    <?php if ($totalPages > 1): ?>
      <span class="badge">لاپەڕە <?= $page ?> لە <?= $totalPages ?></span>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th style="width:6%">#</th>
          <th style="width:16%">ژمارەی پسوڵە</th>
          <th style="width:16%">بەروار</th>
          <th style="width:10%">کاڵاکان</th>
          <th style="width:14%">کۆ</th>
          <th>تێبینی</th>
          <th style="width:20%">کردارەکان</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="7" style="text-align:center">هیچ پسوڵەیەکی دەستی نەدۆزرایەوە.</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td><?= (int)$r['id'] ?></td>
          <td><span class="badge info"><?= safe($r['receipt_no']) ?></span></td>
          <td><?= safe(date('Y-m-d H:i', strtotime($r['created_at']))) ?></td>
          <td><span class="badge"><?= (int)$r['item_count'] ?></span></td>
          <td><strong>$<?= number_format((float)$r['grand_total'], 2) ?></strong></td>
          <td><?= safe(substr($r['note'], 0, 60)) ?><?= strlen($r['note']) > 60 ? '...' : '' ?></td>
          <td class="actions" style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="?view_receipt=<?= (int)$r['id'] ?>" class="btn btn-small">بینین</a>
            <a href="?view_receipt=<?= (int)$r['id'] ?>&print=1" target="_blank" class="btn btn-small">چاپکردن</a>
            <form method="post" onsubmit="return confirm('دڵنیایت لە سڕینەوەی ئەم پسوڵەیە؟');" style="display:inline; margin:0;">
              <input type="hidden" name="delete_id" value="<?= (int)$r['id'] ?>">
              <button class="btn btn-small btn-danger" type="submit">سڕینەوە</button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($totalPages > 1): ?>
  <div class="form-row" style="margin-top: 16px; justify-content: center; gap:8px;">
    <?php
      parse_str($_SERVER['QUERY_STRING'] ?? '', $qs);
      unset($qs['view_receipt']); // Remove view_receipt param if present
      $qs['per_page'] = $perPage;
      $mk = function($p) use ($qs){ $qs['page']=$p; return 'manual_receipts.php?'.http_build_query($qs); };
    ?>
    <?php if ($page > 1): ?>
      <a href="<?= $mk(1) ?>" class="badge">یەکەم</a>
      <a href="<?= $mk(max(1,$page-1)) ?>" class="badge">‹ پێشوو</a>
    <?php endif; ?>
    <span class="badge info">لاپەڕە <?= $page ?> لە <?= $totalPages ?></span>
    <?php if ($page < $totalPages): ?>
      <a href="<?= $mk(min($totalPages,$page+1)) ?>" class="badge">دواتر ›</a>
      <a href="<?= $mk($totalPages) ?>" class="badge">کۆتا</a>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>


<?php require_once 'footer.php'; ?>