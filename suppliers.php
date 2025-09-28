<?php 
$page = 'purchase_system.php';  
require_once __DIR__ . '/header.php'; 
require_once __DIR__ . '/inc/auth.php';
require __DIR__.'/inc/config.php';
$page_title = "Suppliers";
$msg = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['name'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  if ($name === '') {
    $error = "Name is required.";
  } else {
    // Corrected the database query syntax
    $stmt = $pdo->prepare("INSERT INTO suppliers (name, phone) VALUES (?,?)");
    $stmt->execute([$name, $phone]);
    $msg = "Supplier saved successfully.";
  }
}

// Removed the duplicate h() function and the extra header include
$rows = $pdo->query("SELECT * FROM suppliers ORDER BY created_at DESC, id DESC")->fetchAll();
?>
<div class="card">
  <h2>Add Supplier</h2>
  <?php if ($msg): ?><p class="success"><?= h($msg) ?></p><?php endif; ?>
  <?php if ($error): ?><p class="danger"><?= h($error) ?></p><?php endif; ?>
  <form method="post" action="suppliers.php">
      <div class="form-row">
      <div style="flex:1">
      <label for="name">Supplier Name</label>
      <input type="text" id="name" name="name" placeholder="Supplier name" required>
      </div>
      <div style="flex:1">
      <label for="phone">Phone</label>
      <input type="text" id="phone" name="phone" placeholder="Phone (optional)">
      </div>
      <button type="submit">Save</button>
    </div>
  </form>
</div>

<div class="card">
  <h3>Suppliers</h3>
  <table>
    <thead><tr><th>#</th><th>Name</th><th>Phone</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= (int)$r['id'] ?></td>
        <td><?= h($r['name']) ?></td>
        <td><?= h($r['phone']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>

