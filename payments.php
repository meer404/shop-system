<?php $page = 'payments.php'; require_once __DIR__ . '/header.php'; require_once __DIR__ . '/inc/auth.php'; ?>
<?php
require __DIR__.'/inc/config.php'; $page_title="Payments"; $msg=null;
$customers=$pdo->query("SELECT id,name FROM customers ORDER BY name")->fetchAll();
if($_SERVER['REQUEST_METHOD']==='POST'){
  $customer_id=(int)($_POST['customer_id']??0); $amount=(float)($_POST['amount']??0); $note=trim($_POST['note']??'');
  if($customer_id<=0 || $amount<=0){ $msg="Select a customer and enter amount > 0."; }
  else{ $pdo->prepare("INSERT INTO payments (customer_id, amount, note) VALUES (?,?,?)")->execute([$customer_id,$amount,$note]); $msg="Payment recorded."; }
}
$customer_id_filter=isset($_GET['customer_id'])?(int)$_GET['customer_id']:0;
$q="SELECT p.*, c.name AS customer_name FROM payments p JOIN customers c ON c.id=p.customer_id";
if($customer_id_filter>0){
  $stmt=$pdo->prepare($q." WHERE p.customer_id=? ORDER BY p.paid_at DESC, p.id DESC");
  $stmt->execute([$customer_id_filter]);
  $rows=$stmt->fetchAll();
}else{
  $rows=$pdo->query($q." ORDER BY p.paid_at DESC, p.id DESC")->fetchAll();
}
require __DIR__.'/inc/header.php'; ?>
<div class="card">
  <h2>Add Payment</h2>
  <?php if($msg): ?><p class="success"><?= h($msg) ?></p><?php endif; ?>
  <form method="post" action="payments.php">
    <div class="form-row">
      <select name="customer_id" required>
        <option value="">-- Customer --</option>
        <?php foreach($customers as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= $customer_id_filter==$c['id']?'selected':'' ?>><?= h($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="number" step="0.01" name="amount" placeholder="Amount" required>
      <textarea name="note" placeholder="Note (optional)"></textarea>
      <button type="submit">Save</button>
    </div>
  </form>
</div>
<div class="card">
  <h3>Payments</h3>
  <table><thead><tr><th>#</th><th>Customer</th><th>Amount</th><th>When</th><th>Note</th></tr></thead><tbody>
    <?php foreach($rows as $r): ?>
    <tr>
      <td><?= (int)$r['id'] ?></td>
      <td><a href="customer_view.php?id=<?= (int)$r['customer_id'] ?>"><?= h($r['customer_name']) ?></a></td>
      <td>$<?= number_format((float)$r['amount'],2) ?></td>
      <td><?= h($r['paid_at']) ?></td>
      <td><?= h($r['note']) ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody></table>
</div>
<?php require __DIR__.'/inc/footer.php'; ?>
<?php require_once __DIR__ . '/footer.php'; ?>
