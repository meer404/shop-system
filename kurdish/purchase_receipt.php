<?php
require_once '../inc/config.php';
require_once '../inc/auth.php';
function findColumn(PDO $pdo, string $table, array $candidates): ?string {
  try {
    $stmt = $pdo->prepare("DESCRIBE `$table`");
    $stmt->execute();
    $cols = array_map(fn($r) => strtolower($r['Field']), $stmt->fetchAll(PDO::FETCH_ASSOC));
    foreach ($candidates as $c) {
      if (in_array(strtolower($c), $cols, true)) return $c;
    }
  } catch (PDOException $e) {
    // Table might not exist, handle gracefully
    return null;
  }
  return null;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { die('Invalid receipt ID'); }

// --- Database Columns ---
$dateCol = findColumn($pdo, 'purchases', ['date','created_at','purchase_date']);
$dateSelect = $dateCol ? "p.`$dateCol` AS purchase_date_col" : "NULL AS purchase_date_col";

$supplierCol = findColumn($pdo, 'suppliers', ['name', 'supplier_name']);
$supplierSelect = $supplierCol ? "s.`$supplierCol` AS supplier_name_col" : "s.id AS supplier_name_col";

$noteCol = findColumn($pdo, 'purchases', ['note', 'notes', 'description']);
$noteSelect = $noteCol ? "p.`$noteCol` AS note_col" : "'' AS note_col";

// --- Fetch Main Purchase Data ---
$stmt = $pdo->prepare("
  SELECT p.*, {$dateSelect}, {$noteSelect}, {$supplierSelect}
  FROM purchases p
  LEFT JOIN suppliers s ON s.id = p.supplier_id
  WHERE p.id = ?
");
$stmt->execute([$id]);
$purchase = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$purchase) { die('Purchase not found.'); }

// --- Fetch Purchase Items ---
$stmt = $pdo->prepare("
  SELECT pi.*, pr.name AS product_name
  FROM purchase_items pi
  JOIN products pr ON pr.id = pi.product_id
  WHERE pi.purchase_id = ?
");
$stmt->execute([$id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Calculations ---
$subtotal = 0.0;
foreach($rows as $it) {
  $subtotal += (float)$it['qty'] * (float)$it['price'];
}
$discount = (float)($purchase['discount'] ?? 0);
$tax      = (float)($purchase['tax'] ?? 0);
$total    = $subtotal - $discount + $tax;

function safe($v){ return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="ku" dir="rtl">
<head>
  <meta charset="utf-8">
  <title>پسوڵەی کڕین #<?= safe($purchase['id']) ?></title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: Arial, Tahoma, sans-serif; background: #f9f7f4; color: #000; font-size: 14px; line-height: 1.4; padding: 20px; }
    .receipt-wrapper { max-width: 800px; margin: 0 auto; background: #fffef9; border: 2px solid #2c5282; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    .header-section { border-bottom: 2px solid #2c5282; padding-bottom: 15px; margin-bottom: 15px; background: linear-gradient(to bottom, #e6f2ff, #f0f7ff); padding: 15px; border-radius: 4px 4px 0 0; margin: -20px -20px 15px -20px; }
    .company-header { text-align: center; margin-bottom: 10px; }
    .company-header h1 { font-size: 24px; font-weight: bold; margin-bottom: 5px; color: #1a365d; text-shadow: 1px 1px 2px rgba(0,0,0,0.1); }
    .company-info { font-size: 12px; line-height: 1.6; color: #2c5282; }
    .receipt-details { display: flex; justify-content: space-between; margin-bottom: 20px; border: 1px solid #5a88b8; background: #f0f7ff; border-radius: 4px; }
    .detail-group { flex: 1; padding: 10px; border-left: 1px solid #5a88b8; }
    .detail-group:first-child { border-left: none; }
    .detail-label { font-weight: bold; color: #1a365d; display: inline-block; margin-right: 5px; }
    .detail-value { color: #2c5282; font-weight: 600; }
    .supplier-section { margin-bottom: 20px; padding: 10px; border: 1px solid #5a88b8; background: #fdfcfa; border-radius: 4px; }
    .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; border: 1px solid #2c5282; background: #fff; }
    .items-table thead { background: #ffd000ff; color: black; }
    .items-table th { border: 1px solid #2c5282; padding: 8px; text-align: center; font-weight: bold; font-size: 13px; }
    .items-table td { border: 1px solid #5a88b8; padding: 6px 8px; text-align: center; background: #fff; }
    .items-table tbody tr:nth-child(even) { background: #f7fafc; }
    .items-table .item-name { text-align: right; }
    .items-table .number-col { text-align: center; font-weight: 500; }
    .totals-section { display: flex; justify-content: flex-start; margin-bottom: 20px; }
    .totals-table { width: 40%; border-collapse: collapse; }
    .totals-table td { padding: 8px; border: 1px solid #5a88b8; }
    .totals-table .total-label { font-weight: bold; text-align: right; background: #f0f7ff; color: #1a365d; }
    .totals-table .total-value { text-align: left; font-weight: bold; font-size: 1.1em; }
    .note-section { border: 1px solid #5a88b8; padding: 10px; margin-bottom: 20px; background: #fef9e7; min-height: 60px; border-radius: 4px; }
    .note-label { font-weight: bold; margin-bottom: 5px; color: #1a365d; }
    .footer-section { margin-top: 30px; padding-top: 20px; border-top: 2px solid #2c5282; background: #f0f7ff; margin: 30px -20px -20px -20px; padding: 20px; }
    .print-info { text-align: center; font-size: 11px; color: #4a5568; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid #cbd5e0; }
    .signatures { display: flex; justify-content: space-around; margin-top: 20px; }
    .signature-box { text-align: center; min-width: 200px; }
    .signature-line { border-bottom: 2px solid #2c5282; margin-bottom: 5px; min-height: 40px; }
    .signature-label { font-size: 12px; color: #1a365d; font-weight: bold; }
    .print-actions { text-align: center; margin: 20px 0; }
    .bilingual { display: flex; justify-content: space-between; align-items: center; }
    @media print {
      * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
      body { padding: 0; }
      .receipt-wrapper { max-width: 100%; box-shadow: none; }
      .print-actions { display: none; }
      .header-section { margin: -20px -20px 15px -20px; }
      .footer-section { margin: 30px -20px -20px -20px; }
    }
  </style>
</head>
<body>
  <div class="receipt-wrapper">
    <div class="header-section">
      <div class="company-header">
        <div class="bilingual">
           <img src="../images/new_logo.png" alt="Company Logo" style="max-width: 150px;">
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
        <span class="detail-value"><?= safe(date('d/m/Y', strtotime($purchase['purchase_date_col'] ?? time()))) ?></span>
      </div>
      <div class="detail-group">
        <span class="detail-label">جۆری پسوڵە:</span>
        <span class="detail-value">کڕین</span>
      </div>
      <div class="detail-group">
        <span class="detail-label">ژمارەی پسوڵە:</span>
        <span class="detail-value">PUR-<?= safe($purchase['id']) ?></span>
      </div>
    </div>

    <div class="supplier-section">
      <span class="detail-label">فرۆشیار:</span>
      <span class="detail-value"><?= safe($purchase['supplier_name_col'] ?? 'N/A') ?></span>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th width="5%">#</th>
                <th width="50%">کاڵا</th>
                <th width="15%">بڕ</th>
                <th width="15%">نرخی یەکە</th>
                <th width="15%">کۆی گشتی</th>
            </tr>
        </thead>
        <tbody>
            <?php $i=1; foreach ($rows as $it): $line = (float)$it['qty'] * (float)$it['price']; ?>
            <tr>
              <td><?= $i++ ?></td>
              <td class="item-name"><?= safe($it['product_name']) ?></td>
              <td class="number-col"><?= number_format((float)$it['qty'], 2) ?></td>
              <td class="number-col">$<?= number_format((float)$it['price'], 2) ?></td>
              <td class="number-col"><strong>$<?= number_format($line, 2) ?></strong></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="totals-section">
        <table class="totals-table">
            <tr>
                <td class="total-label">کۆی گشتی لاوەکی</td>
                <td class="total-value number-col">$<?= number_format($subtotal, 2) ?></td>
            </tr>
            <tr>
                <td class="total-label">داشکاندن</td>
                <td class="total-value number-col">-$<?= number_format($discount, 2) ?></td>
            </tr>
             
            <tr>
                <td class="total-label">کۆی گشتی</td>
                <td class="total-value number-col">$<?= number_format($total, 2) ?></td>
            </tr>
        </table>
    </div>

    <?php if (!empty($purchase['note_col'])): ?>
    <div class="note-section">
      <div class="note-label">تێبینی:</div>
      <div><?= nl2br(safe($purchase['note_col'])) ?></div>
    </div>
    <?php endif; ?>

    <div class="footer-section">
      <div class="print-info">
        لاپەڕە ١ لە ١ | کات <?= date('d/m/Y h:i:s A') ?> | چاپکراوە لەلایەن: بەڕێوبەر
      </div>
      <div class="signatures">
        <div class="signature-box">
          <div class="signature-line"></div>
          <div class="signature-label">ژمێریار</div>
        </div>
        <div class="signature-box">
          <div class="signature-line"></div>
          <div class="signature-label">واژۆی وەرگر</div>
        </div>
      </div>
    </div>
  </div>

  <div class="print-actions">
    <button onclick="window.print()">چاپکردنی پسوڵە</button>
    <a href="index.php">داشبۆرد</a>
  </div>
</body>
</html>