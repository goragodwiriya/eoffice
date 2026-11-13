-- ---------------------------------------------------------------------------
-- modules/car/install/database.sql — ตารางที่โมดูล car เป็นเจ้าของ
--
-- **ประกาศที่นี่ที่เดียว** ห้ามประกาศซ้ำใน install/database.sql ของโปรเจ็ค
-- และห้ามเขียน CREATE TABLE ซ้ำไว้ในตัวปรับรุ่นอีกชุด
--
-- ข้อกำหนด: InnoDB + utf8mb4 และเขียน PRIMARY KEY/KEY ไว้ในคำสั่ง CREATE TABLE
-- เลย — ของเดิมแยกไปเป็น ALTER TABLE ท้ายไฟล์แบบที่ phpMyAdmin ส่งออกมา
-- ทำให้ตารางที่ ensureTable สร้างตอนปรับรุ่นไม่ได้ดัชนีติดมาด้วย
-- ---------------------------------------------------------------------------

CREATE TABLE `{prefix}_vehicles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `number` varchar(20) NOT NULL DEFAULT '',
  `color` varchar(20) NOT NULL DEFAULT '',
  `detail` text NOT NULL,
  `seats` int(11) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `{prefix}_vehicles_meta` (
  `vehicle_id` int(11) NOT NULL,
  `name` varchar(20) NOT NULL,
  `value` varchar(150) NOT NULL,
  KEY `idx_vehicle_meta` (`vehicle_id`,`name`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `{prefix}_car_reservation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vehicle_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `department` varchar(10) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `detail` text NOT NULL,
  `chauffeur` int(11) NOT NULL,
  `comment` text DEFAULT NULL,
  `travelers` int(11) NOT NULL,
  `begin` datetime DEFAULT NULL,
  `end` datetime DEFAULT NULL,
  `status` tinyint(1) NOT NULL,
  `reason` text DEFAULT NULL,
  `approve` tinyint(1) NOT NULL,
  `closed` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_vehicle_availability` (`vehicle_id`,`status`,`approve`,`begin`,`end`),
  KEY `member_id` (`member_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `{prefix}_car_reservation_data` (
  `reservation_id` int(11) NOT NULL,
  `name` varchar(20) NOT NULL,
  `value` varchar(150) NOT NULL,
  UNIQUE KEY `idx_reservation` (`reservation_id`,`name`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `{prefix}_vehicles` (`id`, `number`, `color`, `detail`, `seats`, `is_active`) VALUES
(1, 'นม 6', '#304FFE', 'พร้อมเครื่องเสียงชุดใหญ่', 50, 1),
(2, 'บจ 888', '#4A148C', '', 13, 1),
(3, 'กข 1234', '#B71C1C', '', 4, 1);

-- ยี่ห้อ/ประเภทอ้างหมวดตัวอย่างข้างล่าง (นม 6 = รถบัส Hino · บจ 888 = รถตู้ Toyota · กข 1234 = รถเก๋ง Nissan)
INSERT INTO `{prefix}_vehicles_meta` (`vehicle_id`, `name`, `value`) VALUES
(1, 'car_brand', '3'),
(1, 'car_type', '4'),
(2, 'car_brand', '1'),
(2, 'car_type', '3'),
(3, 'car_brand', '2'),
(3, 'car_type', '2');

-- ---------------------------------------------------------------------------
-- ข้อมูลตัวอย่าง (การติดตั้งใหม่เท่านั้น) — หมวดของรถ และใบจองที่อ้างสมาชิกตัวอย่าง
-- ของโมดูล personnel (2 สมชาย · 3 สมศรี · 4 วิชัย พนักงานขับรถ · 5 มานี)
-- chauffeur : -1 ขับเอง · 0 ไม่ระบุ · >0 id ของพนักงานขับรถ
-- ---------------------------------------------------------------------------
INSERT INTO `{prefix}_category` (`type`, `category_id`, `topic`, `color`, `is_active`) VALUES
('car_type', '1', 'รถกระบะ', NULL, 1),
('car_type', '2', 'รถยนต์นั่งส่วนบุคคล', NULL, 1),
('car_type', '3', 'รถตู้', NULL, 1),
('car_type', '4', 'รถบัส', NULL, 1),
('car_brand', '1', 'Toyota', NULL, 1),
('car_brand', '2', 'Nissan', NULL, 1),
('car_brand', '3', 'Hino', NULL, 1),
('car_accessory', '1', 'เติมน้ำมันเต็มถัง', NULL, 1),
('car_accessory', '2', 'เครื่องเสียงประชาสัมพันธ์', NULL, 1),
('car_accessory', '3', 'บัตรผ่านทางด่วน', NULL, 1);

INSERT INTO `{prefix}_car_reservation` (`id`, `vehicle_id`, `member_id`, `department`, `created_at`, `detail`, `chauffeur`, `comment`, `travelers`, `begin`, `end`, `status`, `reason`, `approve`, `closed`) VALUES
(1, 2, 3, '2', NOW() - INTERVAL 1 DAY, 'ตรวจรับพัสดุ ณ สำนักงานเขตพื้นที่', 4, NULL, 4, CURDATE() + INTERVAL 2 DAY + INTERVAL 8 HOUR, CURDATE() + INTERVAL 2 DAY + INTERVAL 16 HOUR, 1, NULL, 1, 0),
(2, 3, 2, '1', NOW() - INTERVAL 3 DAY, 'ประชุมที่ศาลากลางจังหวัด', -1, 'ขับเอง', 2, CURDATE() + INTERVAL 8 HOUR, CURDATE() + INTERVAL 12 HOUR, 1, NULL, 1, 0),
(3, 1, 5, '3', NOW(), 'พาบุคลากรไปศึกษาดูงาน', 4, 'ออกเดินทางหน้าอาคาร 1', 40, CURDATE() + INTERVAL 10 DAY + INTERVAL 7 HOUR, CURDATE() + INTERVAL 10 DAY + INTERVAL 18 HOUR, 0, NULL, 1, 0);

INSERT INTO `{prefix}_car_reservation_data` (`reservation_id`, `name`, `value`) VALUES
(1, 'car_accessories', '1'),
(3, 'car_accessories', '1,2');
