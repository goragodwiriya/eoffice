<?php
/**
 * modules/telegramgroup/install/upgrade.php — กลุ่ม Telegram (แทนกลุ่ม LINE Notify ของ E-Office เดิม)
 *
 * install/upgrade_core.php เรียกไฟล์นี้ให้เอง ตัวแปรที่ใช้ได้ : $db, $db_config, $prefix, $content, $config
 *
 * E-Office เดิมมีตาราง {prefix}_line (id, name, department, token) เก็บกลุ่ม LINE Notify
 * บริการ LINE Notify ปิดไปแล้ว token จึงใช้ต่อไม่ได้ — ย้ายชื่อกลุ่มและแผนกมาเป็นกลุ่ม Telegram
 * (chat_id ว่าง ปิดไว้ก่อน ให้ผู้ดูแลเติมรหัสแชทของกลุ่ม) ความตั้งค่า "ส่งเข้ากลุ่มไหน"
 * ของแต่ละโมดูลเดิม (booking_line_id, car_line_id, repair_line_id, edocument_line_id) ย้ายมาเป็น modules ของกลุ่ม
 */
if (!defined('ROOT_PATH')) {
    exit;
}

$_tg_table = $prefix.'_telegram_group';
if (ensureTable($db, $prefix, $_tg_table)) {
    $content[] = '<li class="correct">telegram_group: สร้างตารางใหม่</li>';
}
if (convertToUtf8mb4($db, $_tg_table)) {
    $content[] = '<li class="correct">telegram_group: แปลงเป็น utf8mb4</li>';
}

$_tg_line = $prefix.'_line';
if ($db->tableExists($_tg_line) && $db->fieldExists($_tg_line, 'token') && $db->fieldExists($_tg_line, 'department')) {
    // โมดูลที่ตั้งให้ส่งเข้ากลุ่ม LINE เดิม
    $_tg_modules = [];
    foreach (['booking' => 'booking_line_id', 'car' => 'car_line_id', 'repair' => 'repair_line_id'] as $_module => $_key) {
        if (!empty($config[$_key])) {
            $_tg_modules[(int) $config[$_key]][$_module] = $_module;
        }
    }
    if (!empty($config['edocument_line_id']) && is_array($config['edocument_line_id'])) {
        foreach ($config['edocument_line_id'] as $_group) {
            if (!empty($_group)) {
                $_tg_modules[(int) $_group]['edocument'] = 'edocument';
            }
        }
    }
    $_tg_rows = $db->customQuery("SELECT `id`, `name`, `department` FROM `$_tg_line` ORDER BY `id`", true);
    foreach ($_tg_rows as $_row) {
        if ($db->first($_tg_table, [['id', (int) $_row['id']]])) {
            continue;
        }
        $_mods = isset($_tg_modules[(int) $_row['id']]) ? $_tg_modules[(int) $_row['id']] : [];
        $db->insert($_tg_table, [
            'id' => (int) $_row['id'],
            'name' => (string) $_row['name'],
            'department' => empty($_row['department']) ? null : (string) $_row['department'],
            'chat_id' => '',
            'modules' => empty($_mods) ? '' : ','.implode(',', $_mods).',',
            'is_active' => 0
        ]);
    }
    $_tg_moved = $db->customQuery(
        "SELECT COUNT(*) AS `c` FROM `$_tg_line` L INNER JOIN `$_tg_table` G ON G.`id` = L.`id`"
    );
    $_tg_moved = empty($_tg_moved) ? 0 : (int) $_tg_moved[0]->c;
    if ($_tg_moved === count($_tg_rows)) {
        $db->query("DROP TABLE `$_tg_line`");
        noteRowsMoved($_tg_line, $_tg_table, $_tg_moved);
        $content[] = '<li class="warning">กลุ่ม LINE Notify '.number_format($_tg_moved)
            .' กลุ่ม ย้ายมาเป็นกลุ่ม Telegram (ปิดไว้) — LINE Notify ปิดบริการแล้ว กรุณาเติมรหัสแชทของกลุ่ม Telegram แล้วเปิดใช้งาน</li>';
    }
}
foreach (['booking_line_id', 'car_line_id', 'repair_line_id', 'edocument_line_id'] as $_key) {
    unset($config[$_key]);
}
$content[] = '<li class="correct">telegram_group อัปเกรดสำเร็จ</li>';
