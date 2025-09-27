<?php
$page = 'point_system.php';
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/config.php';

$page_title = "Point Items";
$msg = null;
$error = null;

// Handle form submission to add a new item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_item'])) {
    $name = trim($_POST['name'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $points = (float)($_POST['points'] ?? 0);

    if (empty($name) || $price < 0 || $points < 0) {
        $error = "Name is required, and price/points cannot be negative.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO point_items (name, price, points) VALUES (?, ?, ?)");
            $stmt->execute([$name, $price, $points]);
            $msg = "Item '$name' saved successfully.";
        } catch (Throwable $e) {
            $error = "Error saving item: " . $e->getMessage();
        }
    }
}

// Fetch all existing items to display in the table
$items = $pdo->query("SELECT * FROM point_items ORDER BY name ASC")->fetchAll();

require __DIR__ . '/inc/header.php';
?>

<div class="card">
    <h2>Add New Point Item</h2>
    <?php if ($msg): ?><p class="success"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="danger"><?= htmlspecialchars($error) ?></p><?php endif; ?>

    <form method="post" action="point_items.php">
        <div class="form-row">
            <div style="flex:2">
                <label for="name">Item Name</label>
                <input type="text" id="name" name="name" placeholder="e.g., Service A" required>
            </div>
            <div style="flex:1">
                <label for="price">Price ($)</label>
                <input type="number" id="price" name="price" min="0" step="0.01" value="0.00" required>
            </div>
            <div style="flex:1">
                <label for="points">Points</label>
                <input type="number" id="points" name="points" min="0" step="0.01" value="0.00" required>
            </div>
            <button type="submit" name="save_item">Save Item</button>
        </div>
    </form>
</div>

<div class="card">
    <h3>All Point Items</h3>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Price</th>
                    <th>Points</th>
                    <th>Created At</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$items): ?>
                    <tr><td colspan="5" style="text-align:center;">No items found. Add one above.</td></tr>
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

<?php require __DIR__ . '/inc/footer.php'; ?>
<?php require_once __DIR__ . '/footer.php'; ?>