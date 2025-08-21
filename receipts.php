<?php
require __DIR__.'/inc/config.php';
$page_title = "All Receipts";
$customers = $pdo->query("SELECT id, name FROM customers ORDER BY name")->fetchAll();

// Filters
$customer_id = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
$date_from = trim($_GET['date_from'] ?? '');
$date_to   = trim($_GET['date_to'] ?? '');
$credit_only = isset($_GET['credit_only']) ? 1 : 0;

$where = [];
$params = [];
if ($customer_id > 0) { $where[] = "s.customer_id = ?"; $params[] = $customer_id; }
if ($date_from !== '') { $where[] = "DATE(s.sale_date) >= ?"; $params[] = $date_from; }
if ($date_to   !== '') { $where[] = "DATE(s.sale_date) <= ?"; $params[] = $date_to; }
if ($credit_only) { $where[] = "s.subtotal > s.paid"; }

$sql = "SELECT s.*, c.name AS customer_name
        FROM sales s
        JOIN customers c ON c.id = s.customer_id";
if ($where) { $sql .= " WHERE " . implode(" AND ", $where); }
$sql .= " ORDER BY s.sale_date DESC, s.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

require __DIR__.'/inc/header.php';
?>
<div class="card">
  <h2>All Receipts</h2>
  <form method="get" action="receipts.php" class="form-row noprint">
    <select name="customer_id">
      <option value="0">-- All customers --</option>
      <?php foreach($customers as $c): ?>
        <option value="<?= (int)$c['id'] ?>" <?= $customer_id==$c['id']?'selected':'' ?>><?= h($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="date" name="date_from" value="<?= h($date_from) ?>">
    <input type="date" name="date_to" value="<?= h($date_to) ?>">
    <label><input type="checkbox" name="credit_only" value="1" <?= $credit_only?'checked':'' ?>> Credit only</label>
    <button type="submit">Filter</button>
    <a class="noprint" href="receipts.php">Reset</a>
  </form>
</div>

<div class="card">
  <table>
    <thead>
      <tr>
        <th>Receipt #</th>
        <th>Date</th>
        <th>Customer</th>
        <th>Subtotal</th>
        <th>Paid</th>
        <th>Due</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($rows as $r):
        $receipt_no = 'INV-'.date('Ymd', strtotime($r['sale_date'])).'-'.$r['id'];
        $due = max(0, (float)$r['subtotal'] - (float)$r['paid']);
      ?>
      <tr>
        <td><?= h($receipt_no) ?></td>
        <td><?= h($r['sale_date']) ?></td>
        <td><a href="customer_view.php?id=<?= (int)$r['customer_id'] ?>"><?= h($r['customer_name']) ?></a></td>
        <td>$<?= number_format((float)$r['subtotal'],2) ?></td>
        <td>$<?= number_format((float)$r['paid'],2) ?></td>
        <td class="<?= $due>0?'danger':'' ?>">$<?= number_format($due,2) ?></td>
        <td class="actions">
          <a href="sale_receipt.php?id=<?= (int)$r['id'] ?>">Open</a>
          
          <a href="receipt_edit_items.php?id=<?= (int)$r['id'] ?>">Edit</a>

          <button onclick="window.open('sale_receipt.php?id=<?= (int)$r['id'] ?>','_blank').print()" type="button">Print</button>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__.'/inc/footer.php'; ?>
