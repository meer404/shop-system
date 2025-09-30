<?php
$page = 'point_system.php';

require_once 'header.php';
require_once  '../inc/auth.php';
require_once '../inc/config.php';

function safe($v){ return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

// Fetch all available point items for the dropdown
$items_list = $pdo->query("SELECT id, name, price, points FROM point_items ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

// --- POST HANDLER: SAVE RECEIPT ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_receipt'])) {
    try {
        $pdo->beginTransaction();

        // Header info
        $receipt_name = trim($_POST['receipt_name'] ?? '');
        $receipt_date = trim($_POST['receipt_date'] ?? date('Y-m-d'));
        $place = trim($_POST['place'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $note = trim($_POST['note'] ?? '');

        if (empty($receipt_name) || empty($receipt_date)) {
            throw new Exception("ناوی پسوڵە و بەروار پێویستن.");
        }

        // Line items
        $item_ids = $_POST['item_id'] ?? [];
        $qtys = $_POST['qty'] ?? [];
        $item_points_manual = $_POST['item_points'] ?? [];
        $item_prices_manual = $_POST['item_price'] ?? [];

        $rows = [];
        $grand_total = 0.00;

        foreach ($item_ids as $i => $item_id) {
            $item_id = (int)$item_id;
            $qty = isset($qtys[$i]) ? (float)$qtys[$i] : 0;
            if ($item_id <= 0 || $qty <= 0) continue;

            $stmt = $pdo->prepare("SELECT name FROM point_items WHERE id = ?");
            $stmt->execute([$item_id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$item) continue;

            $item_points = (float)($item_points_manual[$i] ?? 0);
            $item_price  = (float)($item_prices_manual[$i] ?? 0);

            $total_points = $qty * $item_points;
            $line_total = $item_price * $total_points;
            $grand_total += $line_total;

            $rows[] = [
                'item_name' => $item['name'],
                'item_price' => $item_price,
                'item_points' => $item_points,
                'qty' => $qty,
                'total_points' => $total_points,
                'line_total' => $line_total,
            ];
        }

        if (count($rows) === 0) {
            throw new Exception("لانیکەم یەک کاڵای دروست بۆ پسوڵەکە زیاد بکە.");
        }

        // Insert main receipt record
        $stmt = $pdo->prepare("INSERT INTO point_receipts (receipt_name, receipt_date, place, phone, note, grand_total) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$receipt_name, $receipt_date, $place, $phone, $note, $grand_total]);
        $receipt_id = (int)$pdo->lastInsertId();

        $receipt_no = 'PR-' . date('Ymd') . '-' . str_pad((string)$receipt_id, 4, '0', STR_PAD_LEFT);
        $pdo->prepare("UPDATE point_receipts SET receipt_no = ? WHERE id = ?")->execute([$receipt_no, $receipt_id]);

        $stmt = $pdo->prepare("INSERT INTO point_receipt_items (receipt_id, item_name, item_price, item_points, qty, total_points, line_total) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($rows as $r) {
            $stmt->execute([$receipt_id, $r['item_name'], $r['item_price'], $r['item_points'], $r['qty'], $r['total_points'], $r['line_total']]);
        }

        $pdo->commit();

        header('Location: point_receipt_new.php?success=' . urlencode('point_receipts.php?view_receipt=' . $receipt_id));
        exit;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        header('Location: point_receipt_new.php?error=' . urlencode($e->getMessage()));
        exit;
    }
}


$page_title = "پسوڵەی خاڵی نوێ";

?>

<?php if ($success): ?>
    <div class="success">بە سەرکەوتوویی پاشەکەوت کرا! <a class="btn btn-small" href="<?= safe($success) ?>">کردنەوەی پسوڵە</a></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="danger"><strong>هەڵە:</strong> <?= safe($error) ?></div>
<?php endif; ?>

<form method="post" id="receiptForm">
    <div class="card">
        <h2>وردەکارییەکانی پسوڵە</h2>
        <div class="form-row">
            <div>
                <label>ناوی پسوڵە</label>
                <input type="text" name="receipt_name" placeholder="بۆ نموونە، ناوی کڕیار یان پڕۆژە" required>
            </div>
            <div>
                <label>بەروار</label>
                <input type="date" name="receipt_date" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div>
                <label>نرخی بنەڕەت بۆ هەر خاڵێک</label>
                <input type="number" id="master_price_setter" min="0" step="0.01" value="0.00" style="text-align:right;">
            </div>
        </div>
        <div class="form-row">
            <div>
                <label>شوێن (ئارەزوومەندانە)</label>
                <input type="text" name="place" placeholder="بۆ نموونە، شار، لق">
            </div>
            <div>
                <label>ژمارەی تەلەفۆن (ئارەزوومەندانە)</label>
                <input type="text" name="phone" placeholder="بۆ نموونە، ٠٧٧٠ ١٢٣ ٤٥٦٧">
            </div>
        </div>
         <div class="form-row">
            <div style="width:100%">
                 <label>تێبینی (ئارەزوومەندانە)</label>
                <textarea name="note" placeholder="هەر تێبینییەکی زیادە دەربارەی ئەم پسوڵەیە" style="width:100%; min-height: 60px;"></textarea>
            </div>
        </div>
    </div>

    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h2>کاڵاکان</h2>
            <div>
                <button type="button" class="btn" onclick="addRow()">+ زیادکردنی کاڵا</button>
                <button type="submit" name="save_receipt" class="btn">پاشەکەوتکردنی پسوڵە</button>
            </div>
        </div>

        <table id="itemsTbl">
            <thead>
                <tr>
                    <th style="width:25%">ناوی کاڵا</th>
                    <th style="width:10%">بڕ</th>
                    <th style="width:10%">خاڵەکان</th>
                    <th style="width:15%">نرخ بۆ هەر خاڵێک</th>
                    <th style="width:15%">کۆی خاڵەکان</th>
                    <th style="width:15%">کۆی گشتی</th>
                    <th style="width:10%">کردارەکان</th>
                </tr>
            </thead>
            <tbody id="itemsBody">
                </tbody>
        </table>
         <div class="form-row" style="justify-content: flex-end; margin-top: 1rem;">
            <span class="badge">کۆی گشتی: <strong id="totalText">$0.00</strong></span>
        </div>
    </div>
</form>

<script>
const itemsTbody = document.getElementById('itemsBody');
const totalTextEl = document.getElementById('totalText');
const masterPriceSetterEl = document.getElementById('master_price_setter');
const itemsData = <?= json_encode($items_list) ?>;

function escapeHTML(str) {
    const p = document.createElement('p');
    p.textContent = str;
    return p.innerHTML;
}

function money(n) {
    return (Math.round((n + Number.EPSILON) * 100) / 100).toFixed(2);
}

function recalcRow(tr) {
    const qtyInput = tr.querySelector('input[name="qty[]"]');
    const pointsInput = tr.querySelector('input[name="item_points[]"]');
    const priceInput = tr.querySelector('input[name="item_price[]"]');
    
    const totalPointsInput = tr.querySelector('.total_points');
    const lineTotalInput = tr.querySelector('.line_total');

    const qty = parseFloat(qtyInput.value || '0');
    const points = parseFloat(pointsInput.value || '0');
    const price = parseFloat(priceInput.value || '0');

    const totalPoints = qty * points;
    const lineTotal = price * totalPoints;

    totalPointsInput.value = money(totalPoints);
    lineTotalInput.value = '$' + money(lineTotal);
}

function recalcAll() {
    let grandTotal = 0;
    itemsTbody.querySelectorAll('tr').forEach(tr => {
        const lineTotalStr = tr.querySelector('.line_total').value.replace('$', '');
        grandTotal += parseFloat(lineTotalStr || '0');
    });
    totalTextEl.textContent = '$' + money(grandTotal);
}

function attachRowHandlers(tr) {
    const allInputs = tr.querySelectorAll('input, select');
    
    allInputs.forEach(input => {
        input.addEventListener('input', () => {
            if (input.name === 'item_id[]') {
                const selectedId = input.value;
                const item = itemsData.find(i => i.id == selectedId);
                if(item){
                    tr.querySelector('input[name="item_points[]"]').value = money(item.points);
                }
            }
            recalcRow(tr);
            recalcAll();
        });
    });

    tr.querySelector('.removeRow').addEventListener('click', () => {
        if (itemsTbody.querySelectorAll('tr').length > 1) {
            tr.remove();
            recalcAll();
        }
    });
}

function addRow() {
    const tr = document.createElement('tr');
    let optionsHtml = '<option value="">-- هەڵبژاردنی کاڵا --</option>';
    itemsData.forEach(item => {
        optionsHtml += `<option value="${item.id}">${escapeHTML(item.name)}</option>`;
    });

    const defaultPrice = money(masterPriceSetterEl.value);

    tr.innerHTML = `
        <td><select name="item_id[]" required>${optionsHtml}</select></td>
        <td><input type="number" name="qty[]" min="0" step="0.01" value="1.00" required></td>
        <td><input type="number" name="item_points[]" min="0" step="0.01" value="0.00" required></td>
        <td><input type="number" name="item_price[]" min="0" step="0.01" value="${defaultPrice}" required></td>
        <td><input type="text" class="total_points" value="0.00" readonly></td>
        <td><input type="text" class="line_total" value="$0.00" readonly style="font-weight:bold;"></td>
        <td class="row-actions">
            <button type="button" class="btn btn-warning btn-small removeRow">X</button>
        </td>
    `;
    itemsTbody.appendChild(tr);
    attachRowHandlers(tr);
    tr.querySelector('select[name="item_id[]"]').focus();
}

masterPriceSetterEl.addEventListener('input', () => {
    const newPrice = money(masterPriceSetterEl.value);
    itemsTbody.querySelectorAll('tr').forEach(tr => {
        tr.querySelector('input[name="item_price[]"]').value = newPrice;
        recalcRow(tr);
    });
    recalcAll();
});


document.addEventListener('DOMContentLoaded', () => {
    if (itemsData.length > 0) {
       addRow();
    } else {
        itemsTbody.innerHTML = '<tr><td colspan="7" style="text-align:center;">هیچ کاڵایەکی خاڵدار هێشتا دروست نەکراوە. <a href="point_items.php">سەرەتا یەکێک دروست بکە</a>.</td></tr>';
    }
});
</script>


<?php require_once 'footer.php'; ?>