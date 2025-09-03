<?php
// products.php
$page = 'products.php';
require_once __DIR__ . '/header.php';

// CONFIG (expects $pdo PDO instance)
require_once __DIR__ . '/inc/config.php';

// Simple esc helper if not defined
if (!function_exists('h')) {
  function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

$page_title = "Products";
$msg = null;
$err = null;

// Ensure the table has a buy_price (purchase price) column
try {
  $pdo->exec("ALTER TABLE products ADD COLUMN buy_price DECIMAL(10,2) NULL DEFAULT 0");
} catch (Throwable $e) {
  // ignore if column already exists
}

$mode = isset($_POST['mode']) ? $_POST['mode'] : 'create';

// Handle create / update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id    = isset($_POST['id']) ? (int)$_POST['id'] : 0;
  $name  = trim($_POST['name'] ?? '');
  $price = (float)($_POST['price'] ?? 0);        // sale price
  $buy_price = (float)($_POST['buy_price'] ?? 0);// purchase price
  $stock = (int)($_POST['stock'] ?? 0);

  if ($name === '') {
    $err = "Name is required.";
  } else {
    if ($mode === 'update' && $id > 0) {
      $stmt = $pdo->prepare("UPDATE products SET name=?, price=?, buy_price=?, stock=? WHERE id=?");
      $stmt->execute([$name, $price, $buy_price, $stock, $id]);
      $msg = "Product updated.";
    } else {
      $stmt = $pdo->prepare("INSERT INTO products (name, price, buy_price, stock) VALUES (?,?,?,?)");
      $stmt->execute([$name, $price, $buy_price, $stock]);
      $msg = "Product added.";
    }
  }
}

// Handle delete
if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'delete') {
  $id = (int)$_GET['id'];
  if ($id > 0) {
    $pdo->prepare("UPDATE products SET is_active=0 WHERE id=?")->execute([$id]);

    $msg = "Product deleted.";
    // Redirect to avoid resubmission of query params
    header("Location: products.php?msg=" . urlencode($msg));
    exit;
  }
}

// Message from redirect
if (isset($_GET['msg'])) { $msg = $_GET['msg']; }

// If editing, load the row
$editRow = null;
if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'edit') {
  $id = (int)$_GET['id'];
  $stmt = $pdo->prepare("SELECT * FROM products WHERE id=?");
  $stmt->execute([$id]);
  $editRow = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$editRow) {
    $err = "Product not found.";
  }
}

// Fetch rows
$rows = $pdo->query("SELECT * FROM products where is_active = 1 ORDER BY created_at DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="card">
  <h2><?= h($editRow ? 'Edit Product' : 'Add Product') ?></h2>
  <?php if ($msg): ?><p class="success"><?= h($msg) ?></p><?php endif; ?>
  <?php if ($err): ?><p class="error"><?= h($err) ?></p><?php endif; ?>

  <form method="post" action="products.php<?= $editRow ? '?action=edit&id='.(int)$editRow['id']:'' ?>">
  <input type="hidden" name="mode" value="<?= $editRow ? 'update':'create' ?>">
  <?php if($editRow): ?><input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>"><?php endif; ?>

  <div class="form-row">
    <div style="flex:1">
      <label>Name</label>
      <input type="text" name="name" value="<?=h($editRow['name']??'')?>" required>
    </div>
    <div style="flex:1">
      <label>Sale Price</label>
      <input type="number" step="0.01" name="price" value="<?=h($editRow['price']??'')?>" required>
    </div>
    <div style="flex:1">
      <label>Buy Price</label>
      <input type="number" step="0.01" name="buy_price" value="<?=h($editRow['buy_price']??'')?>" required>
    </div>
    <div style="flex:1">
      <label>Stock</label>
      <input type="number" name="stock" value="<?=h($editRow['stock']??'')?>" min="0" required>
    </div>
  </div>

  <button type="submit" class="btn"><?= $editRow?'Update':'Save' ?></button>
  <?php if($editRow): ?><a href="products.php" class="btn">Cancel</a><?php endif; ?>
</form>


  <hr>

  <h3>All Products</h3>
  <div class="table-responsive">
    <table class="table">
      <thead>
        <tr>
          <th>#</th>
          <th>Name</th>
          <th>Sale Price</th>
          <th>Buy Price</th>
          <th>Stock</th>
          <th width="160">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="6" style="text-align:center">No products yet.</td></tr>
      <?php else: foreach($rows as $r): ?>
        <tr>
          <td><?= (int)$r['id'] ?></td>
          <td><?= h($r['name']) ?></td>
          <td>$<?= number_format((float)$r['price'], 2) ?></td>
          <td>$<?= number_format((float)($r['buy_price'] ?? 0), 2) ?></td>
          <td><?= (int)$r['stock'] ?></td>
          <td>
            <a class="btn btn-small" href="products.php?action=edit&id=<?= (int)$r['id'] ?>">Edit</a>
            <a class="btn btn-small btn-danger" href="products.php?action=delete&id=<?= (int)$r['id'] ?>" onclick="return confirm('Delete this product?');">Delete</a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
