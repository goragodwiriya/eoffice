<?php
/**
 * modules/repair/install/upgrade.php — พาฐานเดิมมาถึงสคีมาของโมดูล repair
 *
 * install/upgrade_core.php เรียกไฟล์นี้ให้เองสำหรับทุกโมดูลที่มี ตัวแปรที่ใช้ได้
 * คือชุดเดียวกับที่ upgrade_core ใช้ : $db, $db_config, $prefix, $content, $config
 *
 * ⚠️ ตารางของโมดูลต้องปรับรุ่นที่นี่ ไม่ใช่ใน install/upgrade2.php ของโปรเจ็ค
 * เพื่อให้ "นิยามตาราง + การปรับรุ่น" ของโมดูลอยู่ด้วยกันที่เดียว — โมดูลถูก
 * คัดลอกไปโปรเจ็คใหม่แล้วใช้ได้ทันทีโดยไม่ต้องตามไปแก้ตัวปรับรุ่นของโปรเจ็คนั้น
 *
 * กฎเดียวกับ upgrade_core : ทุกเงื่อนไขถามว่า "ต้องแก้ไหม" ไม่ใช่ "ตอนนี้เป็นอะไร"
 * และห้าม DROP / RENAME ข้อมูลธุรกิจ
 */
if (!defined('ROOT_PATH')) {
    exit;
}

// =========================================================
// repair
// =========================================================
$table_repair = $prefix.'_repair';
// ⚠️ นิยามตารางอยู่ที่ install/database.sql ของโมดูลที่เดียว
// ensureTable อ่านจากไฟล์นั้น จึงไม่มีนิยามชุดที่สองให้ค่อย ๆ ต่างกัน
if (ensureTable($db, $prefix, $table_repair)) {
    $content[] = '<li class="correct">repair: สร้างตารางใหม่</li>';
} else {
    if ($db->fieldExists($table_repair, 'create_date')) {
        $db->query("ALTER TABLE `$table_repair` CHANGE `create_date` `created_at` DATETIME NOT NULL");
        $content[] = '<li class="correct">repair: เปลี่ยนชื่อ create_date → created_at</li>';
    }
    // E-Office เดิมผูกงานซ่อมกับครุภัณฑ์ด้วย inventory_id (ไม่มี product_no)
    // โมดูล inventory ปรับรุ่นก่อนหน้านี้แล้ว (ลำดับตัวอักษร) หน่วยย่อยจึงอยู่ใน inventory_items
    if ($db->fieldExists($table_repair, 'inventory_id')) {
        if (ensureColumn($db, $table_repair, 'product_no', 'varchar(150)', false, null, '', 'customer_id')) {
            $content[] = '<li class="correct">repair: เพิ่มคอลัมน์ product_no</li>';
        }
        if ($db->tableExists($prefix.'_inventory_items')) {
            $db->query(
                "UPDATE `$table_repair` R
                   JOIN (SELECT `inventory_id`, MIN(`id`) AS `item_id` FROM `{$prefix}_inventory_items` GROUP BY `inventory_id`) F
                     ON F.`inventory_id` = R.`inventory_id`
                   JOIN `{$prefix}_inventory_items` I ON I.`id` = F.`item_id`
                    SET R.`product_no` = I.`product_no`
                  WHERE R.`product_no` = ''"
            );
        }
        $_unmapped = $db->customQuery("SELECT COUNT(*) AS `c` FROM `$table_repair` WHERE `product_no` = '' AND `inventory_id` > 0");
        if (empty($_unmapped) || (int) $_unmapped[0]->c === 0) {
            $db->query("ALTER TABLE `$table_repair` DROP COLUMN `inventory_id`");
            $content[] = '<li class="correct">repair: inventory_id → product_no</li>';
        } else {
            $content[] = '<li class="warning">repair: มีงานซ่อม '.(int) $_unmapped[0]->c
                .' รายการที่หาเลขครุภัณฑ์ไม่พบ จึงคงคอลัมน์ inventory_id ไว้ให้ตรวจสอบ</li>';
        }
    }
    foreach ([
        'appointment_date' => ['date', true, null, 'created_at'],
        'repair_no' => ['varchar(50)', true, null, 'appointment_date'],
        'informer' => ['varchar(150)', true, null, 'repair_no'],
        'appraiser' => ['float', false, 0, 'informer']
    ] as $_col => $_def) {
        if (!$db->fieldExists($table_repair, $_col)
            && ensureColumn($db, $table_repair, $_col, $_def[0], $_def[1], $_def[2], '', $_def[3])) {
            $content[] = '<li class="correct">repair: เพิ่มคอลัมน์ '.$_col.'</li>';
        }
    }
    if ($db->isColumnType($table_repair, 'appraiser', 'float')) {
        $db->query("UPDATE `$table_repair` SET `appraiser` = 0 WHERE `appraiser` IS NULL");
        $db->query("ALTER TABLE `$table_repair` CHANGE `appraiser` `appraiser` FLOAT NOT NULL DEFAULT 0");
    }
    if (!$db->indexExists($table_repair, 'PRIMARY')) {
        $db->query("ALTER TABLE `$table_repair` ADD PRIMARY KEY (`id`)");
        $content[] = '<li class="correct">repair: เพิ่ม PRIMARY KEY</li>';
    }
    if (!isAutoIncrement($db, $table_repair, 'id')) {
        $db->query("ALTER TABLE `$table_repair` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT");
        $content[] = '<li class="correct">repair: กำหนด id เป็น AUTO_INCREMENT</li>';
    }
    if (!$db->indexExists($table_repair, 'job_id')) {
        try {
            $db->query("ALTER TABLE `$table_repair` ADD UNIQUE KEY `job_id` (`job_id`)");
            $content[] = '<li class="correct">repair: เพิ่ม unique index job_id</li>';
        } catch (\PDOException $exc) {
            $db->query("ALTER TABLE `$table_repair` ADD INDEX `job_id` (`job_id`)");
            $content[] = '<li class="incorrect">repair: มีเลขที่ใบแจ้งซ่อมซ้ำ ใช้ index ปกติแทน unique ('.$exc->getMessage().')</li>';
        }
    }
    if (!$db->indexExists($table_repair, 'product_no')) {
        $db->query("ALTER TABLE `$table_repair` ADD INDEX `product_no` (`product_no`)");
        $content[] = '<li class="correct">repair: เพิ่ม index product_no</li>';
    }
    if (!$db->indexExists($table_repair, 'idx_customer')) {
        $db->query("ALTER TABLE `$table_repair` ADD INDEX `idx_customer` (`customer_id`, `created_at`)");
        $content[] = '<li class="correct">repair: เพิ่ม index idx_customer</li>';
    }
    if (convertToUtf8mb4($db, $table_repair)) {
        $content[] = '<li class="correct">repair: แปลงเป็น utf8mb4</li>';
    }
    $content[] = '<li class="correct">repair อัปเกรดสำเร็จ</li>';
}

// =========================================================
// repair_status
// =========================================================
$table_repair_status = $prefix.'_repair_status';
// ⚠️ นิยามตารางอยู่ที่ install/database.sql ของโมดูลที่เดียว
// ensureTable อ่านจากไฟล์นั้น จึงไม่มีนิยามชุดที่สองให้ค่อย ๆ ต่างกัน
if (ensureTable($db, $prefix, $table_repair_status)) {
    $content[] = '<li class="correct">repair_status: สร้างตารางใหม่</li>';
} else {
    if ($db->fieldExists($table_repair_status, 'create_date')) {
        $db->query("ALTER TABLE `$table_repair_status` CHANGE `create_date` `created_at` DATETIME NOT NULL");
        $content[] = '<li class="correct">repair_status: เปลี่ยนชื่อ create_date → created_at</li>';
    }
    if ($db->isColumnType($table_repair_status, 'cost', 'float')) {
        $db->query("UPDATE `$table_repair_status` SET `cost` = 0 WHERE `cost` IS NULL");
        $db->query("ALTER TABLE `$table_repair_status` CHANGE `cost` `cost` FLOAT NOT NULL DEFAULT 0");
    }
    if ($db->isColumnType($table_repair_status, 'operator_id', 'int')) {
        $db->query("UPDATE `$table_repair_status` SET `operator_id` = 0 WHERE `operator_id` IS NULL");
        $db->query("ALTER TABLE `$table_repair_status` CHANGE `operator_id` `operator_id` INT(11) NOT NULL DEFAULT 0");
    }
    if (!$db->indexExists($table_repair_status, 'PRIMARY')) {
        $db->query("ALTER TABLE `$table_repair_status` ADD PRIMARY KEY (`id`)");
        $content[] = '<li class="correct">repair_status: เพิ่ม PRIMARY KEY</li>';
    }
    if (!isAutoIncrement($db, $table_repair_status, 'id')) {
        $db->query("ALTER TABLE `$table_repair_status` MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT");
        $content[] = '<li class="correct">repair_status: กำหนด id เป็น AUTO_INCREMENT</li>';
    }
    if (!$db->indexExists($table_repair_status, 'idx_repair')) {
        $db->query("ALTER TABLE `$table_repair_status` ADD INDEX `idx_repair` (`repair_id`, `id`)");
        $content[] = '<li class="correct">repair_status: เพิ่ม index idx_repair</li>';
    }
    if (!$db->indexExists($table_repair_status, 'idx_status')) {
        $db->query("ALTER TABLE `$table_repair_status` ADD INDEX `idx_status` (`status`, `created_at`)");
        $content[] = '<li class="correct">repair_status: เพิ่ม index idx_status</li>';
    }
    if (!$db->indexExists($table_repair_status, 'operator_id')) {
        $db->query("ALTER TABLE `$table_repair_status` ADD INDEX `operator_id` (`operator_id`)");
        $content[] = '<li class="correct">repair_status: เพิ่ม index operator_id</li>';
    }
    if ($db->indexExists($table_repair_status, 'repair_id')) {
        $db->query("ALTER TABLE `$table_repair_status` DROP INDEX `repair_id`");
        $content[] = '<li class="correct">repair_status: ลบ index ซ้ำซ้อน repair_id</li>';
    }
    if (convertToUtf8mb4($db, $table_repair_status)) {
        $content[] = '<li class="correct">repair_status: แปลงเป็น utf8mb4</li>';
    }
    $content[] = '<li class="correct">repair_status อัปเกรดสำเร็จ</li>';
}

// =========================================================
// category: สถานะการซ่อม (repairstatus)
// =========================================================
$_repairstatus = $db->customQuery("SELECT COUNT(*) AS `count` FROM `$table_category` WHERE `type` = 'repairstatus'");
if (empty($_repairstatus) || (int) $_repairstatus[0]->count === 0) {
    $_statuses = [
        1 => ['แจ้งซ่อม', '#660000'],
        2 => ['กำลังดำเนินการ', '#120eeb'],
        3 => ['รออะไหล่', '#d940ff'],
        4 => ['ซ่อมสำเร็จ', '#06d628'],
        5 => ['ซ่อมไม่สำเร็จ', '#FF0000'],
        6 => ['ยกเลิกการซ่อม', '#FF6F00'],
        7 => ['ส่งมอบเรียบร้อย', '#000000']
    ];
    foreach ($_statuses as $_id => $_item) {
        $db->insert($table_category, [
            'type' => 'repairstatus',
            'category_id' => $_id,
            'language' => '',
            'topic' => $_item[0],
            'color' => $_item[1],
            'is_active' => 1
        ]);
    }
    $content[] = '<li class="correct">category: เพิ่มสถานะการซ่อมเริ่มต้น</li>';
}

// =========================================================
// เลขที่ใบแจ้งซ่อม
// E-Office เดิมไม่บันทึกรูปแบบลง config (ใช้ค่าเริ่มต้น JOB%04d) และเก็บ running number
// ด้วยชื่อค่ากำหนด (type = 'repair_job_no') — แกนรุ่นนี้ใช้ตัวรูปแบบเป็น type ย้ายมาเพื่อให้นับต่อจากเลขเดิม
// =========================================================
if (!isset($config['repair_first_status'])) {
    $config['repair_first_status'] = 1;
}
if (!isset($config['repair_job_no']) || $config['repair_job_no'] === '') {
    $config['repair_job_no'] = 'JOB%04d';
}
if (!isset($config['repair_prefix'])) {
    $config['repair_prefix'] = '';
}
$_table_number = $prefix.'_number';
if ($db->tableExists($_table_number)) {
    $_format = (string) $config['repair_job_no'];
    foreach ($db->customQuery("SELECT `prefix`, `auto_increment` FROM `$_table_number` WHERE `type` = 'repair_job_no'", true) as $_row) {
        $_exists = $db->customQuery("SELECT `auto_increment` FROM `$_table_number` WHERE `type` = :t AND `prefix` = :p", true, [':t' => $_format, ':p' => $_row['prefix']]);
        if (empty($_exists)) {
            $db->customQuery("UPDATE `$_table_number` SET `type` = :t WHERE `type` = 'repair_job_no' AND `prefix` = :p", false, [':t' => $_format, ':p' => $_row['prefix']]);
        } else {
            $db->customQuery("UPDATE `$_table_number` SET `auto_increment` = GREATEST(`auto_increment`, :n) WHERE `type` = :t AND `prefix` = :p", false, [':n' => (int) $_row['auto_increment'], ':t' => $_format, ':p' => $_row['prefix']]);
            $db->customQuery("DELETE FROM `$_table_number` WHERE `type` = 'repair_job_no' AND `prefix` = :p", false, [':p' => $_row['prefix']]);
        }
        $content[] = '<li class="correct">number: ย้ายเลขที่ใบแจ้งซ่อม</li>';
    }
}
