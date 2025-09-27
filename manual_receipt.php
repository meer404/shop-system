<?php
require_once __DIR__ . '/header.php';

// CONFIG (expects $pdo PDO instance; will fall back to $conn if needed)
require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/auth.php';

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
      $_SESSION['flash_success'] = "Receipt #$id deleted.";
    }
  } catch (Throwable $e) {
    $_SESSION['flash_error'] = "Delete failed: ".$e->getMessage();
  }
  header("Location: manual_receipts.php");
  exit;
}

/* ---- View Receipt (Alternative method) ---- */
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
    die("Receipt not found!");
  }
  
  // Fetch receipt items
  $stmt = $pdo->prepare("
    SELECT * FROM manual_receipt_items 
    WHERE receipt_id = ? 
    ORDER BY id
  ");
  $stmt->execute([$receipt_id]);
  $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  // Display receipt
  ?>
  <!DOCTYPE html>
  <html>
  <head>
    <title>Receipt #<?= safe($receipt['receipt_no']) ?></title>
    <style>
      body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
      .receipt-header { text-align: center; margin-bottom: 30px; }
      .receipt-info { margin-bottom: 20px; }
      table { width: 100%; border-collapse: collapse; margin: 20px 0; }
      th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
      th { background-color: #f2f2f2; }
      .total-row { font-weight: bold; background-color: #f9f9f9; }
      .print-btn { background: #4CAF50; color: white; padding: 10px 20px; border: none; cursor: pointer; margin: 10px; }
      .back-btn { background: #008CBA; color: white; padding: 10px 20px; border: none; cursor: pointer; margin: 10px; }
      @media print { .no-print { display: none; } }
    </style>
  </head>
  <body>
    <div class="no-print">
      <button onclick="window.print()" class="print-btn">Print Receipt</button>
      <button onclick="window.location='manual_receipts.php'" class="back-btn">Back to List</button>
    </div>
    
    <div class="receipt-header">
      <h1>RECEIPT</h1>
      <h2>#<?= safe($receipt['receipt_no']) ?></h2>
    </div>
    
    <div class="receipt-info">
      <p><strong>Date:</strong> <?= safe($receipt['created_at']) ?></p>
      <?php if ($receipt['note']): ?>
      <p><strong>Note:</strong> <?= safe($receipt['note']) ?></p>
      <?php endif; ?>
    </div>
    
    <table>
      <thead>
        <tr>
          <th>Item</th>
          <th>Description</th>
          <th>Quantity</th>
          <th>Price</th>
          <th>Total</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $item): ?>
        <tr>
          <td><?= safe($item['item_name'] ?? 'N/A') ?></td>
          <td><?= safe($item['description'] ?? '') ?></td>
          <td><?= safe($item['quantity'] ?? 1) ?></td>
          <td><?= number_format($item['unit_price'] ?? 0, 2) ?></td>
          <td><?= number_format($item['line_total'] ?? 0, 2) ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="total-row">
          <td colspan="4" style="text-align: right;">TOTAL:</td>
          <td><?= number_format($receipt['total'], 2) ?></td>
        </tr>
      </tbody>
    </table>
    
    <script>
      // Auto-print if print parameter is set
      if (window.location.search.includes('print=1')) {
        window.print();
      }
    </script>
  </body>
  </html>
  <?php
  exit;
}

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
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Manual Receipts</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="styles.css?v=16" rel="stylesheet">
  <link href="receipt.css?v=16" rel="stylesheet">
  <style>
    .actions { 
      display: flex; 
      gap: 5px; 
      justify-content: flex-end; 
      align-items: center; 
    }
    .btn-view { 
      background-color: #4CAF50; 
      color: white; 
    }
    .btn-print { 
      background-color: #2196F3; 
      color: white; 
    }
  </style>
</head>
<body>
<button class="mobile-menu-toggle noprint">☰</button>
<div class="app">
  <div class="content">
    <div class="card">
      <h2 class="gradient-text">Manual Receipts</h2>
      <?php if ($flash_success): ?><div class="success"><?= safe($flash_success) ?></div><?php endif; ?>
      <?php if ($flash_error):   ?><div class="danger"><?= safe($flash_error)   ?></div><?php endif; ?>

      <form class="form-row" method="get" style="align-items:flex-end">
        <div><label>Search</label><input type="text" name="q" value="<?= safe($q) ?>" placeholder="Receipt No or Note"></div>
        <div><label>From</label><input type="date" name="from" value="<?= safe($from) ?>"></div>
        <div><label>To</label><input type="date" name="to" value="<?= safe($to) ?>"></div>
        <div><label>Min Total</label><input type="number" step="0.01" name="min_total" value="<?= safe($minTot) ?>"></div>
        <div><label>Max Total</label><input type="number" step="0.01" name="max_total" value="<?= safe($maxTot) ?>"></div>
        <div><label>Per Page</label><input type="number" min="5" max="100" name="per_page" value="<?= safe($perPage) ?>"></div>
        <div>
          <button class="btn">Apply Filters</button>
          <a class="btn btn-secondary" href="manual_receipts.php">Reset</a>
          <a class="btn btn-success" href="manual_receipt_new.php">+ New Manual Receipt</a>
        </div>
      </form>

      <div class="mt-2"><div class="badge info">Total: <b><?= number_format($totalRows) ?></b> receipts</div></div>

      <div class="mt-3">
        <table>
          <thead><tr>
            <th>#</th><th>Receipt No</th><th>Created</th><th>Items</th><th>Total</th><th>Note</th><th class="text-right">Actions</th>
          </tr></thead>
          <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="7">No receipts found.</td></tr>
          <?php else: foreach ($rows as $r): ?>
            <tr>
              <td><?= (int)$r['id'] ?></td>
              <td><span class="badge"><?= safe($r['receipt_no']) ?></span></td>
              <td><?= safe(date('Y-m-d H:i', strtotime($r['created_at']))) ?></td>
              <td><?= (int)$r['item_count'] ?></td>
              <td><b><?= number_format((float)$r['grand_total'], 2) ?></b></td>
              <td><?= safe($r['note']) ?></td>
              <td class="text-right">
                <div class="actions">
                  <!-- Direct link to view receipt in same file -->
                  <a class="btn btn-small btn-view" href="?view_receipt=<?= (int)$r['id'] ?>">View</a>
                  <!-- Print link opens in new window with auto-print -->
                  <a class="btn btn-small btn-print" href="?view_receipt=<?= (int)$r['id'] ?>&print=1" target="_blank">Print</a>
                  <form method="post" onsubmit="return confirm('Delete this receipt?');" style="display:inline; margin:0;">
                    <input type="hidden" name="delete_id" value="<?= (int)$r['id'] ?>">
                    <button class="btn btn-danger btn-small" type="submit">Delete</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <?php if ($totalPages > 1): ?>
      <div class="mt-3" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <?php
          parse_str($_SERVER['QUERY_STRING'] ?? '', $qs);
          unset($qs['view_receipt']); // Remove view_receipt param if present
          $qs['per_page'] = $perPage;
          $mk = function($p) use ($qs){ $qs['page']=$p; return 'manual_receipts.php?'.http_build_query($qs); };
        ?>
        <a class="btn btn-secondary btn-small" href="<?= $mk(max(1,$page-1)) ?>">‹ Prev</a>
        <span class="badge">Page <b><?= $page ?></b> / <?= $totalPages ?></span>
        <a class="btn btn-secondary btn-small" href="<?= $mk(min($totalPages,$page+1)) ?>">Next ›</a>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>