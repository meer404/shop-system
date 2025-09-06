<?php $page = 'receipt_edit.php'; require_once __DIR__ . '/header.php'; require_once __DIR__ . '/inc/auth.php'; ?>
<?php
// receipt_edit.php — Edit a single SALE receipt (paid, note, date)
require __DIR__ . '/inc/config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { die("Invalid receipt id"); }

// Fetch current sale
$stmt = $pdo->prepare("
  SELECT s.*, c.name AS customer_name
  FROM sales s
  JOIN customers c ON c.id = s.customer_id
  WHERE s.id = ?
");
$stmt->execute([$id]);
$sale = $stmt->fetch();
if (!$sale) { die("Receipt not found"); }

// Handle update
$msg = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Read inputs
  $paid_in   = $_POST['paid'] ?? '';
  $paid      = ($paid_in === '' ? null : (float)$paid_in);

  // Ensure 'note' exists in DB (column may be nullable)
  $note      = isset($_POST['note']) ? trim($_POST['note']) : null;

  // Optional: allow editing sale_date (yyyy-mm-dd hh:mm:ss or date)
  $sale_date = trim($_POST['sale_date'] ?? '');
  if ($sale_date !== '' && strlen($sale_date) <= 10) {
    // If only date provided, normalize to 00:00:00
    $sale_date .= " 00:00:00";
  }

  // Build dynamic UPDATE
  $set = [];
  $args = [];

  if ($paid !== null) { $set[] = "paid = ?"; $args[] = $paid; }
  if ($note !== null) { $set[] = "note = ?"; $args[] = $note; }
  if ($sale_date !== '') { $set[] = "sale_date = ?"; $args[] = $sale_date; }

  if ($set) {
    $args[] = $id;
    $sql = "UPDATE sales SET " . implode(", ", $set) . " WHERE id = ?";
    $upd = $pdo->prepare($sql);
    $upd->execute($args);
    $msg = "Receipt updated.";
    // refresh $sale
    $stmt->execute([$id]);
    $sale = $stmt->fetch();
  } else {
    $msg = "Nothing to update.";
  }
}

$page_title = "Edit Receipt #".$sale['id']." — ".$sale['customer_name'];
require __DIR__ . '/inc/header.php';
?>
<div class="card">
  <h2>Edit Receipt</h2>
  <?php if ($msg): ?><p class="success"><?= h($msg) ?></p><?php endif; ?>

  <form method="post" action="receipt_edit.php?id=<?= (int)$sale['id'] ?>" class="form-row" style="align-items:flex-end; gap:12px; flex-wrap:wrap">
    <div style="display:flex;flex-direction:column;min-width:220px">
      <label>Customer</label>
      <input value="<?= h($sale['customer_name']) ?>" disabled>
    </div>

    <div style="display:flex;flex-direction:column;max-width:160px">
      <label>Subtotal</label>
      <input value="<?= number_format((float)$sale['subtotal'],2) ?>" disabled>
    </div>

    <div style="display:flex;flex-direction:column;max-width:160px">
      <label>Paid</label>
      <input type="number" step="0.01" name="paid" value="<?= h($sale['paid']) ?>">
    </div>

    <div style="display:flex;flex-direction:column;max-width:200px">
      <label>Sale date (optional)</label>
      <input type="text" name="sale_date" placeholder="YYYY-MM-DD or YYYY-MM-DD HH:MM:SS" value="<?= h($sale['sale_date']) ?>">
    </div>

    <div style="display:flex;flex-direction:column;min-width:300px;flex:1">
      <label>Note</label>
      <textarea name="note" rows="2" placeholder="Write note..."><?= h($sale['note'] ?? '') ?></textarea>
    </div>

    <div style="display:flex;gap:8px">
      <button type="submit">Save</button>
      <a href="sale_receipt.php?id=<?= (int)$sale['id'] ?>" class="badge">View receipt</a>
      <a href="receipts.php" class="badge">Back to receipts</a>
    </div>
  </form>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>
<?php require_once __DIR__ . '/footer.php'; ?>
