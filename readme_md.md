# 🏸 Badminton Court Booking & Management Web Application (Clean Dynamic Style)

เว็บแอปพลิเคชันบริหารจัดการและจองสนามแบดมินตันแบบครบวงจร พัฒนาด้วย **Native PHP** ร่วมกับ **MySQL** ออกแบบด้วยสไตล์ **Clean Dynamic (Sport & Modern)** เน้นอินเทอร์เฟซที่สะอาดตา ขยายกว้างเต็มหน้าจอ (Full-width) อ่านง่าย และมีฟังก์ชันสำหรับการจอง การหารค่าคอร์ทกับเพื่อน ตลาดส่งต่อคอร์ทหลุด และระบบ E-Commerce ร้านค้าอุปกรณ์แบดมินตัน

---

## ✨ คุณสมบัติหลัก (Core Features)

- **🏟️ ตารางจองสล็อตเวลาอัตโนมัติ (Court Booking System):**
  - แสดงตารางช่วงเวลาจองสนาม (13:00 - 21:00 น.) แบบ Real-time
  - แถบควบคุมลอยตัว (Floating Control Bar) สำหรับเลือกวันที่ และกรองประเภทพื้นสนาม (ยาง PBSI / ไม้ปาร์เก้)
  - ระบบกักสล็อตเวลาชั่วคราว (Slot Hold System) ป้องกันปัญหาการจองซ้ำซ้อน

- **🍕 ระบบหารค่าคอร์ทกับเพื่อน (Split Bill Group System):**
  - คำนวณยอดชำระต่อคนตามจำนวนสมาชิกกลุ่มอัตโนมัติ
  - สร้างลิงก์และ QR Code สำหรับแชร์ให้เพื่อนในกลุ่มชำระเงิน
  - ตรวจสอบสถานะการชำระเงินของเพื่อนแต่ละคนแบบ Real-time

- **🔄 ตลาดคอร์ทหลุด (Resale Market System):**
  - ผู้จองที่ติดภารกิจกระทันหันสามารถนำสล็อตเวลามาลงประกาศขายต่อได้
  - สมาชิกท่านอื่นสามารถกดซื้อคอร์ทหลุดในราคาพิเศษ
  - ระบบโอนสิทธิ์การจองให้ผู้ซื้อใหม่ทันทีเมื่อชำระเงินสำเร็จ

- **🎫 บัตรเข้าสนาม E-Ticket & ร้านค้าออนไลน์ (E-Ticket & Shop Store):**
  - สร้าง QR Code E-Ticket สำหรับนำไปสแกนเช็กอินเข้าสนาม
  - ร้านค้าออนไลน์ซื้อลูกแบดมินตันและอุปกรณ์ พร้อม QR Code สำหรับรับของหน้าร้าน
  - ระบบตัด/คืนสต็อกสินค้าอัตโนมัติเมื่อมีการสั่งซื้อหรือยกเลิก

- **🔒 ระบบความปลอดภัยและการรับรองสิทธิ์ (Security & Authentication):**
  - ป้องกัน CSRF Attack ด้วย Dynamic CSRF Token ในทุกฟอร์ม
  - ป้องกัน SQL Injection ด้วย Prepared Statements (MySQLi)
  - ป้องกัน Browser Cache ค้างด้วย Dynamic Assets Versioning (`?v=timestamp`)

---

## 🛠️ เทคโนโลยีที่เลือกใช้ (Tech Stack)

- **Backend:** Native PHP (PHP 8.x)
- **Database:** MySQL / MariaDB (InnoDB Engine)
- **Frontend:** HTML5, CSS3 (Clean Dynamic Custom CSS), JavaScript (Vanilla JS)
- **Architecture:** Procedural with Bootstrap Helper Pattern (`crud_bootstrap.php`)

---

## 🗄️ โครงสร้างฐานข้อมูล (Database Schema)

ฐานข้อมูลชื่อ `s68160383` ประกอบด้วย 13 ตารางหลัก:

1. `badminton_users` - ข้อมูลผู้ใช้งาน และสิทธิ์ (`MEMBER` / `OWNER`)
2. `venues` - ข้อมูลสนามหลัก
3. `courts` - ข้อมูลคอร์ท และประเภทพื้นสนาม (`RUBBER` / `WOOD`)
4. `court_slots` - ข้อมูลสล็อตเวลา และราคา
5. `slot_holds` - ข้อมูลการกักสล็อตเวลาชั่วคราว
6. `bookings` - รายการจองสนาม และรหัส `entry_code`
7. `payments` - ประวัติการชำระเงิน
8. `products` - รายการสินค้าอุปกรณ์/ลูกแบดมินตัน และสต็อก
9. `shop_orders` - คำสั่งซื้อสินค้า และรหัสรับของ `pickup_code`
10. `shop_order_items` - รายการสินค้าย่อยในคำสั่งซื้อ
11. `resale_slots` - ข้อมูลการลงประกาศขายคอร์ทหลุด
12. `booking_splits` - กลุ่มการหารค่าคอร์ท
13. `booking_split_items` - รายการชำระเงินย่อยของผู้หารบิล

---

## 🚀 ขั้นตอนการติดตั้งและการใช้งาน (Installation Guide)

### 1. Clone Repository
```bash
git clone https://github.com/your-username/badminton-booking-app.git
cd badminton-booking-app
```

### 2. ตั้งค่า ฐานข้อมูล (Database Setup)
1. เปิด **phpMyAdmin** หรือ MySQL Client
2. สร้างฐานข้อมูลใหม่ชื่อ `s68160383` (หรือชื่อฐานข้อมูลที่คุณต้องการ)
3. นำคำสั่ง SQL ทั้งหมดจากไฟล์ `schema.sql` หรือคัดลอก DDL ด้านล่างนี้ไปรันในแท็บ SQL:

```sql
CREATE TABLE IF NOT EXISTS `badminton_users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `role` ENUM('MEMBER', 'OWNER') DEFAULT 'MEMBER',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `venues` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `address` TEXT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `courts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `venue_id` INT NOT NULL,
  `court_name` VARCHAR(50) NOT NULL,
  `court_type` ENUM('RUBBER', 'WOOD') DEFAULT 'RUBBER',
  `is_active` TINYINT(1) DEFAULT 1,
  FOREIGN KEY (`venue_id`) REFERENCES `venues`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `court_slots` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `court_id` INT NOT NULL,
  `slot_date` DATE NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `is_available` TINYINT(1) DEFAULT 1,
  FOREIGN KEY (`court_id`) REFERENCES `courts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `slot_holds` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `court_slot_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `expires_at` DATETIME NOT NULL,
  FOREIGN KEY (`court_slot_id`) REFERENCES `court_slots`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `badminton_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `bookings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `court_slot_id` INT NOT NULL,
  `total_price` DECIMAL(10,2) NOT NULL,
  `status` ENUM('CONFIRMED', 'CANCELLED') DEFAULT 'CONFIRMED',
  `entry_code` VARCHAR(50) NULL,
  `is_checked_in` TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `badminton_users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`court_slot_id`) REFERENCES `court_slots`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `booking_id` INT NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `payment_method` VARCHAR(50) DEFAULT 'PROMPTPAY',
  `status` ENUM('PAID', 'REFUNDED') DEFAULT 'PAID',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `stock` INT NOT NULL DEFAULT 0,
  `image` VARCHAR(255) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `shop_orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `total_price` DECIMAL(10,2) NOT NULL,
  `pickup_code` VARCHAR(50) NULL,
  `status` ENUM('PAID', 'COMPLETED', 'CANCELLED') DEFAULT 'PAID',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `badminton_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `shop_order_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `quantity` INT NOT NULL,
  `unit_price` DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (`order_id`) REFERENCES `shop_orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `resale_slots` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `booking_id` INT NOT NULL,
  `seller_user_id` INT NOT NULL,
  `seller_id` INT NOT NULL,
  `resale_price` DECIMAL(10,2) NOT NULL,
  `status` ENUM('OPEN', 'SOLD', 'CANCELLED') DEFAULT 'OPEN',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`seller_user_id`) REFERENCES `badminton_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `booking_splits` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `booking_id` INT NOT NULL,
  `host_user_id` INT NOT NULL,
  `total_amount` DECIMAL(10,2) NOT NULL,
  `split_count` INT NOT NULL DEFAULT 1,
  `per_person_amount` DECIMAL(10,2) NOT NULL,
  `status` ENUM('PENDING', 'COMPLETED', 'EXPIRED') DEFAULT 'PENDING',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `booking_split_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `split_id` INT NOT NULL,
  `user_id` INT NULL,
  `payer_name` VARCHAR(100) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `payment_status` ENUM('PENDING', 'PAID') DEFAULT 'PENDING',
  `paid_at` DATETIME NULL,
  FOREIGN KEY (`split_id`) REFERENCES `booking_splits`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 3. ตั้งค่าไฟล์เชื่อมต่อฐานข้อมูล (Database Connection)
แก้ไขไฟล์ `config/db.php` ให้ตรงกับโฮสต์และฐานข้อมูลของคุณ:
```php
<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "s68160383";

$conn = mysqli_connect($host, $user, $pass, $dbname);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
mysqli_set_charset($conn, "utf8mb4");
```

### 4. รันแอปพลิเคชัน
ย้ายโฟลเดอร์โครงการไปไว้ใน Web Server Directory (เช่น `htdocs` สำหรับ XAMPP หรือ `public_html`) แล้วเปิดผ่านเว็บเบราว์เซอร์:
```text
http://localhost/badminton/
```

---

## 👨‍💻 ผู้พัฒนา (Developer)

- **นาย ธีรพงศ์ แจ่มจำรัส** (รหัส 68160383)
- **นาย กสิวัฒน์ แซ่เห** (รหัส 68160180)
- สาขาปัญญาประดิษฐ์ประยุกต์และเทคโนโลยีอัจฉริยะ คณะวิทยาการสารสนเทศ มหาวิทยาลัยบูรพา

---

## 📄 License

โครงการนี้จัดทำขึ้นเพื่อการศึกษาภายใต้วิชาการ 89022167 การจัดการฐานข้อมูล มหาวิทยาลัยบูรพา