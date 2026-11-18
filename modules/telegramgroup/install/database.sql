-- ---------------------------------------------------------------------------
-- telegram_group — กลุ่ม Telegram ที่รับข้อความแจ้งเตือน (แทนกลุ่ม LINE Notify ของ E-Office เดิม)
--
-- department ว่าง = รับทุกแผนก · มีค่า = รับเฉพาะรายการของแผนกนั้น
-- modules = รายชื่อโมดูลที่ส่งเข้ากลุ่มนี้ คั่นด้วย , และมี , หัวท้าย เช่น ,booking,car,
-- ---------------------------------------------------------------------------
CREATE TABLE `{prefix}_telegram_group` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL DEFAULT '',
  `department` varchar(10) DEFAULT NULL,
  `chat_id` varchar(50) NOT NULL DEFAULT '',
  `modules` varchar(255) NOT NULL DEFAULT '',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ตัวอย่าง (การติดตั้งใหม่เท่านั้น) — ปิดไว้ เพราะยังไม่มีรหัสแชทจริง เติมรหัสแชทของกลุ่มแล้วเปิดใช้งาน
INSERT INTO `{prefix}_telegram_group` (`id`, `name`, `department`, `chat_id`, `modules`, `is_active`) VALUES
(1, 'กลุ่มผู้บริหาร (ทุกแผนก)', NULL, '', ',booking,car,repair,edocument,', 0),
(2, '', '2', '', ',booking,car,', 0);

