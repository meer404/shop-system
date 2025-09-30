<?php
$page = 'point_system.php';
require_once 'header.php';
require_once  '../inc/auth.php';
require_once '../inc/config.php';

$page_title = "کاڵاکانی خاڵ";
$msg = null;
$error = null;

// Handle form submission to add a new item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_item'])) {
    $name = trim($_POST['name'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $points = (float)($_POST['points'] ?? 0);

    if (empty($name) || $price < 0 || $points < 0) {
        $error = "ناو پێویستە، و نرخ/خاڵەکان نابێت نەرێنی بن.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO point_items (name, price, points) VALUES (?, ?, ?)");
            $stmt->execute([$name, $price, $points]);
            $msg = "کاڵای '$name' بە سەرکەوتوویی پاشەکەوت کرا.";
        } catch (Throwable $e) {
            $error = "هەڵە لە پاشەکەوتکردنی کاڵا: " . $e->getMessage();
        }
    }
}

// Fetch all existing items to display in the table
$items = $pdo->query("SELECT * FROM point_items ORDER BY name ASC")->fetchAll();
?>
<div class="card">
    <h2>زیادکردنی کاڵای خاڵ</h2>
    <?php if ($msg): ?><p class="success"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="danger"><?= htmlspecialchars($error) ?></p><?php endif; ?>

    <form method="post" action="point_items.php">
        <div class="form-row">
            <div style="flex:2;">
                <label for="name">ناوی کاڵا</label>
                <input type="text" id="name" name="name" placeholder="بۆ نموونە، کوپی قاوە" required>
            </div>
            <div style="flex:1;">
                <label for="price">نرخ</label>
                <input type="number" id="price" name="price" min="0" step="0.01" value="0.00" required>
            </div>
            <div style="flex:1;">
                <label for="points">خاڵەکان</label>
                <input type="number" id="points" name="points" min="0" step="0.01" value="0.00" required>
            </div>
            <button type="submit" name="save_item">پاشەکەوتکردنی کاڵا</button>
        </div>
    </form>
</div>

<div class="card">
    <h3>هەموو کاڵاکانی خاڵ</h3>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ناسنامە</th>
                    <th>ناو</th>
                    <th>نرخ</th>
                    <th>خاڵەکان</th>
                    <th>کاتی دروستکردن</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$items): ?>
                    <tr><td colspan="5" style="text-align:center;">هیچ کاڵایەک نەدۆزرایەوە. لە سەرەوە یەکێک زیاد بکە.</td></tr>
                <?php else: foreach ($items as $item): ?>
                    <tr>
                        <td><?= (int)$item['id'] ?></td>
                        <td><?= htmlspecialchars($item['name']) ?></td>
                        <td>$<?= number_format((float)$item['price'], 2) ?></td>
                        <td><?= number_format((float)$item['points'], 2) ?></td>
                        <td><?= htmlspecialchars($item['created_at']) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>