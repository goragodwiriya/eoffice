<?php
/**
 * modules/car/install/upgrade.php — พาฐานเดิมมาถึงสคีมาของโมดูล car
 *
 * install/upgrade_core.php เรียกไฟล์นี้ให้เอง ตัวแปรที่ใช้ได้คือชุดเดียวกับที่
 * upgrade_core ใช้ : $db, $db_config, $prefix, $content, $config
 *
 * ⚠️ ก่อนมีไฟล์นี้ **ไม่มีอะไรแตะตารางของโมดูลนี้เลย** install/upgrade2.php
 * ของโปรเจ็คเป็นแม่แบบเปล่า ๆ ที่เรียกแต่ upgrade_core ส่วนตารางทั้ง 4 ตาราง
 * ถูกประกาศไว้ใน install/database.sql เท่านั้น ไซต์ที่ติดตั้งใหม่จึงได้สคีมาถูก
 * แต่ไซต์ที่กดปรับรุ่นได้สคีมาเก่าค้างไว้ทั้งชุด — อาการเดียวกับที่โปรเจ็คพี่น้อง
 * booking เคยพังด้วย "Unknown column 'R.is_active'"
 *
 * สคีมารุ่นเดิมอยู่ที่ install/legacy/pre-module.sql (คัดจากไซต์จริง)
 *
 * กฎเดียวกับ upgrade_core : ทุกเงื่อนไขถามว่า "ต้องแก้ไหม" ไม่ใช่ "ตอนนี้เป็นอะไร"
 */
if (!defined('ROOT_PATH')) {
    exit;
}

$_t_vehicles = $prefix.'_vehicles';
$_t_vehicles_meta = $prefix.'_vehicles_meta';
$_t_reservation = $prefix.'_car_reservation';
$_t_reservation_data = $prefix.'_car_reservation_data';

foreach ([$_t_vehicles, $_t_vehicles_meta, $_t_reservation, $_t_reservation_data] as $_t) {
    // นิยามตารางอยู่ที่ modules/car/install/database.sql ที่เดียว
    if (ensureTable($db, $prefix, $_t)) {
        $content[] = '<li class="correct">car: สร้างตาราง '.$_t.'</li>';
    }
    // ต้องแปลงก่อนปรับคอลัมน์เสมอ — CONVERT TO CHARACTER SET เลื่อนชนิด TEXT
    // เป็น MEDIUMTEXT ถ้าแปลงทีหลังชนิดจะไม่ตรงกับที่ติดตั้งใหม่ได้
    if (convertToInnoDB($db, $_t)) {
        $content[] = '<li class="correct">'.$_t.': แปลงเป็น InnoDB</li>';
    }
    if (convertToUtf8mb4($db, $_t)) {
        $content[] = '<li class="correct">'.$_t.': แปลงเป็น utf8mb4</li>';
    }
}

// =============================================================================
// vehicles — published → is_active
//
// ใช้แพทเทิร์นเดียวกับที่ upgrade_core ทำกับตาราง category : เพิ่มคอลัมน์ใหม่
// คัดลอกค่าเดิมมา แล้วค่อยลบคอลัมน์เก่า (ค่าไม่หาย เพราะย้ายไปอยู่คอลัมน์ใหม่แล้ว)
// =============================================================================
if (!$db->fieldExists($_t_vehicles, 'is_active')) {
    $db->query("ALTER TABLE `$_t_vehicles` ADD `is_active` TINYINT(1) NULL");
    if ($db->fieldExists($_t_vehicles, 'published')) {
        $db->query("UPDATE `$_t_vehicles` SET `is_active` = `published`");
    } else {
        $db->query("UPDATE `$_t_vehicles` SET `is_active` = 1");
    }
    $content[] = '<li class="correct">vehicles: เพิ่ม is_active</li>';
}
if ($db->fieldExists($_t_vehicles, 'published')) {
    $db->query("ALTER TABLE `$_t_vehicles` DROP COLUMN `published`");
    $content[] = '<li class="correct">vehicles: ลบ published (ค่าย้ายไป is_active แล้ว)</li>';
}
foreach ([
    'number' => ['varchar(20)', false, '', 'id'],
    'color' => ['varchar(20)', false, '', 'number'],
    'detail' => ['text', false, null, 'color'],
    'seats' => ['int(11)', false, null, 'detail'],
    'is_active' => ['tinyint(1)', false, 1, 'seats']
] as $_col => $_def) {
    if (ensureColumn($db, $_t_vehicles, $_col, $_def[0], $_def[1], $_def[2], '', $_def[3])) {
        $content[] = '<li class="correct">vehicles: ปรับคอลัมน์ '.$_col.'</li>';
    }
}

// =============================================================================
// car_reservation — create_date → created_at และคอลัมน์ที่โค้ดปัจจุบันอ้าง
//
// ⚠️ โค้ดของโมดูลอ้าง approve 28 จุด และ is_active 17 จุด ไซต์ที่อัปเกรดโดยไม่มี
// คอลัมน์เหล่านี้จะพังทันทีที่เปิดหน้าจอง
// =============================================================================
if (!$db->fieldExists($_t_reservation, 'created_at') && $db->fieldExists($_t_reservation, 'create_date')) {
    // CHANGE เก็บข้อมูลเดิมไว้ครบ เป็นการเปลี่ยนชื่อ ไม่ใช่สร้างใหม่แล้วทิ้งของเก่า
    $db->query("ALTER TABLE `$_t_reservation` CHANGE `create_date` `created_at` DATETIME NULL DEFAULT NULL");
    $content[] = '<li class="correct">car_reservation: เปลี่ยนชื่อ create_date → created_at</li>';
}
// approver → approve (เปลี่ยนชื่อ ไม่ใช่สร้างใหม่ ค่าเดิมจึงไม่หาย)
if (!$db->fieldExists($_t_reservation, 'approve') && $db->fieldExists($_t_reservation, 'approver')) {
    $db->query("ALTER TABLE `$_t_reservation` CHANGE `approver` `approve` TINYINT(1) NOT NULL");
    $content[] = '<li class="correct">car_reservation: เปลี่ยนชื่อ approver → approve</li>';
}
foreach ([
    'department' => ['varchar(10)', true, null, 'member_id'],
    'created_at' => ['datetime', true, null, 'department'],
    'detail' => ['text', false, null, 'created_at'],
    'chauffeur' => ['int(11)', false, null, 'detail'],
    'comment' => ['text', true, null, 'chauffeur'],
    'travelers' => ['int(11)', false, null, 'comment'],
    'status' => ['tinyint(1)', false, null, 'end'],
    'reason' => ['text', true, null, 'status'],
    // ⚠️ ส่ง null เป็นค่าปริยาย ไม่ใช่ 0 — สคีมาปัจจุบันประกาศ NOT NULL เฉย ๆ
    // ไม่ได้กำหนด DEFAULT ถ้าใส่ 0 ลงไปสคีมาจะไม่ตรงกับฐานที่ติดตั้งใหม่
    'approve' => ['tinyint(1)', false, null, 'reason'],
    'closed' => ['tinyint(1)', false, null, 'approve']
] as $_col => $_def) {
    if (ensureColumn($db, $_t_reservation, $_col, $_def[0], $_def[1], $_def[2], '', $_def[3])) {
        $content[] = '<li class="correct">car_reservation: ปรับคอลัมน์ '.$_col.'</li>';
    }
}
foreach ([
    'name' => ['varchar(20)', false, null, 'reservation_id'],
    'value' => ['varchar(150)', false, null, 'name']
] as $_col => $_def) {
    if (ensureColumn($db, $_t_reservation_data, $_col, $_def[0], $_def[1], $_def[2], '', $_def[3])) {
        $content[] = '<li class="correct">car_reservation_data: ปรับคอลัมน์ '.$_col.'</li>';
    }
}
foreach ([
    'name' => ['varchar(20)', false, null, 'vehicle_id'],
    'value' => ['varchar(150)', false, null, 'name']
] as $_col => $_def) {
    if (ensureColumn($db, $_t_vehicles_meta, $_col, $_def[0], $_def[1], $_def[2], '', $_def[3])) {
        $content[] = '<li class="correct">vehicles_meta: ปรับคอลัมน์ '.$_col.'</li>';
    }
}

// =============================================================================
// ดัชนีที่ query ใช้จริง
//
// ⚠️ ต้องทำ "หลัง" ปรับคอลัมน์เสร็จ เพราะ idx_vehicle_availability อ้าง approve
// ซึ่งเมื่อกี้ยังชื่อ approver อยู่
//
// ⚠️ idx_reservation เป็น UNIQUE — ถ้าไซต์ไหนมี (reservation_id, name) ซ้ำ
// คำสั่งจะล้ม ตัวปรับรุ่นจึงต้องตรวจก่อนแล้วบอกผู้ดูแลให้แก้ ไม่ใช่ล้มกลางคัน
// =============================================================================
if (ensureIndexes($db, $_t_vehicles_meta, ['idx_vehicle_meta' => '`vehicle_id`, `name`'])) {
    $content[] = '<li class="correct">vehicles_meta: ปรับดัชนี</li>';
}
if (ensureIndexes($db, $_t_reservation, [
    'idx_vehicle_availability' => '`vehicle_id`, `status`, `approve`, `begin`, `end`',
    'member_id' => '`member_id`, `created_at`'
])) {
    $content[] = '<li class="correct">car_reservation: ปรับดัชนี</li>';
}
$_dup = $db->customQuery(
    "SELECT COUNT(*) AS `c` FROM (SELECT 1 FROM `$_t_reservation_data`
     GROUP BY `reservation_id`, `name` HAVING COUNT(*) > 1) `x`"
);
if (!empty($_dup) && (int) $_dup[0]->c > 0) {
    $content[] = '<li class="warning">car_reservation_data: มีคู่ (reservation_id, name) ซ้ำอยู่ '
        .number_format((int) $_dup[0]->c).' คู่ จึงยังสร้างดัชนี idx_reservation แบบ UNIQUE ไม่ได้ '
        .'กรุณาลบแถวที่ซ้ำออกให้เหลือชุดเดียว แล้วกดปรับรุ่นอีกครั้ง</li>';
} elseif (ensureIndexes($db, $_t_reservation_data, ['idx_reservation' => '`reservation_id`, `name`'])) {
    // ensureIndexes สร้างเป็น INDEX ธรรมดา ต้องยกระดับเป็น UNIQUE ให้ตรงกับติดตั้งใหม่
    $db->query("ALTER TABLE `$_t_reservation_data` DROP INDEX `idx_reservation`");
    $db->query("ALTER TABLE `$_t_reservation_data` ADD UNIQUE KEY `idx_reservation` (`reservation_id`, `name`) USING BTREE");
    $content[] = '<li class="correct">car_reservation_data: ปรับดัชนี</li>';
}

// =============================================================================
// ดัชนีรุ่นเก่าที่ถูกแทนด้วยดัชนีรวมข้างบนแล้ว
//
// เก็บไว้ก็ไม่มีอะไรใช้ แต่ทำให้ทุกการเขียนต้องอัปเดตดัชนีเพิ่มโดยเปล่าประโยชน์
// และทำให้ไซต์ที่ปรับรุ่นมีดัชนีไม่เท่าไซต์ที่ติดตั้งใหม่ไปตลอด
// การลบดัชนีไม่ทำข้อมูลหาย จึงต่างจากกฎ "ห้ามลบของเดิม" ซึ่งคุ้มครองข้อมูล
// (room_id เป็นชื่อที่คัดลอกติดมาจากโมดูล booking ทั้งที่ตารางนี้เป็นของรถ)
// =============================================================================
if (dropIndexes($db, $_t_vehicles_meta, ['room_id', 'vehicle_id'])) {
    $content[] = '<li class="correct">vehicles_meta: ลบดัชนีรุ่นเก่าที่ถูกแทนแล้ว</li>';
}
if (dropIndexes($db, $_t_reservation_data, ['reservation_id'])) {
    $content[] = '<li class="correct">car_reservation_data: ลบดัชนีรุ่นเก่าที่ถูกแทนแล้ว</li>';
}
// E-Office 6.x มีดัชนี status เดี่ยว — idx_vehicle_availability ครอบคลุมแล้ว
if (dropIndexes($db, $_t_reservation, ['status'])) {
    $content[] = '<li class="correct">car_reservation: ลบดัชนีรุ่นเก่าที่ถูกแทนแล้ว</li>';
}

$content[] = '<li class="correct">car อัปเกรดสำเร็จ</li>';

// =============================================================================
// E-Office เดิม
// 1. พนักงานขับรถ = สมาชิกที่มีสถานะ chauffeur_status · โมดูลนี้ใช้สิทธิ์ can_drive_car
//    (ทำเฉพาะตอนที่ยังไม่มีใครมีสิทธิ์นี้ จึงไม่ทับการตั้งค่าที่ผู้ดูแลทำเองภายหลัง)
// 2. หมวดอุปกรณ์เสริม type car_accessories → car_accessory (ใบจองยังเก็บชื่อ car_accessories เหมือนเดิม)
// =============================================================================
$_t_user = $prefix.'_user';
if (isset($config['chauffeur_status']) && (int) $config['chauffeur_status'] > 1) {
    $_drivers = $db->customQuery("SELECT COUNT(*) AS `c` FROM `$_t_user` WHERE `permission` LIKE '%,can_drive_car,%'");
    if (!empty($_drivers) && (int) $_drivers[0]->c === 0) {
        $_status = (int) $config['chauffeur_status'];
        $db->query("UPDATE `$_t_user` SET `permission` = CONCAT(IF(`permission` IS NULL OR `permission` = '', ',', `permission`), 'can_drive_car,') WHERE `status` = $_status");
        $content[] = '<li class="correct">car: ให้สิทธิ์ขับรถ (can_drive_car) แก่สมาชิกสถานะพนักงานขับรถของระบบเดิม</li>';
    }
}
$_t_category = $prefix.'_category';
$_old = $db->customQuery("SELECT COUNT(*) AS `c` FROM `$_t_category` WHERE `type` = 'car_accessories'");
$_new = $db->customQuery("SELECT COUNT(*) AS `c` FROM `$_t_category` WHERE `type` = 'car_accessory'");
if (!empty($_old) && (int) $_old[0]->c > 0 && !empty($_new) && (int) $_new[0]->c === 0) {
    $db->query("UPDATE `$_t_category` SET `type` = 'car_accessory' WHERE `type` = 'car_accessories'");
    $content[] = '<li class="correct">car: หมวดอุปกรณ์เสริม car_accessories → car_accessory</li>';
}
