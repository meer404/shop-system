<?php $page = 'purchase_system.php';  require_once __DIR__ . '/header.php'; require_once __DIR__ . '/inc/auth.php';?>
<?php
require __DIR__.'/inc/config.php';
$page_title = "Purchases";
$suppliers = $pdo->query("SELECT id, name FROM suppliers ORDER BY name")->fetchAll();

$supplier_id = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : 0;
$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');

$where = [];
$args = [];
if ($supplier_id > 0){ $where[] = "p.supplier_id = ?"; $args[] = $supplier_id; }
if ($from !== ''){ $where[] = "p.purchase_date >= ?"; $args[] = $from . " 00:00:00"; }
if ($to !== ''){ $where[] = "p.purchase_date <= ?"; $args[] = $to . " 23:59:59"; }

$sql = "SELECT p.*, s.name AS supplier_name, (p.subtotal - p.paid) AS balance
        FROM purchases p JOIN suppliers s ON s.id=p.supplier_id";
if ($where){ $sql .= " WHERE " . implode(" AND ", $where); }
$sql .= " ORDER BY p.purchase_date DESC, p.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($args);
$rows = $stmt->fetchAll();

require __DIR__.'/inc/header.php';
?>
<div class="card">
  <h2>Purchases</h2>
  <form method="get" action="purchases.php" class="form-row">
    <select name="supplier_id">
      <option value="0">-- All suppliers --</option>
      <?php foreach($suppliers as $s): ?>
        <option value="<?= (int)$s['id'] ?>" <?= $supplier_id==$s['id']?'selected':'' ?>><?= h($s['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="date" name="from" value="<?= h($from) ?>">
    <input type="date" name="to" value="<?= h($to) ?>">
    <button type="submit">Filter</button>
    <a class="badge" href="purchases.php">Reset</a>
  </form>
</div>

<div class="card">
  <table>
    <thead><tr><th>#</th><th>Date</th><th>Supplier</th><th>Subtotal</th><th>Paid</th><th>Balance</th><th>Receipt</th></tr></thead>
    <tbody>
      <?php foreach($rows as $r): ?>
      <tr>
        <td><?= (int)$r['id'] ?></td>
        <td><?= h($r['purchase_date']) ?></td>
        <td><?= h($r['supplier_name']) ?></td>
        <td>$<?= number_format((float)$r['subtotal'],2) ?></td>
        <td>$<?= number_format((float)$r['paid'],2) ?></td>
        <td class="<?= ($r['balance']>0)?'danger':'' ?>">$<?= number_format((float)$r['balance'],2) ?></td>
        <td><a href="purchase_receipt.php?id=<?= (int)$r['id'] ?>">Open</a></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__.'/inc/footer.php'; ?>
<?php require_once __DIR__ . '/footer.php'; ?>
