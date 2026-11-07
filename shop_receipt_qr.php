<?php
// shop_receipt_qr.php
date_default_timezone_set('Asia/Bangkok');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/crud_bootstrap.php';

$orderId = valid_id($_GET['order_id'] ?? null);
$userId = (int)($_SESSION['user_id'] ?? 0);
$userRole = $_SESSION['role'] ?? 'PLAYER';

if (!$orderId || !$userId) {
    header('Location: shop.php');
    exit;
}

$sql = "
    SELECT so.id as order_id, so.total_price, so.status, so.pickup_code, so.is_picked_up, so.created_at, so.user_id as owner_id,
           u.full_name, u.email, u.phone_number
    FROM shop_orders so
    JOIN badminton_users u ON so.user_id = u.id
    WHERE so.id = ?
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $orderId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$order = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$order || ($order['owner_id'] !== $userId && $userRole !== 'OWNER')) {
    page_start("ไม่พบรายการสั่งซื้อ");
    echo '<p class="err" style="color:#c62828; background:#ffebee; padding:10px; border-radius:4px;">ไม่พบรายการสั่งซื้อนี้ หรือคุณไม่มีสิทธิ์เข้าถึง</p>';
    echo '<p><a href="shop.php">← กลับสู่หน้าร้านค้า</a></p>';
    page_end();
    exit;
}

$itemsSql = "
    SELECT soi.quantity, soi.price_per_unit, p.name as product_name
    FROM shop_order_items soi
    JOIN products p ON soi.product_id = p.id
    WHERE soi.order_id = ?
";
$stmt = mysqli_prepare($conn, $itemsSql);
mysqli_stmt_bind_param($stmt, "i", $orderId);
mysqli_stmt_execute($stmt);
$itemsRes = mysqli_stmt_get_result($stmt);
$items = [];
while ($row = mysqli_fetch_assoc($itemsRes)) {
    $items[] = $row;
}
mysqli_stmt_close($stmt);

if (empty($order['pickup_code'])) {
    $code = 'SHOP-' . strtoupper(substr(md5($order['order_id'] . time()), 0, 8));
    $upStmt = mysqli_prepare($conn, "UPDATE shop_orders SET pickup_code = ? WHERE id = ?");
    mysqli_stmt_bind_param($upStmt, "si", $code, $order['order_id']);
    mysqli_stmt_execute($upStmt);
    mysqli_stmt_close($upStmt);
    $order['pickup_code'] = $code;
}

$qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=" . urlencode($order['pickup_code']);

page_start("คิวอาร์โค้ดรับสินค้า (E-Receipt)");
?>

<div class="card" style="max-width:480px; margin:0 auto; background:#fff; padding:25px; border-radius:12px; border:1px solid #ddd; text-align:center; box-shadow:0 4px 12px rgba(0,0,0,0.1);">
    <h2 style="margin-top:0; color:#135c9a;">📦 QR Code สำหรับรับสินค้า</h2>
    <p style="color:#666; font-size:0.9rem; margin-bottom:20px;">แสดง QR Code นี้แก่เจ้าหน้าที่หน้าเคาน์เตอร์เพื่อรับลูกแบดมินตัน</p>

    <?php if ($order['status'] === 'CANCELLED'): ?>
        <div style="background:#ffebee; color:#c62828; padding:15px; border-radius:8px; margin-bottom:20px; font-weight:bold;">
            ❌ ออเดอร์นี้ถูกยกเลิกแล้ว ไม่สามารถนำ QR Code นี้ไปรับสินค้าได้
        </div>
    <?php else: ?>
        <div style="background:#f9f9f9; padding:15px; border-radius:8px; display:inline-block; border:1px solid #eee;">
            <img src="<?= $qrUrl ?>" alt="QR Code Pickup" style="width:200px; height:200px; display:block; margin:0 auto;">
            <span style="display:block; margin-top:8px; font-family:monospace; font-size:1.1rem; font-weight:bold; color:#333;"><?= h($order['pickup_code']) ?></span>
        </div>
    <?php endif; ?>

    <div style="text-align:left; margin-top:20px; border-top:1px dashed #ccc; padding-top:15px; font-size:0.95rem; line-height:1.6;">
        <p style="margin:5px 0;">👤 <strong>ชื่อลูกค้า:</strong> <?= h($order['full_name']) ?></p>
        <p style="margin:5px 0;">📞 <strong>เบอร์โทรศัพท์:</strong> <?= h($order['phone_number'] ?: '-') ?></p>
        <p style="margin:5px 0;">🕒 <strong>วันที่สั่งซื้อ:</strong> <?= h($order['created_at']) ?></p>

        <h4 style="margin:15px 0 5px 0; color:#135c9a; border-bottom:1px solid #eee; padding-bottom:5px;">🛒 รายการสินค้าที่สั่งซื้อ:</h4>
        <ul style="padding-left:20px; margin:5px 0;">
            <?php foreach ($items as $item): ?>
                <li>
                    <strong><?= h($item['product_name']) ?></strong> 
                    x <?= h($item['quantity']) ?> 
                    (฿<?= number_format($item['price_per_unit'] * $item['quantity'], 2) ?>)
                </li>
            <?php endforeach; ?>
        </ul>

        <p style="margin-top:10px; font-size:1.1rem; color:#2e7d32; font-weight:bold;">ยอดชำระรวม: ฿<?= number_format($order['total_price'], 2) ?></p>

        <p style="margin:5px 0;">สถานะการรับของ: 
            <?php if ($order['status'] === 'CANCELLED'): ?>
                <span style="color:red; font-weight:bold;">❌ ยกเลิกการสั่งซื้อแล้ว</span>
            <?php elseif (!empty($order['is_picked_up'])): ?>
                <span style="color:green; font-weight:bold;">✅ รับสินค้าเรียบร้อยแล้ว</span>
            <?php else: ?>
                <span style="color:#ef6c00; font-weight:bold;">⏳ รอรับสินค้าหน้าเคาน์เตอร์</span>
            <?php endif; ?>
        </p>
    </div>

    <div style="margin-top:20px;">
        <a href="shop.php" style="color:#666; text-decoration:none;">← กลับไปยังหน้าร้านค้า</a>
    </div>
</div>

<?php page_end(); ?>