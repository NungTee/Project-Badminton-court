-- ==========================================
-- 1. ตารางผู้ใช้งานระบบ (Users)
-- ==========================================
CREATE TABLE IF NOT EXISTS `badminton_users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `role` ENUM('MEMBER', 'OWNER') DEFAULT 'MEMBER',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 2. ตารางสนามหลัก (Venues)
-- ==========================================
CREATE TABLE IF NOT EXISTS `venues` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `address` TEXT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 3. ตารางคอร์ท (Courts)
-- ==========================================
CREATE TABLE IF NOT EXISTS `courts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `venue_id` INT NOT NULL,
  `court_name` VARCHAR(50) NOT NULL,
  `court_type` ENUM('RUBBER', 'WOOD') DEFAULT 'RUBBER',
  `is_active` TINYINT(1) DEFAULT 1,
  FOREIGN KEY (`venue_id`) REFERENCES `venues`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 4. ตารางสล็อตเวลาจองสนาม (Court Slots)
-- ==========================================
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

-- ==========================================
-- 5. ตารางกักสล็อตเวลาชั่วคราว (Slot Holds)
-- ==========================================
CREATE TABLE IF NOT EXISTS `slot_holds` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `court_slot_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `expires_at` DATETIME NOT NULL,
  FOREIGN KEY (`court_slot_id`) REFERENCES `court_slots`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `badminton_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 6. ตารางรายการจองสนาม (Bookings)
-- ==========================================
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

-- ==========================================
-- 7. ตารางชำระเงิน (Payments)
-- ==========================================
CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `booking_id` INT NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `payment_method` VARCHAR(50) DEFAULT 'PROMPTPAY',
  `status` ENUM('PAID', 'REFUNDED') DEFAULT 'PAID',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 8. ตารางสินค้าอุปกรณ์/ลูกแบด (Products)
-- ==========================================
CREATE TABLE IF NOT EXISTS `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `stock` INT NOT NULL DEFAULT 0,
  `image` VARCHAR(255) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 9. ตารางคำสั่งซื้อสินค้า (Shop Orders)
-- ==========================================
CREATE TABLE IF NOT EXISTS `shop_orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `total_price` DECIMAL(10,2) NOT NULL,
  `pickup_code` VARCHAR(50) NULL,
  `status` ENUM('PAID', 'COMPLETED', 'CANCELLED') DEFAULT 'PAID',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `badminton_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 10. ตารางรายการสินค้าในคำสั่งซื้อ (Shop Order Items)
-- ==========================================
CREATE TABLE IF NOT EXISTS `shop_order_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `quantity` INT NOT NULL,
  `unit_price` DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (`order_id`) REFERENCES `shop_orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 11. ตารางตลาดคอร์ทหลุด (Resale Slots)
-- ==========================================
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

-- ==========================================
-- 12. ตารางกลุ่มหารค่าคอร์ท (Booking Splits)
-- ==========================================
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

-- ==========================================
-- 13. ตารางรายการชำระของคนหารบิล (Booking Split Items)
-- ==========================================
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