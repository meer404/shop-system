<?php $page = 'customers.php'; require_once __DIR__ . '/header.php'; require_once __DIR__ . '/inc/auth.php'; ?>
<?php
require __DIR__.'/inc/config.php'; $page_title="Customers"; $msg=null;
if ($_SERVER['REQUEST_METHOD']==='POST'){
  $name=trim($_POST['name']??''); $phone=trim($_POST['phone']??'');
  if($name===''){ $msg="Name is required."; } else {
    $pdo->prepare("INSERT INTO customers (name, phone) VALUES (?,?)")->execute([$name,$phone]);
    $msg="Customer saved.";
  }
}
$rows=$pdo->query("SELECT * FROM customers ORDER BY created_at DESC, id DESC")->fetchAll();
require __DIR__.'/inc/header.php'; ?>
<div class="card">
  <h2>Add Customer</h2>
  <?php if($msg): ?><p class="success"><?= h($msg) ?></p><?php endif; ?>
  <form method="post" action="customers.php">
   <div class="form-row">
      <div style="flex:1">
      <label for="name">Customer Name</label>
      <input type="text" id="name" name="name" placeholder="Customer name" required>
      </div>
      <div style="flex:1">
      <label for="phone">Phone (optional)</label>
      <input type="text" id="phone" name="phone" placeholder="Phone (optional)">
      </div>
    
      <button type="submit">Save</button>
    </div>
  </form>
</div>
<div class="card">
  <h3>All Customers</h3>
  <table><thead><tr><th>#</th><th>Name</th><th>Phone</th><th>Actions</th></tr></thead><tbody>
  <?php foreach($rows as $r): ?>
    <tr>
      <td><?= (int)$r['id'] ?></td>
      <td><?= h($r['name']) ?></td>
      <td><?= h($r['phone']) ?></td>
      <td class="actions"><a href="customer_view.php?id=<?= (int)$r['id'] ?>">View</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table>
</div>
</div>
<?php require __DIR__.'/inc/footer.php'; ?>
<?php require_once __DIR__ . '/footer.php'; ?>
