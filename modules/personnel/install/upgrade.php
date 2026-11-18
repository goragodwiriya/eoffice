<?php
/**
 * modules/personnel/install/upgrade.php — พาฐานเดิมมาถึงสคีมาของโมดูล personnel
 *
 * install/upgrade_core.php เรียกไฟล์นี้ให้เอง ตัวแปรที่ใช้ได้คือชุดเดียวกับที่
 * upgrade_core ใช้ : $db, $db_config, $prefix, $content, $config
 *
 * นิยามตารางอยู่ที่ modules/personnel/install/database.sql ที่เดียว
 * กฎเดียวกับ upgrade_core : ทุกเงื่อนไขถามว่า "ต้องแก้ไหม" ไม่ใช่ "ตอนนี้เป็นอะไร"
 */
if (!defined('ROOT_PATH')) {
    exit;
}

// =========================================================
// personnel
// =========================================================
$table_personnel = $prefix.'_personnel';
if (ensureTable($db, $prefix, $table_personnel)) {
    $content[] = '<li class="correct">personnel: สร้างตารางใหม่</li>';
    // E-Office เดิมไม่มีตาราง personnel — บุคลากรคือสมาชิกที่มีสถานะตาม personnel_status
    // ตำแหน่ง/แผนกอยู่ใน user_meta และช่องข้อมูลเพิ่มเติม (PERSONNEL_DETAILS) ก็อยู่ใน user_meta
    // สร้างแถวบุคลากรให้ครบทุกคนจากข้อมูลนั้น (แถว user_meta คงไว้ แผนกยังใช้กับระบบจอง/สารบรรณ)
    if (!empty($config['personnel_status']) && is_array($config['personnel_status'])) {
        $_statuses = array_map('intval', $config['personnel_status']);
        $_seeded = 0;
        $_metas = [];
        foreach ($db->customQuery(
            "SELECT `member_id`, `name`, `value` FROM `{$prefix}_user_meta` ORDER BY `member_id`, `name`, `value`", true
        ) as $_row) {
            if (!isset($_metas[$_row['member_id']][$_row['name']])) {
                $_metas[$_row['member_id']][$_row['name']] = $_row['value'];
            }
        }
        foreach ($db->customQuery(
            "SELECT `id`".($db->fieldExists($prefix.'_user', 'code') ? ', `code`' : ", '' AS `code`")
            ." FROM `{$prefix}_user` WHERE `status` IN (".implode(',', $_statuses).") ORDER BY `id`", true
        ) as $_row) {
            $_meta = isset($_metas[$_row['id']]) ? $_metas[$_row['id']] : [];
            $_custom = array_diff_key($_meta, ['department' => 1, 'position' => 1]);
            // รหัสพนักงาน — E-Office เดิมมีคอลัมน์ user.code (เลขประชาชนไม่ใช่รหัสพนักงาน ไม่คัดลอก)
            $_code = mb_substr(trim((string) $_row['code']), 0, 20);
            $db->insert($table_personnel, [
                'id' => (int) $_row['id'],
                'position' => isset($_meta['position']) ? (int) $_meta['position'] : 0,
                'department' => isset($_meta['department']) ? (int) $_meta['department'] : 0,
                'order' => 0,
                'custom' => empty($_custom) ? null : json_encode($_custom, JSON_UNESCAPED_UNICODE),
                'employee_code' => $_code === '' ? null : $_code
            ]);
            ++$_seeded;
        }
        if ($_seeded > 0) {
            $content[] = '<li class="correct">personnel: สร้างข้อมูลบุคลากร '.$_seeded.' คนจากสมาชิกของระบบเดิม</li>';
        }
        if (!isset($config['teacher_status'])) {
            // สถานะของบุคลากรที่เพิ่มใหม่ = สถานะบุคลากรตัวแรกที่ไม่ใช่ผู้ดูแลระบบ
            $_staff = array_values(array_diff($_statuses, [1]));
            $config['teacher_status'] = empty($_staff) ? 2 : (int) $_staff[0];
        }
    }
} else {
    if (convertToInnoDB($db, $table_personnel)) {
        $content[] = '<li class="correct">personnel: แปลงเป็น InnoDB</li>';
    }
    // แปลง charset ก่อนปรับชนิดคอลัมน์ เพราะ CONVERT TO utf8mb4 ดัน TEXT ขึ้นเป็น MEDIUMTEXT
    if (convertToUtf8mb4($db, $table_personnel)) {
        $content[] = '<li class="correct">personnel: แปลงเป็น utf8mb4</li>';
    }
    if (ensureColumn($db, $table_personnel, 'custom', 'text', true)) {
        $content[] = '<li class="correct">personnel: ปรับคอลัมน์ custom</li>';
    }
    if (!$db->indexExists($table_personnel, 'PRIMARY')) {
        $db->query("ALTER TABLE `$table_personnel` ADD PRIMARY KEY (`id`)");
        $content[] = '<li class="correct">personnel: เพิ่ม PRIMARY KEY</li>';
    }
    // เลขประชาชน → รหัสพนักงาน (2026-11-09)
    // เลขประชาชนเป็นข้อมูลของสมาชิก (user.id_card) ไม่ใช่ของบุคลากร — ย้ายค่าที่ยังไม่มีในตาราง
    // สมาชิกไปไว้ที่นั่น (ไม่ให้ข้อมูลหาย) แล้วเลิกใช้คอลัมน์นี้ในตารางบุคลากร
    if (ensureColumn($db, $table_personnel, 'employee_code', 'varchar(20)', true, null, '', 'custom')) {
        $content[] = '<li class="correct">personnel: เพิ่มคอลัมน์ employee_code (รหัสพนักงาน)</li>';
    }
    if ($db->fieldExists($table_personnel, 'id_card')) {
        $_t_user = $prefix.'_user';
        $db->query(
            "UPDATE `$_t_user` U JOIN `$table_personnel` P ON P.`id` = U.`id`
                SET U.`id_card` = P.`id_card`
              WHERE (U.`id_card` IS NULL OR U.`id_card` = '') AND P.`id_card` IS NOT NULL AND P.`id_card` != ''
                AND NOT EXISTS (SELECT 1 FROM (SELECT `id`, `id_card` FROM `$_t_user`) X WHERE X.`id_card` = P.`id_card` AND X.`id` != U.`id`)"
        );
        $_left = $db->customQuery(
            "SELECT COUNT(*) AS `c` FROM `$table_personnel` P LEFT JOIN `$_t_user` U ON U.`id` = P.`id`
              WHERE P.`id_card` IS NOT NULL AND P.`id_card` != '' AND (U.`id_card` IS NULL OR U.`id_card` != P.`id_card`)"
        );
        if (empty($_left) || (int) $_left[0]->c === 0) {
            if ($db->indexExists($table_personnel, 'id_card')) {
                $db->query("ALTER TABLE `$table_personnel` DROP INDEX `id_card`");
            }
            $db->query("ALTER TABLE `$table_personnel` DROP COLUMN `id_card`");
            $content[] = '<li class="correct">personnel: เลิกใช้เลขประชาชนในตารางบุคลากร (ค่าอยู่ที่ข้อมูลสมาชิก)</li>';
        } else {
            $content[] = '<li class="warning">personnel: เลขประชาชน '.(int) $_left[0]->c
                .' รายการย้ายไปข้อมูลสมาชิกไม่ได้ (ซ้ำกับสมาชิกคนอื่น) จึงคงคอลัมน์ id_card ไว้ให้ตรวจสอบ</li>';
        }
    }
    // ชั้น/ห้อง (ครูประจำชั้นของระบบโรงเรียน) ไม่ใช้ในโปรเจ็คนี้ — ลบได้เมื่อไม่มีค่า
    foreach (['class', 'room'] as $_col) {
        if ($db->fieldExists($table_personnel, $_col)) {
            $_used = $db->customQuery("SELECT COUNT(*) AS `c` FROM `$table_personnel` WHERE `$_col` IS NOT NULL AND `$_col` != 0");
            if (empty($_used) || (int) $_used[0]->c === 0) {
                $db->query("ALTER TABLE `$table_personnel` DROP COLUMN `$_col`");
                $content[] = '<li class="correct">personnel: ลบคอลัมน์ '.$_col.' ที่ไม่ใช้</li>';
            } else {
                $content[] = '<li class="warning">personnel: คอลัมน์ '.$_col.' มีข้อมูล '.(int) $_used[0]->c.' รายการ จึงคงไว้</li>';
            }
        }
    }
    foreach (ensureIndexes($db, $table_personnel, [
        'employee_code' => '`employee_code`',
        'position' => '`position`,`order`'
    ]) as $_index) {
        $content[] = '<li class="correct">personnel: เพิ่มดัชนี '.$_index.'</li>';
    }
    // custom : PHP serialize -> JSON
    // ระบบเดิมเก็บด้วย serialize() การอ่านกลับด้วย unserialize() กับข้อมูลที่ผู้ใช้กรอก
    // เปิดช่อง object injection ระบบใหม่อ่านเป็น JSON อย่างเดียว จึงต้องแปลงของเดิมให้หมด
    $_count = 0;
    foreach ($db->customQuery("SELECT `id`, `custom` FROM `$table_personnel` WHERE `custom` LIKE 'a:%'", true) as $_row) {
        $_custom = @unserialize($_row['custom'], ['allowed_classes' => false]);
        $_custom = is_array($_custom) && !empty($_custom) ? json_encode($_custom, JSON_UNESCAPED_UNICODE) : null;
        $db->update($table_personnel, (int) $_row['id'], ['custom' => $_custom]);
        ++$_count;
    }
    if ($_count > 0) {
        $content[] = '<li class="correct">personnel: แปลงข้อมูลเพิ่มเติม '.$_count.' รายการเป็น JSON</li>';
    }
    $content[] = '<li class="correct">personnel อัปเกรดสำเร็จ</li>';
}

// ค่ากำหนดของโมดูล — ไม่ทับค่าที่ไซต์ตั้งไว้แล้ว
if (!isset($config['personnel_w'])) {
    $config['personnel_w'] = 500;
}
if (!isset($config['personnel_h'])) {
    $config['personnel_h'] = 500;
}
if (!isset($config['teacher_status'])) {
    $config['teacher_status'] = 2;
}
