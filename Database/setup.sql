-- 1. ตารางผู้ใช้งานระบบแบดมินตัน (badminton_users)
CREATE TABLE IF NOT EXISTS `s68160383`.`badminton_users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `full_name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(190) NOT NULL UNIQUE,
    `phone_number` VARCHAR(20) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('PLAYER', 'OWNER', 'STAFF') NOT NULL DEFAULT 'PLAYER',
    `credit_balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. ตารางสถานที่/สนามหลัก
CREATE TABLE IF NOT EXISTS `s68160383`.`venues` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `address` TEXT NOT NULL,
    `cancellation_policy` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. ตารางคอร์ทแต่ละสนาม
CREATE TABLE IF NOT EXISTS `s68160383`.`courts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `venue_id` INT NOT NULL,
    `court_name` VARCHAR(50) NOT NULL,
    `court_type` ENUM('RUBBER', 'WOOD', 'CEMENT') DEFAULT 'RUBBER',
    `is_active` TINYINT(1) DEFAULT 1,
    FOREIGN KEY (`venue_id`) REFERENCES `s68160383`.`venues`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. ตารางสล็อตเวลาและราคา
CREATE TABLE IF NOT EXISTS `s68160383`.`court_slots` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `court_id` INT NOT NULL,
    `slot_date` DATE NOT NULL,
    `start_time` TIME NOT NULL,
    `end_time` TIME NOT NULL,
    `price` DECIMAL(10,2) NOT NULL,
    `is_available` TINYINT(1) DEFAULT 1,
    FOREIGN KEY (`court_id`) REFERENCES `s68160383`.`courts`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_slot` (`court_id`, `slot_date`, `start_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. ตารางการล็อกคอร์ทชั่วคราวกันจองซ้ำ
CREATE TABLE IF NOT EXISTS `s68160383`.`slot_holds` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `court_slot_id` BIGINT NOT NULL,
    `user_id` INT NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`court_slot_id`) REFERENCES `s68160383`.`court_slots`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `s68160383`.`badminton_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. ตารางข้อมูลการจองหลัก
CREATE TABLE IF NOT EXISTS `s68160383`.`bookings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `court_slot_id` BIGINT NOT NULL,
    `total_price` DECIMAL(10,2) NOT NULL,
    `status` ENUM('PENDING', 'CONFIRMED', 'CANCELLED', 'FOR_RESALE') DEFAULT 'PENDING',
    `booking_source` ENUM('ONLINE', 'WALK_IN', 'PHONE') DEFAULT 'ONLINE',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `s68160383`.`badminton_users`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`court_slot_id`) REFERENCES `s68160383`.`court_slots`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. ตารางการชำระเงิน
CREATE TABLE IF NOT EXISTS `s68160383`.`payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `booking_id` INT NOT NULL UNIQUE,
    `amount` DECIMAL(10,2) NOT NULL,
    `payment_method` VARCHAR(50) NOT NULL,
    `transaction_ref` VARCHAR(100) NULL,
    `status` ENUM('PENDING', 'SUCCESS', 'FAILED', 'REFUNDED') DEFAULT 'PENDING',
    `paid_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`booking_id`) REFERENCES `s68160383`.`bookings`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. ตารางตลาดปล่อยคิวต่อ / คอร์ทหลุด
CREATE TABLE IF NOT EXISTS `s68160383`.`resale_slots` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `booking_id` INT NOT NULL,
    `seller_id` INT NOT NULL,
    `buyer_id` INT NULL,
    `resale_price` DECIMAL(10,2) NOT NULL,
    `status` ENUM('OPEN', 'SOLD', 'EXPIRED') DEFAULT 'OPEN',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`booking_id`) REFERENCES `s68160383`.`bookings`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`seller_id`) REFERENCES `s68160383`.`badminton_users`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`buyer_id`) REFERENCES `s68160383`.`badminton_users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- เพิ่มข้อมูลผู้ใช้งานตัวอย่างของระบบแบดมินตัน
INSERT INTO `s68160383`.`badminton_users` (`id`, `full_name`, `email`, `phone_number`, `password_hash`, `role`, `credit_balance`) VALUES
(1, 'สมชาย สายตี', 'somchai@example.com', '0812345678', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe11.7588102377488', 'PLAYER', 100.00),
(2, 'วิชัย ใจดี', 'wichai@example.com', '0898765432', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe11.7588102377488', 'PLAYER', 0.00),
(3, 'เจ้าของสนาม Smash', 'owner@smashbadminton.com', '021112222', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe11.7588102377488', 'OWNER', 0.00);

-- เพิ่มข้อมูลสนามและสล็อตเวลาตัวอย่าง
INSERT INTO `s68160383`.`venues` (`id`, `name`, `address`, `cancellation_policy`) VALUES
(1, 'Smash Badminton Club', '99/1 ถ.บางนา-ตราด กทม.', 'ยกเลิกก่อน 24 ชม. คืนเงิน 100%');

INSERT INTO `s68160383`.`courts` (`id`, `venue_id`, `court_name`, `court_type`, `is_active`) VALUES
(1, 1, 'คอร์ท 1 (ยาง PBSI)', 'RUBBER', 1),
(2, 1, 'คอร์ท 2 (ยาง PBSI)', 'RUBBER', 1);

INSERT INTO `s68160383`.`court_slots` (`court_id`, `slot_date`, `start_time`, `end_time`, `price`, `is_available`) VALUES
(1, CURDATE(), '17:00:00', '18:00:00', 200.00, 1),
(1, CURDATE(), '18:00:00', '19:00:00', 250.00, 1),
(2, CURDATE(), '17:00:00', '18:00:00', 200.00, 1),
(2, CURDATE(), '18:00:00', '19:00:00', 250.00, 1);