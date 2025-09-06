<?php 
$page = 'sale_new.php'; 
require_once __DIR__ . '/header.php'; 
require __DIR__.'/inc/config.php'; 
require_once __DIR__ . '/inc/auth.php';
$page_title="New Sale"; 
$msg=null; 
$last_sale_id=null;

$customers=$pdo->query("SELECT id,name FROM customers ORDER BY name")->fetchAll();
$products=$pdo->query("SELECT id,name,price,stock FROM products where is_active=1 ORDER BY name")->fetchAll();

if($_SERVER['REQUEST_METHOD']==='POST'){
  $note = trim($_POST['note'] ?? '');
  $customer_id=(int)($_POST['customer_id']??0); 
  $items=$_POST['items']??[]; 
  $paid=(float)($_POST['paid']??0);
  $discount=(float)($_POST['discount']??0);

  if($customer_id<=0){ $msg="Select a customer."; }
  elseif(empty($items)){ $msg="Add at least one item."; }
  else{
    $subtotal=0.0;
    foreach($items as $it){
      $pid=(int)($it['product_id']??0); 
      $qty=(int)($it['qty']??0);
      if($pid<=0 or $qty<=0) continue;
      $p=$pdo->prepare("SELECT price,stock FROM products WHERE id=?"); 
      $p->execute([$pid]); 
      $pinfo=$p->fetch();
      if(!$pinfo){ $msg="Product not found."; break; }
      if($pinfo['stock']<$qty){ $msg="Not enough stock for a product."; break; }
      $subtotal += (float)$pinfo['price'] * $qty;
    }

    if($msg===null){
      $total = max(0, $subtotal - $discount); // apply discount safely
      $is_credit = $paid < $total ? 1 : 0;

      $pdo->beginTransaction();
      try{
        $pdo->prepare("INSERT INTO sales (customer_id, subtotal, paid, is_credit, note, discount) VALUES (?,?,?,?,?,?)")
            ->execute([$customer_id,$total,$paid,$is_credit,$note,$discount]);
        $sale_id=(int)$pdo->lastInsertId(); $last_sale_id=$sale_id;

        foreach($items as $it){
          $pid=(int)($it['product_id']??0); 
          $qty=(int)($it['qty']??0); 
          if($pid<=0 or $qty<=0) continue;
          $p=$pdo->prepare("SELECT price FROM products WHERE id=?"); 
          $p->execute([$pid]); 
          $price=(float)$p->fetch()['price']; 
          $line=$price*$qty;
          $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, qty, price, line_total) VALUES (?,?,?,?,?)")
              ->execute([$sale_id,$pid,$qty,$price,$line]);
          $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id=?")->execute([$qty,$pid]);
        }

        $pdo->commit();
        $msg = "Sale saved. <a href='sale_receipt.php?id={$last_sale_id}'>Open receipt</a>";
      }catch(Exception $e){ 
        $pdo->rollBack(); 
        $msg="Error saving sale: ".h($e->getMessage()); 
      }
    }
  }
}
require __DIR__.'/inc/header.php'; 
?>
<div class="card">
  <h2>New Sale</h2>
  <?php if($msg): ?><p class="success"><?= $msg ?></p><?php endif; ?>
  <form method="post" action="sale_new.php" id="saleForm">
    <div class="form-row">
      <label>Customer:</label>
      <select name="customer_id" required>
        <option value="">-- Select --</option>
        <?php foreach($customers as $c): ?>
          <option value="<?= (int)$c['id'] ?>"><?= h($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <label>Paid now:</label>
      <input type="number" step="0.01" name="paid" value="0">
      <label>Discount:</label>
      <input type="number" step="0.01" name="discount" id="discount" value="0" oninput="recalc()">
      <button type="button" class="noprint" onclick="addRow()">+ Add Item</button>
      <button type="submit" class="noprint">Save Sale</button>
    </div>

    <table id="itemsTbl">
      <thead>
        <tr>
          <th>Product</th><th>Price</th><th>Stock</th><th>Qty</th><th>Line Total</th><th></th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>

    <div class="form-row">
      <label for="note">Note:</label>
      <textarea id="note" name="note" rows="5" cols="120" placeholder="Write a Note For more Detailes"></textarea>
    </div>
  </form>
</div>

<script>
const products = <?= json_encode($products) ?>;

function addRow(){
  const tb=document.querySelector('#itemsTbl tbody');
  const tr=document.createElement('tr'); 
  const idx=tb.children.length;
  tr.innerHTML=`
    <td>
      <select name="items[${idx}][product_id]" onchange="updateRow(this); recalc();">
        <option value="">-- Select --</option>
        ${products.map(p=>`<option value="${p.id}" data-price="${p.price}" data-stock="${p.stock}">${p.name}</option>`).join('')}
      </select>
    </td>
    <td class="price">$0.00</td>
    <td class="stock">0</td>
    <td><input type="number" name="items[${idx}][qty]" value="1" min="1" oninput="recalc()"></td>
    <td class="lineTotal">$0.00</td>
    <td><button type="button" onclick="this.closest('tr').remove(); recalc()">Remove</button></td>
  `;
  tb.appendChild(tr);
}

function updateRow(sel){
  const o=sel.selectedOptions[0], tr=sel.closest('tr');
  tr.querySelector('.price').textContent='$'+Number(o.dataset.price||0).toFixed(2);
  tr.querySelector('.stock').textContent=o.dataset.stock||0;
}

function money(n){ return Number(n||0).toFixed(2); }

function recalc(){
  const rows = Array.from(document.querySelectorAll('#itemsTbl tbody tr'));
  let subtotal = 0;

  rows.forEach(tr=>{
    const sel = tr.querySelector('select[name*="[product_id]"]');
    const opt = sel && sel.selectedOptions ? sel.selectedOptions[0] : null;
    const price = opt ? parseFloat(opt.dataset.price || 0) : 0;
    const qty = parseInt(tr.querySelector('input[name*="[qty]"]')?.value || '0', 10);
    const line = price * qty;
    subtotal += line;
    if (tr.querySelector('.price')) tr.querySelector('.price').textContent = '$' + money(price);
    if (tr.querySelector('.lineTotal')) tr.querySelector('.lineTotal').textContent = '$' + money(line);
  });

  const discount = parseFloat(document.getElementById('discount').value || 0);
  const total = subtotal - discount;

  document.getElementById('subtotal').textContent = money(subtotal);
  document.getElementById('total').textContent = money(total >= 0 ? total : 0);
}

document.addEventListener('DOMContentLoaded', recalc);
</script>

<div class="form-row">
  <span class="badge"> Subtotal $<strong id="subtotal">0.00</strong></span>
  <span class="badge"> Discount $<strong id="discountShow">0.00</strong></span>
  <span class="badge"> Total $<strong id="total">0.00</strong></span>
</div>

<?php require __DIR__.'/inc/footer.php'; ?>
<?php require_once __DIR__ . '/footer.php'; ?>
