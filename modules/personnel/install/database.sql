-- ---------------------------------------------------------------------------
-- modules/personnel/install/database.sql — ตารางที่โมดูล personnel เป็นเจ้าของ
--
-- **ประกาศที่นี่ที่เดียว** ห้ามประกาศซ้ำใน install/database.sql ของโปรเจ็ค
--
-- personnel ต่อ 1:1 กับ user (personnel.id = user.id) เก็บข้อมูลที่เป็นของ
-- บุคลากรเท่านั้น ชื่อ เบอร์โทร วันเกิด สถานะ อยู่ที่ตาราง user
--
-- คอลัมน์ทุกตัวเหมือนระบบเดิม (SMS 6.x) ทุกประการ ไซต์ที่ย้ายมาจึงไม่ต้องย้ายข้อมูล
--   position / department = category_id ของหมวดหมู่ position / department
--   class / room          = ครูประจำชั้น (category_id ของ class / room)
--   custom                = ข้อมูลเพิ่มเติมตาม PERSONNEL_DETAILS เป็น JSON
--                           (ระบบเดิมเป็น PHP serialize — upgrade.php แปลงให้)
-- ---------------------------------------------------------------------------

CREATE TABLE `{prefix}_personnel` (
  `id` int(11) NOT NULL,
  `position` int(11) NOT NULL,
  `department` int(11) NOT NULL,
  `order` tinyint(3) NOT NULL DEFAULT 0,
  `custom` text DEFAULT NULL,
  `employee_code` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `employee_code` (`employee_code`),
  KEY `position` (`position`,`order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- ข้อมูลตัวอย่างของ E-Office (เฉพาะการติดตั้งใหม่ — ตัวปรับรุ่นไม่รัน INSERT)
--
-- ตำแหน่ง (แผนกเป็นหมวดกลาง department ของ install/database.sql — ห้ามประกาศซ้ำที่นี่)
-- ---------------------------------------------------------------------------
INSERT INTO `{prefix}_category` (`type`, `category_id`, `language`, `topic`, `color`, `is_active`) VALUES
('position', '1', '', 'ผู้อำนวยการ', NULL, 1),
('position', '2', '', 'รองผู้อำนวยการ', NULL, 1),
('position', '3', '', 'หัวหน้างาน', NULL, 1),
('position', '4', '', 'เจ้าหน้าที่', NULL, 1);

-- ---------------------------------------------------------------------------
-- สมาชิกตัวอย่าง (ผู้ดูแลระบบ id 1 สร้างโดยตัวติดตั้ง)
--
-- ⚠️ password ว่าง = เข้าระบบไม่ได้จนกว่าผู้ดูแลจะตั้งรหัสผ่านให้ (Kotchasan\Password::verify
-- ปฏิเสธรหัสผ่านที่เก็บไว้ว่างเสมอ) — ไม่มีบัญชีที่เดารหัสผ่านได้ติดมากับการติดตั้ง
--   2 สมชาย  บุคลากร ช่างซ่อม (can_repair)        แผนกบริหาร
--   3 สมศรี  บุคลากร ผู้จัดการห้องประชุม/รถ/พัสดุ   แผนกจัดซื้อจัดจ้าง
--   4 วิชัย  พนักงานขับรถ (can_drive_car)         แผนกบริหาร
--   5 มานี   สมาชิกทั่วไป                          แผนกบุคคล
-- ---------------------------------------------------------------------------
INSERT INTO `{prefix}_user` (`id`, `username`, `salt`, `password`, `status`, `permission`, `name`, `sex`, `phone`, `created_at`, `active`) VALUES
(2, 'somchai@localhost', '', '', 2, ',can_repair,', 'สมชาย ใจดี', 'm', '0810000002', NOW(), 1),
(3, 'somsri@localhost', '', '', 2, ',can_manage_booking,can_manage_car,can_manage_inventory,can_upload_edocument,', 'สมศรี รักงาน', 'f', '0810000003', NOW(), 1),
(4, 'wichai@localhost', '', '', 3, ',can_drive_car,', 'วิชัย ขับดี', 'm', '0810000004', NOW(), 1),
(5, 'manee@localhost', '', '', 0, '', 'มานี มีสุข', 'f', '0810000005', NOW(), 1);

INSERT INTO `{prefix}_user_meta` (`value`, `name`, `member_id`) VALUES
('1', 'department', 1),
('1', 'department', 2),
('2', 'department', 3),
('1', 'department', 4),
('3', 'department', 5);

-- บุคลากร = สมาชิกสถานะ 1 (ผู้ดูแลระบบ) และ 2 (บุคลากร) ตาม personnel_status
INSERT INTO `{prefix}_personnel` (`id`, `position`, `department`, `order`, `custom`, `employee_code`) VALUES
(1, 1, 1, 1, '{"job":"บริหารงานทั่วไป"}', 'EMP001'),
(2, 4, 1, 3, '{"job":"งานซ่อมบำรุง"}', 'EMP002'),
(3, 3, 2, 2, '{"job":"งานพัสดุและยานพาหนะ"}', 'EMP003');
