<?php
// shop.php
date_default_timezone_set('Asia/Bangkok');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/crud_bootstrap.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
$msg = '';
$err = '';

// 1. จัดการการซื้อสินค้า
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'buy_product') {
    verify_csrf();
    $productId = valid_id($_POST['product_id'] ?? null);
    $qty = (int)($_POST['quantity'] ?? 1);

    if ($productId && $qty > 0) {
        mysqli_begin_transaction($conn);
        try {
            $stmt = mysqli_prepare($conn, "SELECT price, stock, name FROM products WHERE id = ? AND is_active = 1");
            mysqli_stmt_bind_param($stmt, "i", $productId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $p = mysqli_fetch_assoc($res);
            mysqli_stmt_close($stmt);

            if (!$p || $p['stock'] < $qty) {
                throw new Exception("สินค้าหมดหรือจำนวนสต็อกไม่เพียงพอ");
            }

            $totalPrice = $p['price'] * $qty;

            // บันทึกออเดอร์
            $stmt = mysqli_prepare($conn, "INSERT INTO shop_orders (user_id, total_price, status) VALUES (?, ?, 'COMPLETED')");
            mysqli_stmt_bind_param($stmt, "id", $userId, $totalPrice);
            mysqli_stmt_execute($stmt);
            $orderId = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            // บันทึกรายการ
            $stmt = mysqli_prepare($conn, "INSERT INTO shop_order_items (order_id, product_id, quantity, price_per_unit) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "iiid", $orderId, $productId, $qty, $p['price']);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            // ตัดสต็อก
            $stmt = mysqli_prepare($conn, "UPDATE products SET stock = stock - ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "ii", $qty, $productId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            mysqli_commit($conn);
            header("Location: shop_receipt_qr.php?order_id=" . $orderId);
            exit;
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            $err = $e->getMessage();
        }
    }
}

// 2. จัดการการยกเลิกการสั่งซื้อสินค้า (Cancel Order & Refund Stock)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel_order') {
    verify_csrf();
    $orderId = valid_id($_POST['order_id'] ?? null);

    if ($orderId && $userId) {
        mysqli_begin_transaction($conn);
        try {
            // ดึงข้อมูลออเดอร์ (ต้องเป็นของตัวเอง และยังไม่ได้สแกนรับสินค้า)
            $stmt = mysqli_prepare($conn, "SELECT id, status, is_picked_up FROM shop_orders WHERE id = ? AND user_id = ? FOR UPDATE");
            mysqli_stmt_bind_param($stmt, "ii", $orderId, $userId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $order = mysqli_fetch_assoc($res);
            mysqli_stmt_close($stmt);

            if (!$order) {
                throw new Exception("ไม่พบรายการสั่งซื้อนี้");
            }

            if ($order['status'] === 'CANCELLED') {
                throw new Exception("รายการสั่งซื้อนี้ถูกยกเลิกไปแล้ว");
            }

            if (!empty($order['is_picked_up'])) {
                throw new Exception("ไม่สามารถยกเลิกได้ เนื่องจากคุณได้รับสินค้าไปแล้ว");
            }

            // ดึงรายการสินค้าทั้งหมดในออเดอร์นี้เพื่อคืนสต็อก
            $itemsStmt = mysqli_prepare($conn, "SELECT product_id, quantity FROM shop_order_items WHERE order_id = ?");
            mysqli_stmt_bind_param($itemsStmt, "i", $orderId);
            mysqli_stmt_execute($itemsStmt);
            $itemsRes = mysqli_stmt_get_result($itemsStmt);
            
            while ($item = mysqli_fetch_assoc($itemsRes)) {
                $upStock = mysqli_prepare($conn, "UPDATE products SET stock = stock + ? WHERE id = ?");
                mysqli_stmt_bind_param($upStock, "ii", $item['quantity'], $item['product_id']);
                mysqli_stmt_execute($upStock);
                mysqli_stmt_close($upStock);
            }
            mysqli_stmt_close($itemsStmt);

            // อัปเดตสถานะออเดอร์เป็น CANCELLED
            $upOrder = mysqli_prepare($conn, "UPDATE shop_orders SET status = 'CANCELLED', cancelled_at = NOW() WHERE id = ?");
            mysqli_stmt_bind_param($upOrder, "i", $orderId);
            mysqli_stmt_execute($upOrder);
            mysqli_stmt_close($upOrder);

            mysqli_commit($conn);
            $msg = "ยกเลิกการสั่งซื้อออเดอร์ #$orderId เรียบร้อยแล้ว (คืนสินค้าเข้าสต็อกแล้ว)";
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            $err = $e->getMessage();
        }
    }
}

// 3. ดึงรายการสินค้าสำหรับเปิดขาย
$products = mysqli_query($conn, "SELECT * FROM products WHERE is_active = 1 ORDER BY id ASC");

// 4. ดึงรายการที่ผู้ใช้นี้เคยสั่งซื้อ
$myOrdersSql = "
    SELECT so.id as order_id, so.total_price, so.status, so.created_at, so.is_picked_up,
           GROUP_CONCAT(CONCAT(p.name, ' x', soi.quantity) SEPARATOR ', ') as items_summary
    FROM shop_orders so
    JOIN shop_order_items soi ON so.id = soi.order_id
    JOIN products p ON soi.product_id = p.id
    WHERE so.user_id = ?
    GROUP BY so.id
    ORDER BY so.created_at DESC
";
$stmt = mysqli_prepare($conn, $myOrdersSql);
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$myOrdersRes = mysqli_stmt_get_result($stmt);

page_start("ร้านค้าอุปกรณ์แบดมินตัน");

if ($msg) echo '<p class="ok" style="background:#e8f5e9; color:#2e7d32; padding:10px; border-radius:4px; font-weight:bold;">✅ ' . h($msg) . '</p>';
if ($err) echo '<p class="err" style="background:#ffebee; color:#c62828; padding:10px; border-radius:4px; font-weight:bold;">⚠️ ' . h($err) . '</p>';
?>

<h2>🛍️ เลือกซื้อลูกแบดมินตัน</h2>
<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:20px; margin-top:15px; margin-bottom:40px;">
    <?php if ($products && mysqli_num_rows($products) > 0): ?>
        <?php while ($prod = mysqli_fetch_assoc($products)): ?>
            <div class="card" style="background:#fff; border:1px solid #ddd; padding:20px; border-radius:8px; text-align:center;">
                <div style="font-size:3rem; margin-bottom:10px;">🏸</div>
                <h3 style="margin:0; color:#135c9a;"><?= h($prod['name']) ?></h3>
                <p style="color:#666; font-size:0.9rem;"><?= $prod['category'] === 'TUBE' ? 'แบบหลอด (12 ลูก)' : 'แบบแยกขายรายลูก' ?></p>
                <p style="font-size:1.4rem; color:#2e7d32; font-weight:bold; margin:10px 0;">฿<?= number_format($prod['price'], 2) ?></p>
                <p style="color:#555; font-size:0.85rem;">คงเหลือ: <strong><?= number_format($prod['stock']) ?></strong> <?= $prod['category'] === 'TUBE' ? 'หลอด' : 'ลูก' ?></p>

                <form method="post" style="margin-top:15px;">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="buy_product">
                    <input type="hidden" name="product_id" value="<?= h($prod['id']) ?>">
                    
                    <div style="display:flex; justify-content:center; align-items:center; gap:10px; margin-bottom:10px;">
                        <label>จำนวน:</label>
                        <input type="number" name="quantity" value="1" min="1" max="<?= $prod['stock'] ?>" style="width:60px; padding:5px; text-align:center;">
                    </div>

                    <button type="submit" <?= $prod['stock'] <= 0 ? 'disabled' : '' ?> style="width:100%; padding:10px; background:<?= $prod['stock'] > 0 ? '#2e7d32' : '#ccc' ?>; color:white; border:none; border-radius:4px; font-weight:bold; cursor:pointer;">
                        <?= $prod['stock'] > 0 ? '🛒 สั่งซื้อทันที' : 'สินค้าหมด' ?>
                    </button>
                </form>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p style="text-align:center; grid-column:1/-1; padding:30px; background:#f9f9f9; border-radius:8px;">ยังไม่มีสินค้าเปิดขายในขณะนี้</p>
    <?php endif; ?>
</div>

<h2>📋 รายการสั่งซื้อของฉัน (สำหรับรับของ)</h2>
<div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap:15px; margin-top:15px;">
    <?php if ($myOrdersRes && mysqli_num_rows($myOrdersRes) > 0): ?>
        <?php while ($ord = mysqli_fetch_assoc($myOrdersRes)): ?>
            <div class="card" style="background:#fff; border:1px solid #ddd; padding:15px; border-radius:8px;">
                <h4 style="margin-top:0; color:#135c9a;">ออเดอร์ #<?= h($ord['order_id']) ?></h4>
                <p style="margin:5px 0; font-size:0.95rem;">📦 <strong>สินค้า:</strong> <?= h($ord['items_summary']) ?></p>
                <p style="margin:5px 0; font-size:0.95rem;">💰 <strong>รวม:</strong> ฿<?= number_format($ord['total_price'], 2) ?></p>
                <p style="margin:5px 0; font-size:0.85rem; color:#777;">📅 สั่งเมื่อ: <?= h($ord['created_at']) ?></p>
                <p style="margin:5px 0; font-size:0.9rem;">สถานะ: 
                    <?php if ($ord['status'] === 'CANCELLED'): ?>
                        <span style="color:red; font-weight:bold;">ยกเลิกรายการแล้ว</span>
                    <?php elseif (!empty($ord['is_picked_up'])): ?>
                        <span style="color:green; font-weight:bold;">รับสินค้าแล้ว</span>
                    <?php else: ?>
                        <span style="color:#ef6c00; font-weight:bold;">รอรับสินค้า</span>
                    <?php endif; ?>
                </p>

                <div style="margin-top:12px; display:flex; gap:10px; flex-wrap:wrap;">
                    <?php if ($ord['status'] !== 'CANCELLED'): ?>
                        <a href="shop_receipt_qr.php?order_id=<?= h($ord['order_id']) ?>" style="flex:1; text-align:center; background:#1565c0; color:white; text-decoration:none; padding:8px; border-radius:4px; font-weight:bold; font-size:0.9rem;">
                            📦 ดู QR Code
                        </a>

                        <?php if (empty($ord['is_picked_up'])): ?>
                            <form method="post" onsubmit="return confirm('ยืนยันยกเลิกการสั่งซื้อออเดอร์นี้? (ระบบจะคืนจำนวนสินค้าเข้าสต็อก)');" style="margin:0; flex:1;">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="action" value="cancel_order">
                                <input type="hidden" name="order_id" value="<?= h($ord['order_id']) ?>">
                                <button type="submit" style="width:100%; background:#c62828; color:white; border:none; padding:8px; border-radius:4px; font-weight:bold; font-size:0.9rem; cursor:pointer;">
                                    ❌ ยกเลิกสั่งซื้อ
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p style="color:#777; grid-column:1/-1; padding:20px; background:#f9f9f9; border-radius:8px; text-align:center;">คุณยังไม่มีประวัติการสั่งซื้อสินค้า</p>
    <?php endif; ?>
</div>

<?php page_end(); ?>