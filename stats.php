<?php
require_once __DIR__ . '/header.php'; 
require __DIR__.'/inc/config.php'; 
require_once __DIR__ . '/inc/auth.php';

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

/* Helper functions */
function fetch_one($pdo, $sql, $params) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: ['units'=>0,'amount'=>0];
}

function fetch_series($pdo, $sql, $params) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $out = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $out[$row['d']] = (int)$row['units'];
    }
    return $out;
}

function money_fmt($n) { return number_format((float)$n, 2); }
function int_fmt($n) { return number_format((int)$n); }

/* Fetch data */
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

/* Build aligned date labels */
$labels = [];
$cursor = new DateTime($from);
$end = new DateTime($to);
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

// Calculate profit/loss
$profit = (float)$salesTotals['amount'] - (float)$purchaseTotals['amount'];
$profitPercent = $purchaseTotals['amount'] > 0 ? (($profit / (float)$purchaseTotals['amount']) * 100) : 0;
?>

<style>
:root {
    --bg-primary: #0f0f23;
    --bg-secondary: #1a1a2e;
    --bg-tertiary: #16213e;
    --bg-card: rgba(26, 26, 46, 0.8);
    --text-primary: #ffffff;
    --text-secondary: #b8bcc8;
    --text-muted: #6c757d;
    --accent-primary: #00d4ff;
    --accent-secondary: #7c3aed;
    --success: #10b981;
    --danger: #ef4444;
    --warning: #f59e0b;
    --border-color: rgba(255, 255, 255, 0.1);
    --shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.3);
    --shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.4);
    --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
}

body {
    background: linear-gradient(135deg, var(--bg-primary) 0%, var(--bg-secondary) 100%);
    color: var(--text-primary);
    min-height: 100vh;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
}

.dashboard-container {
    background: var(--bg-primary);
    min-height: 100vh;
    padding: 1rem;
}

.stats-card {
    background: var(--bg-card);
    backdrop-filter: blur(10px);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    color: var(--text-primary);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: var(--shadow-md);
    position: relative;
    overflow: hidden;
}

.stats-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--accent-primary), var(--accent-secondary));
    opacity: 0;
    transition: opacity 0.3s ease;
}

.stats-card:hover::before {
    opacity: 1;
}

.stats-card:hover {
    transform: translateY(-8px);
    box-shadow: var(--shadow-lg);
    border-color: var(--accent-primary);
}

.stats-card.sales::before {
    background: linear-gradient(90deg, var(--success), #34d399);
}

.stats-card.purchases::before {
    background: linear-gradient(90deg, var(--warning), #fbbf24);
}

.stats-card.profit::before {
    background: linear-gradient(90deg, var(--accent-primary), var(--accent-secondary));
}

.stats-card.profit.negative::before {
    background: linear-gradient(90deg, var(--danger), #f87171);
}

.stats-icon {
    font-size: 2.5rem;
    opacity: 0.7;
    background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.stats-card.sales .stats-icon {
    background: linear-gradient(135deg, var(--success), #34d399);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.stats-card.purchases .stats-icon {
    background: linear-gradient(135deg, var(--warning), #fbbf24);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.stats-card.profit.negative .stats-icon {
    background: linear-gradient(135deg, var(--danger), #f87171);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.chart-container {
    background: var(--bg-card);
    backdrop-filter: blur(10px);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    box-shadow: var(--shadow-md);
    transition: all 0.3s ease;
}

.chart-container:hover {
    box-shadow: var(--shadow-lg);
    border-color: var(--accent-primary);
}

.date-filter {
    background: var(--bg-card);
    backdrop-filter: blur(10px);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    box-shadow: var(--shadow-md);
    padding: 2rem;
}

.btn-custom {
    background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
    border: none;
    border-radius: 15px;
    padding: 12px 24px;
    font-weight: 600;
    color: white;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: var(--shadow-sm);
}

.btn-custom:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(0, 212, 255, 0.4);
    filter: brightness(1.1);
    color: white;
}

.page-header {
    background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-weight: 700;
    margin-bottom: 2rem;
    font-size: clamp(1.5rem, 4vw, 2.5rem);
}

.form-control {
    background: var(--bg-tertiary);
    border: 2px solid var(--border-color);
    border-radius: 12px;
    color: var(--text-primary);
    transition: all 0.3s ease;
    padding: 12px 16px;
}

.form-control:focus {
    background: var(--bg-tertiary);
    border-color: var(--accent-primary);
    box-shadow: 0 0 0 0.2rem rgba(0, 212, 255, 0.25);
    color: var(--text-primary);
}

.form-control::placeholder {
    color: var(--text-muted);
}

.form-label {
    color: var(--text-secondary);
    font-weight: 600;
    margin-bottom: 0.75rem;
}

.metric-label {
    font-size: 0.9rem;
    color: var(--text-secondary);
    font-weight: 500;
    margin-bottom: 0.5rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.metric-value {
    font-size: clamp(1.5rem, 3vw, 2.2rem);
    font-weight: 700;
    margin-bottom: 0.25rem;
    color: var(--text-primary);
}

.metric-change {
    font-size: 0.85rem;
    color: var(--text-secondary);
    opacity: 0.8;
}

.btn-group .btn {
    background: var(--bg-tertiary);
    border: 1px solid var(--border-color);
    color: var(--text-secondary);
    transition: all 0.3s ease;
}

.btn-group .btn.active,
.btn-group .btn:hover {
    background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
    border-color: var(--accent-primary);
    color: white;
}

.card-title {
    color: var(--text-primary);
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.text-primary {
    color: var(--accent-primary) !important;
}

.text-muted {
    color: var(--text-muted) !important;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .dashboard-container {
        padding: 0.75rem;
    }
    
    .metric-value {
        font-size: clamp(1.25rem, 2.5vw, 1.8rem);
    }
    
    .stats-icon {
        font-size: 2rem;
    }
}

@media (max-width: 768px) {
    .dashboard-container {
        padding: 0.5rem;
    }
    
    .date-filter {
        padding: 1.5rem;
    }
    
    .stats-card {
        margin-bottom: 1rem;
    }
    
    .metric-value {
        font-size: 1.5rem;
    }
    
    .stats-icon {
        font-size: 1.75rem;
    }
    
    .chart-container .card-body {
        padding: 1rem;
    }
    
    .btn-custom {
        padding: 10px 20px;
        font-size: 0.9rem;
    }
}

@media (max-width: 576px) {
    .page-header {
        font-size: 1.5rem;
        text-align: center;
    }
    
    .metric-value {
        font-size: 1.25rem;
    }
    
    .stats-icon {
        font-size: 1.5rem;
    }
    
    .date-filter {
        padding: 1rem;
    }
    
    .chart-container {
        border-radius: 15px;
    }
    
    .stats-card {
        border-radius: 15px;
    }
}

/* Custom scrollbar */
::-webkit-scrollbar {
    width: 8px;
}

::-webkit-scrollbar-track {
    background: var(--bg-secondary);
}

::-webkit-scrollbar-thumb {
    background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary));
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    filter: brightness(1.2);
}

/* Loading animation */
.loading-shimmer {
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
    animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

/* Chart responsive containers */
.chart-responsive {
    position: relative;
    height: 300px;
}

@media (min-width: 768px) {
    .chart-responsive {
        height: 400px;
    }
}

@media (min-width: 1200px) {
    .chart-responsive {
        height: 450px;
    }
}
</style>

<div class="dashboard-container">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between mb-4">
            <h2 class="page-header mb-2 mb-md-0">📈 ئامارى کڕین و فرۆشتن</h2>
            <div class="text-muted">
                <i class="fas fa-calendar-alt me-2"></i>
                <span class="d-none d-sm-inline"><?= date('M j, Y', strtotime($from)) ?> - <?= date('M j, Y', strtotime($to)) ?></span>
                <span class="d-sm-none"><?= date('m/d', strtotime($from)) ?> - <?= date('m/d', strtotime($to)) ?></span>
            </div>
        </div>

        <!-- Date Filter -->
        <div class="card date-filter mb-4">
            <form class="row g-3 align-items-end" method="get">
                <div class="col-12 col-sm-6 col-lg-4">
                    <label class="form-label">
                        <i class="fas fa-calendar-plus me-2 text-primary"></i>لەڕێکە: (From)
                    </label>
                    <input type="date" name="from" value="<?= htmlspecialchars($from) ?>" class="form-control">
                </div>
                <div class="col-12 col-sm-6 col-lg-4">
                    <label class="form-label">
                        <i class="fas fa-calendar-check me-2 text-primary"></i>بۆ ڕێکە: (To)
                    </label>
                    <input type="date" name="to" value="<?= htmlspecialchars($to) ?>" class="form-control">
                </div>
                <div class="col-12 col-lg-4">
                    <button class="btn btn-custom w-100">
                        <i class="fas fa-sync-alt me-2"></i>نوێکردنەوە (Refresh)
                    </button>
                </div>
            </form>
        </div>

        <!-- Stats Cards -->
        <div class="row g-3 g-lg-4 mb-5">
            <div class="col-6 col-lg-3">
                <div class="card stats-card sales h-100">
                    <div class="card-body p-3 p-lg-4 d-flex flex-column flex-md-row align-items-start align-items-md-center">
                        <div class="flex-grow-1 mb-2 mb-md-0">
                            <div class="metric-label">Units Sold</div>
                            <div class="metric-value"><?= int_fmt($salesTotals['units']) ?></div>
                            <div class="metric-change d-none d-sm-block">Total Items</div>
                        </div>
                        <div class="stats-icon align-self-end align-self-md-center">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="card stats-card sales h-100">
                    <div class="card-body p-3 p-lg-4 d-flex flex-column flex-md-row align-items-start align-items-md-center">
                        <div class="flex-grow-1 mb-2 mb-md-0">
                            <div class="metric-label">Sales Revenue</div>
                            <div class="metric-value">$<?= money_fmt($salesTotals['amount']) ?></div>
                            <div class="metric-change d-none d-sm-block">Total Income</div>
                        </div>
                        <div class="stats-icon align-self-end align-self-md-center">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="card stats-card purchases h-100">
                    <div class="card-body p-3 p-lg-4 d-flex flex-column flex-md-row align-items-start align-items-md-center">
                        <div class="flex-grow-1 mb-2 mb-md-0">
                            <div class="metric-label">Units Purchased</div>
                            <div class="metric-value"><?= int_fmt($purchaseTotals['units']) ?></div>
                            <div class="metric-change d-none d-sm-block">Stock Added</div>
                        </div>
                        <div class="stats-icon align-self-end align-self-md-center">
                            <i class="fas fa-boxes"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="card stats-card <?= $profit >= 0 ? 'profit' : 'profit negative' ?> h-100">
                    <div class="card-body p-3 p-lg-4 d-flex flex-column flex-md-row align-items-start align-items-md-center">
                        <div class="flex-grow-1 mb-2 mb-md-0">
                            <div class="metric-label"><?= $profit >= 0 ? 'Profit' : 'Loss' ?></div>
                            <div class="metric-value">$<?= money_fmt(abs($profit)) ?></div>
                            <div class="metric-change d-none d-sm-block">
                                <?= abs($profitPercent) > 0 ? number_format($profitPercent, 1) : '0' ?>%
                                <?= $profit >= 0 ? 'Profit' : 'Loss' ?>
                            </div>
                        </div>
                        <div class="stats-icon align-self-end align-self-md-center">
                            <i class="fas fa-<?= $profit >= 0 ? 'chart-line' : 'chart-line-down' ?>"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Chart -->
        <div class="row g-3 g-lg-4">
            <div class="col-12">
                <div class="card chart-container">
                    <div class="card-body p-3 p-lg-4">
                        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center mb-4">
                            <h5 class="card-title mb-2 mb-lg-0">
                                <i class="fas fa-chart-line me-2 text-primary"></i>
                                <span class="d-none d-md-inline">هێڵکاری ڕۆژانە: فرۆشتن vs کڕین (Units)</span>
                                <span class="d-md-none">Daily Chart</span>
                            </h5>
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-sm active" onclick="toggleChart('units')">
                                    Units
                                </button>
                                <button type="button" class="btn btn-sm" onclick="toggleChart('amount')">
                                    Amount
                                </button>
                            </div>
                        </div>
                        <div class="chart-responsive">
                            <canvas id="lineChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Charts Row -->
        <div class="row g-3 g-lg-4 mt-2">
            <div class="col-12 col-lg-6">
                <div class="card chart-container">
                    <div class="card-body p-3 p-lg-4">
                        <h6 class="card-title mb-3">
                            <i class="fas fa-chart-pie me-2 text-primary"></i>
                            <span class="d-none d-sm-inline">Sales vs Purchases Overview</span>
                            <span class="d-sm-none">Overview</span>
                        </h6>
                        <div style="position: relative; height: 250px;">
                            <canvas id="doughnutChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-6">
                <div class="card chart-container">
                    <div class="card-body p-3 p-lg-4">
                        <h6 class="card-title mb-3">
                            <i class="fas fa-chart-bar me-2 text-primary"></i>
                            <span class="d-none d-sm-inline">Weekly Summary</span>
                            <span class="d-sm-none">Weekly</span>
                        </h6>
                        <div style="position: relative; height: 250px;">
                            <canvas id="barChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Dark theme configuration for Chart.js
Chart.defaults.color = '#b8bcc8';
Chart.defaults.borderColor = 'rgba(255, 255, 255, 0.1)';
Chart.defaults.backgroundColor = 'rgba(0, 212, 255, 0.1)';
Chart.defaults.font.family = 'Inter, system-ui, sans-serif';

// Data preparation
const labels = <?= json_encode($labels) ?>;
const salesUnits = <?= json_encode($salesUnits) ?>;
const purchaseUnits = <?= json_encode($purchaseUnits) ?>;

// Responsive font sizes
const isMobile = window.innerWidth < 768;
const fontSize = isMobile ? 10 : 12;
const titleFontSize = isMobile ? 12 : 14;

// Line Chart
const lineCtx = document.getElementById('lineChart').getContext('2d');
const lineChart = new Chart(lineCtx, {
    type: 'line',
    data: {
        labels: labels.map(label => {
            const date = new Date(label);
            return isMobile ? date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) : label;
        }),
        datasets: [
            {
                label: 'Units Sold',
                data: salesUnits,
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                borderWidth: isMobile ? 2 : 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#10b981',
                pointBorderColor: '#0f0f23',
                pointBorderWidth: 2,
                pointRadius: isMobile ? 3 : 5,
                pointHoverRadius: isMobile ? 5 : 7
            },
            {
                label: 'Units Purchased',
                data: purchaseUnits,
                borderColor: '#f59e0b',
                backgroundColor: 'rgba(245, 158, 11, 0.1)',
                borderWidth: isMobile ? 2 : 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#f59e0b',
                pointBorderColor: '#0f0f23',
                pointBorderWidth: 2,
                pointRadius: isMobile ? 3 : 5,
                pointHoverRadius: isMobile ? 5 : 7
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
            intersect: false,
            mode: 'index'
        },
        plugins: {
            legend: {
                position: 'top',
                labels: {
                    usePointStyle: true,
                    padding: isMobile ? 15 : 20,
                    font: {
                        size: fontSize,
                        weight: '600'
                    },
                    color: '#b8bcc8'
                }
            },
            tooltip: {
                backgroundColor: 'rgba(15, 15, 35, 0.9)',
                titleColor: '#ffffff',
                bodyColor: '#b8bcc8',
                borderColor: '#00d4ff',
                borderWidth: 1,
                cornerRadius: 12,
                padding: 12,
                titleFont: { size: fontSize },
                bodyFont: { size: fontSize }
            }
        },
        scales: {
            x: {
                grid: {
                    color: 'rgba(255, 255, 255, 0.05)',
                    drawBorder: false
                },
                ticks: {
                    maxTicksLimit: isMobile ? 6 : 10,
                    font: { size: fontSize },
                    color: '#6c757d'
                }
            },
            y: {
                beginAtZero: true,
                grid: {
                    color: 'rgba(255, 255, 255, 0.05)',
                    drawBorder: false
                },
                ticks: {
                    font: { size: fontSize },
                    color: '#6c757d'
                }
            }
        }
    }
});

// Doughnut Chart
const doughnutCtx = document.getElementById('doughnutChart').getContext('2d');
new Chart(doughnutCtx, {
    type: 'doughnut',
    data: {
        labels: ['Sales Amount', 'Purchase Amount'],
        datasets: [{
            data: [<?= $salesTotals['amount'] ?>, <?= $purchaseTotals['amount'] ?>],
            backgroundColor: [
                'rgba(16, 185, 129, 0.8)',
                'rgba(245, 158, 11, 0.8)'
            ],
            borderColor: [
                '#10b981',
                '#f59e0b'
            ],
            borderWidth: 2,
            hoverOffset: isMobile ? 5 : 10
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: isMobile ? 15 : 20,
                    usePointStyle: true,
                    font: { size: fontSize },
                    color: '#b8bcc8'
                }
            },
            tooltip: {
                backgroundColor: 'rgba(15, 15, 35, 0.9)',
                titleColor: '#ffffff',
                bodyColor: '#b8bcc8',
                borderColor: '#00d4ff',
                borderWidth: 1,
                cornerRadius: 12,
                padding: 12
            }
        }
    }
});

// Bar Chart (Weekly Summary)
const barCtx = document.getElementById('barChart').getContext('2d');
new Chart(barCtx, {
    type: 'bar',
    data: {
        labels: isMobile ? ['W1', 'W2', 'W3', 'W4'] : ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
        datasets: [
            {
                label: 'Sales',
                data: [
                    salesUnits.slice(0, 7).reduce((a, b) => a + b, 0),
                    salesUnits.slice(7, 14).reduce((a, b) => a + b, 0),
                    salesUnits.slice(14, 21).reduce((a, b) => a + b, 0),
                    salesUnits.slice(21).reduce((a, b) => a + b, 0)
                ],
                backgroundColor: 'rgba(16, 185, 129, 0.8)',
                borderColor: '#10b981',
                borderWidth: 1,
                borderRadius: 6,
                borderSkipped: false,
            },
            {
                label: 'Purchases',
                data: [
                    purchaseUnits.slice(0, 7).reduce((a, b) => a + b, 0),
                    purchaseUnits.slice(7, 14).reduce((a, b) => a + b, 0),
                    purchaseUnits.slice(14, 21).reduce((a, b) => a + b, 0),
                    purchaseUnits.slice(21).reduce((a, b) => a + b, 0)
                ],
                backgroundColor: 'rgba(245, 158, 11, 0.8)',
                borderColor: '#f59e0b',
                borderWidth: 1,
                borderRadius: 6,
                borderSkipped: false,
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: isMobile ? 15 : 20,
                    usePointStyle: true,
                    font: { size: fontSize },
                    color: '#b8bcc8'
                }
            },
            tooltip: {
                backgroundColor: 'rgba(15, 15, 35, 0.9)',
                titleColor: '#ffffff',
                bodyColor: '#b8bcc8',
                borderColor: '#00d4ff',
                borderWidth: 1,
                cornerRadius: 12,
                padding: 12
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    color: 'rgba(255, 255, 255, 0.05)',
                    drawBorder: false
                },
                ticks: {
                    font: { size: fontSize },
                    color: '#6c757d'
                }
            },
            x: {
                grid: {
                    display: false
                },
                ticks: {
                    font: { size: fontSize },
                    color: '#6c757d'
                }
            }
        }
    }
});

// Chart toggle functionality
function toggleChart(type) {
    // Update button states
    document.querySelectorAll('.btn-group button').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');
    
    // This would require additional data fetching for amounts
    // For now, it's just a UI demo
    console.log('Chart toggled to:', type);
}

// Responsive chart updates
window.addEventListener('resize', function() {
    const newIsMobile = window.innerWidth < 768;
    if (newIsMobile !== isMobile) {
        // Update font sizes and redraw charts
        Chart.helpers.each(Chart.instances, function(chart) {
            chart.options.plugins.legend.labels.font.size = newIsMobile ? 10 : 12;
            chart.options.scales.x.ticks.font.size = newIsMobile ? 10 : 12;
            chart.options.scales.y.ticks.font.size = newIsMobile ? 10 : 12;
            chart.update();
        });
    }
});

// Loading animation for cards
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.stats-card, .chart-container');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
            card.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
});
</script>

<?php require_once 'footer.php'; ?>

<script src="kurdish-ui.js?v=3"></script>