<?php $page = 'suppliers.php'; require_once __DIR__ . '/header.php'; ?>
<?php
require __DIR__.'/inc/config.php';
$page_title = "Suppliers";
$msg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['name'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  if ($name === '') {
    $msg = "Name is required.";
  } else {
    $pdo->prepare("INSERT INTO suppliers (name, phone) VALUES (?,?)")->execute([$name,$phone]);
    $msg = "Supplier saved.";
  }
}

$rows = $pdo->query("SELECT * FROM suppliers ORDER BY created_at DESC, id DESC")->fetchAll();

require __DIR__.'/inc/header.php';
?>
<div class="card">
  <h2>Add Supplier</h2>
  <?php if ($msg): ?><p class="success"><?= h($msg) ?></p><?php endif; ?>
  <form method="post" action="suppliers.php">
    <div class="form-row">
      <input name="name" placeholder="Supplier name" required>
      <input name="phone" placeholder="Phone (optional)">
      <button type="submit">Save</button>
    </div>
  </form>
</div>

<div class="card">
  <h3>Suppliers</h3>
  <table>
    <thead><tr><th>#</th><th>Name</th><th>Phone</th></tr></thead>
    <tbody>
      <?php foreach($rows as $r): ?>
      <tr>
        <td><?= (int)$r['id'] ?></td>
        <td><?= h($r['name']) ?></td>
        <td><?= h($r['phone']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__.'/inc/footer.php'; ?>
<?php require_once __DIR__ . '/footer.php'; ?>
