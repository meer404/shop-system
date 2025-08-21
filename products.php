<?php $page = 'products.php'; require_once __DIR__ . '/header.php'; ?>
<?php
require __DIR__.'/inc/config.php'; $page_title="Products"; $msg=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
  $name=trim($_POST['name']??''); $price=(float)($_POST['price']??0); $stock=(int)($_POST['stock']??0);
  if($name===''){ $msg="Name is required."; } else {
    $pdo->prepare("INSERT INTO products (name, price, stock) VALUES (?,?,?)")->execute([$name,$price,$stock]);
    $msg="Product saved.";
  }
}
$rows=$pdo->query("SELECT * FROM products ORDER BY created_at DESC, id DESC")->fetchAll();
require __DIR__.'/inc/header.php'; ?>
<div class="card">
  <h2>Add Product</h2>
  <?php if($msg): ?><p class="success"><?= h($msg) ?></p><?php endif; ?>
  <form method="post" action="products.php">
    <div class="form-row">
      <input name="name" placeholder="Product name" required>
      <input type="number" step="0.01" name="price" placeholder="Price" required>
      <input type="number" name="stock" placeholder="Stock" required>
      <button type="submit">Save</button>
    </div>
  </form>
</div>
<div class="card">
  <h3>All Products</h3>
  <table><thead><tr><th>#</th><th>Name</th><th>Price</th><th>Stock</th></tr></thead><tbody>
  <?php foreach($rows as $r): ?>
    <tr>
      <td><?= (int)$r['id'] ?></td>
      <td><?= h($r['name']) ?></td>
      <td>$<?= number_format((float)$r['price'],2) ?></td>
      <td><?= (int)$r['stock'] ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table>
</div>
<?php require __DIR__.'/inc/footer.php'; ?>
<?php require_once __DIR__ . '/footer.php'; ?>
