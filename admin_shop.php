<?php
// admin_shop.php
require_once __DIR__ . '/crud_bootstrap.php';

if (($_SESSION['role'] ?? '') !== 'OWNER') {
    exit('Unauthorized');
}

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_product') {
    verify_csrf();
    $productId = valid_id($_POST['product_id'] ?? null);
    $price = filter_var($_POST['price'] ?? 0, FILTER_VALIDATE_FLOAT);
    $stock = (int)($_POST['stock'] ?? 0);

    if ($productId && $price !== false) {
        $stmt = mysqli_prepare($conn, "UPDATE products SET price = ?, stock = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "dii", $price, $stock, $productId);
        if (mysqli_stmt_execute($stmt)) {
            $msg = "อัปเดตข้อมูลสินค้าเรียบร้อยแล้ว";
        } else {
            $err = "เกิดข้อผิดพลาดในการอัปเดต";
        }
        mysqli_stmt_close($stmt);
    }
}

$products = mysqli_query($conn, "SELECT * FROM products ORDER BY id ASC");

page_start("จัดการราคาสินค้าและสต็อก (แอดมิน)");
?>

<?php if ($msg) echo '<p class="ok" style="background:#e8f5e9; color:#2e7d32; padding:10px; border-radius:4px; font-weight:bold;">' . h($msg) . '</p>'; ?>
<?php if ($err) echo '<p class="err" style="background:#ffebee; color:#c62828; padding:10px; border-radius:4px; font-weight:bold;">' . h($err) . '</p>'; ?>

<table class="data-table" style="width:100%; border-collapse:collapse; margin-top:20px; text-align:center;">
    <thead>
        <tr style="background:#f0f4f8;">
            <th style="padding:10px; border:1px solid #ddd;">ชื่อสินค้า</th>
            <th style="padding:10px; border:1px solid #ddd;">ประเภท</th>
            <th style="padding:10px; border:1px solid #ddd;">ราคา (บาท)</th>
            <th style="padding:10px; border:1px solid #ddd;">จำนวนสต็อก</th>
            <th style="padding:10px; border:1px solid #ddd;">จัดการ</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($p = mysqli_fetch_assoc($products)): ?>
            <tr>
                <td style="padding:10px; border:1px solid #ddd; text-align:left;"><strong><?= h($p['name']) ?></strong></td>
                <td style="padding:10px; border:1px solid #ddd;"><?= $p['category'] === 'TUBE' ? 'แบบหลอด' : 'แบบรายลูก' ?></td>
                <form method="post">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="update_product">
                    <input type="hidden" name="product_id" value="<?= h($p['id']) ?>">
                    <td style="padding:10px; border:1px solid #ddd;">
                        <input type="number" name="price" value="<?= h($p['price']) ?>" step="0.01" style="width:90px; padding:5px; text-align:center;">
                    </td>
                    <td style="padding:10px; border:1px solid #ddd;">
                        <input type="number" name="stock" value="<?= h($p['stock']) ?>" style="width:80px; padding:5px; text-align:center;">
                    </td>
                    <td style="padding:10px; border:1px solid #ddd;">
                        <button type="submit" style="padding:6px 12px; background:#1565c0; color:white; border:none; border-radius:4px; font-weight:bold; cursor:pointer;">บันทึก</button>
                    </td>
                </form>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<?php page_end(); ?>