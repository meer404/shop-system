<?php $page = 'customers.php'; require_once  'header.php'; require_once '../inc/auth.php'; ?>
<?php
require '../inc/config.php'; $page_title="كڕیاران"; $msg=null;
if ($_SERVER['REQUEST_METHOD']==='POST'){
  $name=trim($_POST['name']??''); $phone=trim($_POST['phone']??'');
  if($name===''){ $msg="Name is required."; } else {
    $pdo->prepare("INSERT INTO customers (name, phone) VALUES (?,?)")->execute([$name,$phone]);
    $msg="Customer saved.";
  }
}
$rows=$pdo->query("SELECT * FROM customers ORDER BY created_at DESC, id DESC")->fetchAll();
require '../inc/header.php'; ?>
<div class="card">
  <h2>زیادکردنی کڕیار</h2>
  <?php if($msg): ?><p class="success"><?= h($msg) ?></p><?php endif; ?>
  <form method="post" action="customers.php">
    <div class="form-row">
      <input name="name" placeholder="ناوی کڕیار" required>
      <input name="phone" placeholder="Phone (optional)">
      <button type="submit">سەیڤکردن</button>
    </div>
  </form>
</div>
<div class="card">
  <h3>كڕیاران</h3>
  <table><thead><tr><th>#</th><th>ناو</th><th>ژمارە مۆبایل</th><th>کردارەکان</th></tr></thead><tbody>
  <?php foreach($rows as $r): ?>
    <tr>
      <td><?= (int)$r['id'] ?></td>
      <td><?= h($r['name']) ?></td>
      <td><?= h($r['phone']) ?></td>
      <td class="actions"><a href="customer_view.php?id=<?= (int)$r['id'] ?>">بینین</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table>
</div>
<?php require '../inc/footer.php'; ?>
<?php require_once  'footer.php'; ?>
