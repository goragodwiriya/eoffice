-- ---------------------------------------------------------------------------
-- modules/repair/install/database.sql — ตารางที่โมดูล repair เป็นเจ้าของ
--
-- **ประกาศที่นี่ที่เดียว** ห้ามประกาศซ้ำใน install/database.sql ของโปรเจ็ค
-- ประกาศสองที่ = ติดตั้งใหม่ล้มด้วย "Table already exists" และนิยามสองชุด
-- จะค่อย ๆ ต่างกันจนไซต์ที่อัปเกรดคนละเส้นทางได้สคีมาไม่เหมือนกัน
--
-- ทั้งการติดตั้งใหม่ (common.php::schemaFiles) และการปรับรุ่น (ensureTable)
-- อ่านนิยามจากไฟล์นี้ไฟล์เดียว
-- ---------------------------------------------------------------------------

CREATE TABLE `{prefix}_repair` (
`id` int(11) NOT NULL AUTO_INCREMENT,
`customer_id` int(11) NOT NULL,
`product_no` varchar(150) NOT NULL,
`job_id` varchar(20) NOT NULL,
`job_description` varchar(1000) NOT NULL,
`created_at` datetime NOT NULL,
`appointment_date` date DEFAULT NULL,
`repair_no` varchar(50) DEFAULT NULL,
`informer` varchar(150) DEFAULT NULL,
`appraiser` float NOT NULL DEFAULT 0,
PRIMARY KEY (`id`),
UNIQUE KEY `job_id` (`job_id`),
KEY `product_no` (`product_no`),
KEY `idx_customer` (`customer_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `{prefix}_repair_status` (
`id` int(11) NOT NULL AUTO_INCREMENT,
`repair_id` int(11) NOT NULL,
`status` tinyint(2) NOT NULL,
`operator_id` int(11) NOT NULL DEFAULT 0,
`comment` varchar(1000) DEFAULT NULL,
`member_id` int(11) NOT NULL,
`created_at` datetime NOT NULL,
`cost` float NOT NULL DEFAULT 0,
PRIMARY KEY (`id`),
KEY `idx_repair` (`repair_id`,`id`),
KEY `idx_status` (`status`,`created_at`),
KEY `operator_id` (`operator_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- ข้อมูลตั้งต้นของโมดูลนี้
--
-- ⚠️ ต้องอยู่ที่นี่ ไม่ใช่ใน install/database.sql ของโปรเจ็ค เพราะ schemaFiles()
-- รันไฟล์ของโปรเจ็ค **ก่อน** ไฟล์ของโมดูล — INSERT ที่อยู่ฝั่งโปรเจ็คจะวิ่งไปหา
-- ตารางที่ยังไม่ถูกสร้าง แล้วการติดตั้งใหม่ล้มทันที
-- ---------------------------------------------------------------------------

INSERT INTO `{prefix}_category` (`type`, `category_id`, `topic`, `color`, `is_active`) VALUES
('repairstatus', '1', 'แจ้งซ่อม', '#660000', 1),
('repairstatus', '2', 'กำลังดำเนินการ', '#120eeb', 1),
('repairstatus', '3', 'รออะไหล่', '#d940ff', 1),
('repairstatus', '4', 'ซ่อมสำเร็จ', '#06d628', 1),
('repairstatus', '5', 'ซ่อมไม่สำเร็จ', '#FF0000', 1),
('repairstatus', '6', 'ยกเลิกการซ่อม', '#FF6F00', 1),
('repairstatus', '7', 'ส่งมอบเรียบร้อย', '#000000', 1);

-- ---------------------------------------------------------------------------
-- ข้อมูลตัวอย่าง (การติดตั้งใหม่เท่านั้น) — งานซ่อมของครุภัณฑ์ตัวอย่างในโมดูล inventory
-- ผู้แจ้งเป็นผู้ถือครองเครื่องนั้น (3 สมศรี · 5 มานี) ช่างคือ 2 สมชาย (can_repair)
--   JOB0001 Notebook NB-0001  กำลังรออะไหล่
--   JOB0002 เครื่องพิมพ์ PR-0001 ซ่อมเสร็จ ส่งมอบแล้ว
--   JOB0003 จอ MN-0001        แจ้งซ่อมวันนี้ ยังไม่มีช่างรับ (ขึ้นการ์ด "งานซ่อมวันนี้")
-- เลขที่ใบแจ้งซ่อมต่อไปเริ่มที่ JOB0004 (แถว number ข้างล่าง รูปแบบตั้งต้น JOB%04d)
-- ---------------------------------------------------------------------------
INSERT INTO `{prefix}_repair` (`id`, `customer_id`, `product_no`, `job_id`, `job_description`, `created_at`, `appointment_date`, `repair_no`, `informer`, `appraiser`) VALUES
(1, 3, 'NB-0001', 'JOB0001', 'เปิดเครื่องไม่ติด เสียบสายชาร์จแล้วไฟไม่เข้า', NOW() - INTERVAL 3 DAY, NULL, NULL, NULL, 0),
(2, 5, 'PR-0001', 'JOB0002', 'พิมพ์ออกมาเป็นเส้น หมึกไม่ออกบางสี', NOW() - INTERVAL 10 DAY, NULL, NULL, NULL, 0),
(3, 3, 'MN-0001', 'JOB0003', 'หน้าจอกะพริบเป็นระยะ', NOW() - INTERVAL 1 HOUR, NULL, NULL, NULL, 0);

INSERT INTO `{prefix}_repair_status` (`id`, `repair_id`, `status`, `operator_id`, `comment`, `member_id`, `created_at`, `cost`) VALUES
(1, 1, 1, 0, 'ใช้งานประจำวัน ต้องการด่วน', 3, NOW() - INTERVAL 3 DAY, 0),
(2, 1, 2, 2, 'ตรวจสอบแล้ว อะแดปเตอร์ชำรุด', 2, NOW() - INTERVAL 2 DAY, 0),
(3, 1, 3, 2, 'สั่งอะแดปเตอร์ใหม่แล้ว รอของ 3-5 วัน', 2, NOW() - INTERVAL 1 DAY, 450),
(4, 2, 1, 0, NULL, 5, NOW() - INTERVAL 10 DAY, 0),
(5, 2, 4, 2, 'ล้างหัวพิมพ์และเปลี่ยนตลับหมึก', 2, NOW() - INTERVAL 8 DAY, 350),
(6, 2, 7, 2, 'ส่งคืนผู้ใช้งานเรียบร้อย', 2, NOW() - INTERVAL 7 DAY, 0),
(7, 3, 1, 0, NULL, 3, NOW() - INTERVAL 1 HOUR, 0);

INSERT INTO `{prefix}_number` (`type`, `prefix`, `auto_increment`, `updated_at`) VALUES
('JOB%04d', '', 3, CURDATE());
