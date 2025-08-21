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
      <input name="name" placeholder="Customer name" required>
      <input name="phone" placeholder="Phone (optional)">
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
<?php require __DIR__.'/inc/footer.php'; ?>
