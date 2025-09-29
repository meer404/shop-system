<?php
require_once  '../inc/auth.php';
require '../inc/config.php';

$saleId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($saleId <= 0) die("ناسنامەی فرۆشتن هەڵەیە.");

//
// 1) Load core sale fields
//
$sale = null;
$stmt = $pdo->prepare("
  SELECT s.id, s.customer_id, s.is_credit, s.note,
         /* try common date column; adjust below if you use another */
         s.sale_date 
  FROM sales s
  WHERE s.id = ?
  LIMIT 1
");
$stmt->execute([$saleId]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$sale) die("فرۆشتن نەدۆزرایەوە.");

//
// 2) Try to read sales.discount if the column exists
//
$discount = 0.0;
try {
  $q = $pdo->prepare("SELECT COALESCE(discount,0) FROM sales WHERE id = ?");
  $q->execute([$saleId]);
  $v = $q->fetchColumn();
  if ($v !== false && $v !== null) $discount = (float)$v;
} catch (Throwable $e) {
  // Column doesn't exist yet; leave $discount = 0.0
}

//
// 3) Customer
//
$customer = ['id'=>$sale['customer_id'], 'name'=>'کڕیاری گشتی', 'phone'=>null];
$cst = $pdo->prepare("SELECT id,name,phone FROM customers WHERE id=? LIMIT 1");
$cst->execute([$sale['customer_id']]);
if ($r = $cst->fetch(PDO::FETCH_ASSOC)) $customer = $r;

//
// 4) Items + subtotal
//
$it = $pdo->prepare("
  SELECT si.product_id, si.qty, si.price,
         (si.qty * si.price) AS subtotal,
         p.name AS product_name
  FROM sale_items si
  LEFT JOIN products p ON p.id = si.product_id
  WHERE si.sale_id = ?
  ORDER BY si.id ASC
");
$it->execute([$saleId]);
$items = $it->fetchAll(PDO::FETCH_ASSOC);

$subtotal = 0.0;
foreach ($items as $row) $subtotal += (float)($row['subtotal'] ?? 0);

//
// 5) Totals
//
$tax = 0.0;                    // keep 0 unless you add tax
$total = $subtotal - $discount;
if ($total < 0) $total = 0.0;

//
// 6) Paid for THIS sale (payments.sale_id -> SUM(amount)); fallback to sales.paid if present
//
$paidThisSale = 0.0;
try {
  $ps = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE sale_id = ?");
  $ps->execute([$saleId]);
  $paidThisSale = (float)$ps->fetchColumn();
} catch (Throwable $e) { /* ignore */ }

if ($paidThisSale <= 0) {
  try {
    $ps2 = $pdo->prepare("SELECT COALESCE(paid,0) FROM sales WHERE id = ?");
    $ps2->execute([$saleId]);
    $v = $ps2->fetchColumn();
    if ($v !== false && $v !== null) $paidThisSale = (float)$v;
  } catch (Throwable $e) { /* ignore */ }
}
$dueThisSale = max($total - $paidThisSale, 0.0);

//
// 7) Customer overall balance (view v_customer_balance if exists; else compute)
//
$customerBalance = 0.0;
try {
  $vb = $pdo->prepare("SELECT COALESCE(balance,0) FROM v_customer_balance WHERE customer_id = ?");
  $vb->execute([$customer['id']]);
  $val = $vb->fetchColumn();
  if ($val !== false && $val !== null) $customerBalance = (float)$val;
} catch (Throwable $e) {
  $calc = $pdo->prepare("
    SELECT
      COALESCE((
        SELECT SUM(si.qty * si.price)
        FROM sales s
        JOIN sale_items si ON si.sale_id = s.id
        WHERE s.customer_id = ? AND COALESCE(s.is_credit,0) = 1
      ),0)
      -
      COALESCE((
        SELECT SUM(p.amount) FROM payments p WHERE p.customer_id = ?
      ),0)
  ");
  $calc->execute([$customer['id'], $customer['id']]);
  $customerBalance = (float)$calc->fetchColumn();
}

//
// 8) Meta
//
$createdAt = $sale['created_at'] ?? date('Y-m-d H:i:s');   // adjust if your date column has a different name
$invoiceNo = sprintf("INV-%s-%d", date('Ymd', strtotime($createdAt)), $saleId);
$isCredit = !empty($sale['is_credit']) ? (int)$sale['is_credit'] : 0;
$note = $sale['note'] ?? '';
?>
<!doctype html>
<html lang="ku" dir="rtl">
<head>
  <meta charset="utf-8">
  <title>وەصڵ #<?= htmlspecialchars($invoiceNo) ?></title>
  <style>
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
   
      border-radius: 4px;
    }
    
    .detail-group {
      flex: 1;
      padding: 10px;
      border-left: 1px solid #5a88b8;
      text-align: center;
    }
    
    .detail-group:first-child {
      border-left: none;
    }
    
    .detail-label {
      font-weight: bold;
      color: #1a365d;
      display: block;
      margin-bottom: 3px;
    }
    
    .detail-value {
      color: #2c5282;
      font-weight: 600;
    }
    
    /* Customer Info */
    .customer-section {
      display: flex;
      gap: 20px;
      margin-bottom: 20px;
      padding: 10px;
      border: 1px solid #5a88b8;
      background: #fdfcfa;
      border-radius: 4px;
    }
    
    .customer-field {
      flex: 1;
      text-align: center;
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
    
    .items-table .product-name {
      text-align: right;
    }
    
    .items-table .number-col {
      text-align: center;
      font-weight: 500;
    }
    
    /* Totals Section */
    .totals-section {
      margin-bottom: 20px;
    }
    
    .totals-grid {
      display: flex;
      gap: 20px;
    }
    
    .totals-box {
      flex: 1;
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
      body {
        padding: 0;
        background: #fff;
      }
      
      .receipt-wrapper {
        border: 1px solid #000;
        max-width: 100%;
        box-shadow: none;
      }
      
      .print-actions {
        display: none;
      }
      
      .items-table th,
      .items-table td {
        border: 1px solid #000;
      }
      
      .header-section {
        margin: -20px -20px 15px -20px;
      }
      
      .footer-section {
        margin: 30px -20px -20px -20px;
      }
    }
    
    /* Bilingual Support */
    .bilingual {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .ar-text {
      text-align: right;
      font-family: Arial, Tahoma;
    }
  </style>
</head>
<body>
  <div class="receipt-wrapper">
    <!-- Header -->
    <div class="header-section">
      <div class="company-header">
        <h1>پسولەی فرۆشتن</h1>
        <div class="bilingual">
           <img src="../images/new_logo.png" alt="لۆگۆی کۆمپانیا" style="max-width: 150px;">
          <div class="company-info">
            بۆردی کۆنترۆڵ<br>
            سلێمانی - چوارباخ خوار فولکەی مامە ڕیشە<br>
            تەلەفۆن: 07732828287 - 00722142666 <br>
             ئیمەیڵ: jumaarasoul3@gmail.com
          </div>
         
        </div>
      </div>
    </div>
    
    <!-- Receipt Details -->
    <div class="receipt-details">
      <div class="detail-group">
        <span class="detail-label">بەروار:</span>
        <span class="detail-value"><?= htmlspecialchars(date('d/m/Y', strtotime($createdAt))) ?></span>
      </div>
      <div class="detail-group">
        <span class="detail-label">جۆری وەصڵ:</span>
        <span class="detail-value"><?= $isCredit ? 'قەرز' : 'واسڵکراوە' ?></span>
      </div>
      <div class="detail-group">
        <span class="detail-label">ژمارەی وەصڵ:</span>
        <span class="detail-value"><?= htmlspecialchars($saleId) ?></span>
      </div>
    </div>
    
    <!-- Customer Information -->
    <div class="customer-section">
      <div class="customer-field">
        <span class="detail-label">کڕیار:</span>
        <span class="detail-value"><?= htmlspecialchars($customer['name'] ?? 'کڕیاری گشتی') ?></span>
      </div>
      <div class="customer-field">
        <span class="detail-label">تەلەفۆن:</span>
        <span class="detail-value"><?= htmlspecialchars($customer['phone'] ?? '-') ?></span>
      </div>
      <div class="customer-field">
        <span class="detail-label">کۆدی هەژمار:</span>
        <span class="detail-value"><?= htmlspecialchars($customer['id']) ?></span>
      </div>
    </div>
    
    <!-- Items Table -->
    <table class="items-table">
      <thead>
        <tr>
          <th width="5%">#</th>
          <th width="45%">ناوی کاڵا</th>
          <th width="15%">بڕ</th>
          <th width="15%">نرخ</th>
          <th width="20%">کۆی گشتی</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($items): $i=1; foreach($items as $it): ?>
        <tr>
          <td><?= $i++ ?></td>
          <td class="product-name"><?= htmlspecialchars($it['product_name'] ?? ('#'.$it['product_id'])) ?></td>
          <td class="number-col"><?= number_format((float)$it['qty'], 2) ?></td>
          <td class="number-col"><?= number_format((float)$it['price'], 2) ?></td>
          <td class="number-col"><?= number_format((float)$it['subtotal'], 2) ?></td>
        </tr>
        <?php endforeach; else: ?>
        <tr>
          <td colspan="5">هیچ کاڵایەک نیە</td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
    
    <!-- Totals Section -->
    <div class="totals-section">
      <div class="totals-grid">
        <div class="totals-box">
          <div class="total-row">
            <span class="total-label">کۆی لاوەکی:</span>
            <span class="total-value">$<?= number_format((float)$subtotal, 2) ?></span>
          </div>
          <div class="total-row">
            <span class="total-label">داشکاندن:</span>
            <span class="total-value">$<?= number_format((float)$discount, 2) ?></span>
          </div>
          <div class="total-row">
            <span class="total-label">کۆی گشتی:</span>
            <span class="total-value">$<?= number_format((float)$total, 2) ?></span>
          </div>
        </div>
        
        <div class="totals-box">
          <div class="total-row">
            <span class="total-label">بڕی دراو:</span>
            <span class="total-value">$<?= number_format((float)$paidThisSale, 2) ?></span>
          </div>
          <div class="total-row">
            <span class="total-label">بڕی ماوە:</span>
            <span class="total-value">$<?= number_format((float)$dueThisSale, 2) ?></span>
          </div>
          <div class="total-row">
            <span class="total-label">قەرزی پێشوی کڕیار:</span>
            <span class="total-value">$<?= number_format((float)$customerBalance, 2) ?></span>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Note Section -->
    <?php if ($note !== ''): ?>
    <div class="note-section">
      <div class="note-label">تێبینی:</div>
      <div><?= nl2br(htmlspecialchars($note)) ?></div>
    </div>
    <?php endif; ?>
    
    <!-- Footer Section -->
    <div class="footer-section">
      <div class="print-info">
        لاپەڕەی ١ لە ١ | کات <?= date('d/m/Y h:i:s A') ?> | چاپکراو لەلایەن: بەڕێوبەر
      </div>
      
      <div class="signatures">
        <div class="signature-box">
          <div class="signature-line"></div>
          <div class="signature-label">ژمێریار</div>
        </div>
        <div class="signature-box">
          <div class="signature-line"></div>
          <div class="signature-label">واژووی کڕیار</div>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Print Actions - Outside the receipt wrapper -->
  <div class="print-actions">
    <button onclick="window.print()">چاپکردنی وەصڵ</button>
    <a href="receipts.php">هەموو وەصڵەکان</a>
    <a href="index.php">سەرەتا</a>
  </div>
</body>
</html>