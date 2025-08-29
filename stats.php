<?php
require_once __DIR__ . '/inc/config.php';   

if (!isset($pdo) || !($pdo instanceof PDO)) {
  die("<b>DB error:</b> config.php should set <code>\$pdo = new PDO(...)</code>");
}

$page = 'stats.php';
require_once __DIR__ . '/header.php';

/* Date range (default: last 30 days) */
$from = (isset($_GET['from']) && preg_match('~^\d{4}-\d{2}-\d{2}$~', $_GET['from']))
          ? $_GET['from'] : date('Y-m-d', strtotime('-30 days'));
$to   = (isset($_GET['to']) && preg_match('~^\d{4}-\d{2}-\d{2}$~', $_GET['to']))
          ? $_GET['to'] : date('Y-m-d');

/* Helper: fetch one row */
function fetch_one($pdo, $sql, $params){
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  return $row ?: ['units'=>0,'amount'=>0];
}

/* Totals */
$salesTotals = fetch_one($pdo, "
  SELECT COALESCE(SUM(si.qty),0) AS units,
         COALESCE(SUM(si.line_total),0) AS amount
  FROM sales s
  JOIN sale_items si ON si.sale_id = s.id
  WHERE DATE(s.sale_date) BETWEEN ? AND ?
", [$from, $to]);

$purchaseTotals = fetch_one($pdo, "
  SELECT COALESCE(SUM(pi.qty),0) AS units,
         COALESCE(SUM(pi.line_total),0) AS amount
  FROM purchases p
  JOIN purchase_items pi ON pi.purchase_id = p.id
  WHERE DATE(p.purchase_date) BETWEEN ? AND ?
", [$from, $to]);

/* Per-day series */
function fetch_series($pdo, $sql, $params){
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $out = [];
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $out[$row['d']] = (int)$row['units'];
  }
  return $out;
}

$salesSeries = fetch_series($pdo, "
  SELECT DATE(s.sale_date) AS d, COALESCE(SUM(si.qty),0) as units
  FROM sales s
  JOIN sale_items si ON si.sale_id = s.id
  WHERE DATE(s.sale_date) BETWEEN ? AND ?
  GROUP BY DATE(s.sale_date)
", [$from, $to]);

$purchaseSeries = fetch_series($pdo, "
  SELECT DATE(p.purchase_date) AS d, COALESCE(SUM(pi.qty),0) as units
  FROM purchases p
  JOIN purchase_items pi ON pi.purchase_id = p.id
  WHERE DATE(p.purchase_date) BETWEEN ? AND ?
  GROUP BY DATE(p.purchase_date)
", [$from, $to]);

/* Build aligned labels */
$labels = [];
$cursor = new DateTime($from);
$end    = new DateTime($to);
while ($cursor <= $end) {
  $labels[] = $cursor->format('Y-m-d');
  $cursor->modify('+1 day');
}
$salesUnits = [];
$purchaseUnits = [];
foreach ($labels as $d) {
  $salesUnits[] = $salesSeries[$d] ?? 0;
  $purchaseUnits[] = $purchaseSeries[$d] ?? 0;
}

/* Helpers */
function money_fmt($n){ return number_format((float)$n, 2); }
function int_fmt($n){ return number_format((int)$n); }
?>

<div class="d-flex align-items-center justify-content-between mb-3">
  <h3 class="mb-0">📈 ئاماری کڕین و فرۆشتن</h3>
</div>

<form class="row g-2 align-items-end mb-4" method="get">
  <div class="col-md-3">
    <label class="form-label">لەڕێکە: (From)</label>
    <input type="date" name="from" value="<?= htmlspecialchars($from) ?>" class="form-control">
  </div>
  <div class="col-md-3">
    <label class="form-label">بۆ ڕێکە: (To)</label>
    <input type="date" name="to" value="<?= htmlspecialchars($to) ?>" class="form-control">
  </div>
  <div class="col-md-3">
    <button class="btn btn-primary w-100">نوێکردنەوە (Refresh)</button>
  </div>
</form>

<div class="row g-3 mb-4">
  <div class="col-md-3"><div class="card"><div class="card-body">
    <div class="small text-secondary">Units Sold</div>
    <div class="h4 mb-0"><?= int_fmt($salesTotals['units']) ?></div>
  </div></div></div>

  <div class="col-md-3"><div class="card"><div class="card-body">
    <div class="small text-secondary">Sales Amount</div>
    <div class="h4 mb-0">$<?= money_fmt($salesTotals['amount']) ?></div>
  </div></div></div>

  <div class="col-md-3"><div class="card"><div class="card-body">
    <div class="small text-secondary">Units Purchased</div>
    <div class="h4 mb-0"><?= int_fmt($purchaseTotals['units']) ?></div>
  </div></div></div>

  <div class="col-md-3"><div class="card"><div class="card-body">
    <div class="small text-secondary">Purchase Amount</div>
    <div class="h4 mb-0">$<?= money_fmt($purchaseTotals['amount']) ?></div>
  </div></div></div>
</div>

<div class="card mb-4">
  <div class="card-body">
    <h6 class="mb-2">هێڵکاری ڕۆژانە: فرۆشتن vs کڕین (Units)</h6>
    <canvas id="lineChart" height="100"></canvas>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const labels = <?= json_encode($labels) ?>;
const salesUnits = <?= json_encode($salesUnits) ?>;
const purchaseUnits = <?= json_encode($purchaseUnits) ?>;
new Chart(document.getElementById('lineChart'), {
  type: 'line',
  data: {
    labels,
    datasets: [
      { label: 'Units Sold', data: salesUnits, borderWidth: 2, tension: 0.3 },
      { label: 'Units Purchased', data: purchaseUnits, borderWidth: 2, tension: 0.3 }
    ]
  },
  options: { responsive: true, scales: { y: { beginAtZero: true } } }
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
