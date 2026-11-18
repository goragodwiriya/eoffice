<?php
/**
 * modules/inventory/install/legacy-eoffice.php — แปลงทะเบียนครุภัณฑ์ของ E-Office เดิม (GCMS 6.x)
 *
 * upgrade.php ของโมดูลเรียกไฟล์นี้ก่อนทำอย่างอื่น ตัวแปรที่ใช้ได้ชุดเดียวกับ upgrade.php
 * ($db, $db_config, $prefix, $content, $config)
 *
 * รูปแบบของ E-Office เดิม (ไม่เหมือน inventory/oms/oas ตัวไหนเลย)
 *   inventory        id · topic · detail · unit (id ของหมวด unit) · status (1 = ใช้งานอยู่)
 *   inventory_meta   category_id · type_id · model_id เก็บเป็นแถว meta
 *   inventory_stock  id · inventory_id · product_no · stock · create_date   ← "หน่วยย่อย" ชื่อตารางชนกับตารางยอดคงเหลือของสคีมากลาง
 *   inventory_user   id · stock_id · member_id · create_date · return_date   ← ผู้รับผิดชอบ/ผู้ใช้งานครุภัณฑ์
 *
 * ปลายทาง
 *   inventory_stock (เดิม) → inventory_items โดยคง id เดิมไว้ (inventory_user.stock_id ชี้ id เดียวกัน)
 *                           แล้วเปลี่ยนชื่อตารางเดิมเป็น inventory_stock_bak เพื่อให้ตารางยอดคงเหลือใช้ชื่อนี้ได้
 *   inventory.status → is_active/inuse · detail → description · unit (id) → ชื่อหน่วย
 *   inventory_meta category_id/type_id/model_id → คอลัมน์ของ inventory (แถว meta คงไว้)
 *   inventory_user.stock_id → inventory_item_id · create_date → received_date
 *
 * เงื่อนไขทุกข้อถามว่า "ต้องแก้ไหม" จึงรันซ้ำได้ ถ้าค้างกลางทางรันใหม่แล้วเดินต่อจนจบ
 */
if (!defined('ROOT_PATH')) {
    exit;
}

$_eo_stock = $prefix.'_inventory_stock';
$_eo_inventory = $prefix.'_inventory';
$_eo_items = $prefix.'_inventory_items';
$_eo_user = $prefix.'_inventory_user';

// ตาราง inventory_stock แบบ E-Office = มี product_no แต่ไม่มี inventory_item_id
$_eo_legacy = $db->tableExists($_eo_stock)
    && $db->fieldExists($_eo_stock, 'product_no')
    && !$db->fieldExists($_eo_stock, 'inventory_item_id');

if ($_eo_legacy) {
    if (ensureTable($db, $prefix, $_eo_items)) {
        $content[] = '<li class="correct">inventory: สร้างตาราง inventory_items</li>';
    }
    // หน่วยของ E-Office เก็บเป็น id ของหมวด unit — แปลงเป็นชื่อหน่วยก่อน เพราะสคีมากลางเก็บเป็นข้อความ
    $_eo_units = [];
    foreach ($db->customQuery("SELECT `category_id`, `topic` FROM `{$prefix}_category` WHERE `type` = 'unit'") as $_row) {
        $_eo_units[(string) $_row->category_id] = (string) $_row->topic;
    }
    if ($db->isColumnType($_eo_inventory, 'unit', 'int')) {
        $db->query("ALTER TABLE `$_eo_inventory` MODIFY `unit` varchar(20) DEFAULT NULL");
    }
    foreach ($_eo_units as $_id => $_topic) {
        $db->customQuery("UPDATE `$_eo_inventory` SET `unit` = ? WHERE `unit` = ?", false, [mb_substr($_topic, 0, 20), $_id]);
    }
    $db->query("UPDATE `$_eo_inventory` SET `unit` = NULL WHERE `unit` = '0' OR `unit` = ''");

    // หน่วยย่อย — คง id เดิม เพื่อให้ inventory_user และการอ้างอิงอื่นชี้ถูกตัว
    $_eo_before = $db->customQuery("SELECT COUNT(*) AS `c` FROM `$_eo_stock`");
    $_eo_before = empty($_eo_before) ? 0 : (int) $_eo_before[0]->c;
    $db->query(
        "INSERT IGNORE INTO `$_eo_items` (`id`, `sku`, `product_no`, `inventory_id`, `unit`, `cut_stock`, `stock`, `last_update`)
         SELECT S.`id`,
                IF(S.`product_no` IS NULL OR S.`product_no` = '', CONCAT('ITEM-', S.`id`), S.`product_no`),
                IF(S.`product_no` IS NULL OR S.`product_no` = '', CONCAT('ITEM-', S.`id`), S.`product_no`),
                S.`inventory_id`, V.`unit`, 1, S.`stock`, IFNULL(UNIX_TIMESTAMP(S.`create_date`), 0)
           FROM `$_eo_stock` S
           LEFT JOIN `$_eo_inventory` V ON V.`id` = S.`inventory_id`"
    );
    $_eo_moved = $db->customQuery(
        "SELECT COUNT(*) AS `c` FROM `$_eo_stock` S INNER JOIN `$_eo_items` I ON I.`id` = S.`id` AND I.`inventory_id` = S.`inventory_id`"
    );
    $_eo_moved = empty($_eo_moved) ? 0 : (int) $_eo_moved[0]->c;
    if ($_eo_moved < $_eo_before) {
        throw new \Exception(
            'ย้ายเลขครุภัณฑ์ของระบบเดิมเข้า <code>'.$_eo_items.'</code> ได้ไม่ครบ ('.$_eo_moved.' จาก '.$_eo_before.' รายการ)<br>'
            .'สาเหตุที่พบบ่อยคือเลขครุภัณฑ์ (product_no) ซ้ำกันในตาราง <code>'.$_eo_stock.'</code> '
            .'กรุณาแก้ให้ไม่ซ้ำกัน แล้วปรับรุ่นใหม่อีกครั้ง'
        );
    }

    // สถานะ รายละเอียด และหมวดหมู่ของตัวสินค้า
    if ($db->fieldExists($_eo_inventory, 'status')) {
        if (ensureColumn($db, $_eo_inventory, 'is_active', 'tinyint(1)', false, 1, '', 'topic')) {
            $content[] = '<li class="correct">inventory: เพิ่มคอลัมน์ is_active</li>';
        }
        $db->query("UPDATE `$_eo_inventory` SET `is_active` = IF(`status` = 1, 1, 0)");
        $db->query("ALTER TABLE `$_eo_inventory` DROP COLUMN `status`");
        $content[] = '<li class="correct">inventory: status → is_active</li>';
    }
    if ($db->fieldExists($_eo_inventory, 'detail')) {
        if (ensureColumn($db, $_eo_inventory, 'description', 'text', true, null, '', 'topic')) {
            $content[] = '<li class="correct">inventory: เพิ่มคอลัมน์ description</li>';
        }
        $db->query("UPDATE `$_eo_inventory` SET `description` = `detail` WHERE (`description` IS NULL OR `description` = '') AND `detail` IS NOT NULL");
        $db->query("ALTER TABLE `$_eo_inventory` DROP COLUMN `detail`");
        $content[] = '<li class="correct">inventory: detail → description</li>';
    }
    foreach (['category_id', 'type_id', 'model_id'] as $_col) {
        if (ensureColumn($db, $_eo_inventory, $_col, 'varchar(10)', true, null, '', 'description')) {
            $content[] = '<li class="correct">inventory: เพิ่มคอลัมน์ '.$_col.'</li>';
        }
        $db->query(
            "UPDATE `$_eo_inventory` V
               JOIN `{$prefix}_inventory_meta` M ON M.`inventory_id` = V.`id` AND M.`name` = '$_col'
                SET V.`$_col` = LEFT(M.`value`, 10)
              WHERE V.`$_col` IS NULL OR V.`$_col` = ''"
        );
    }

    // ครุภัณฑ์ของ E-Office มีเลขประจำทุกชิ้น ยอดจึงอยู่ที่หน่วยย่อย = นับแยกรายชิ้น (count_stock 2)
    // ยอดยกมาของ upgrade.php ลงที่ระดับหน่วยย่อยด้วย ทั้งสองที่จึงตรงกัน
    if (ensureColumn($db, $_eo_inventory, 'count_stock', 'tinyint(1)', false, 1, '', 'topic')) {
        $content[] = '<li class="correct">inventory: เพิ่มคอลัมน์ count_stock</li>';
        $db->query(
            "UPDATE `$_eo_inventory` SET `count_stock` = 2
              WHERE `id` IN (SELECT `inventory_id` FROM `$_eo_items`)"
        );
    }

    // ผู้รับผิดชอบครุภัณฑ์ — stock_id เดิมคือ id ของหน่วยย่อยตัวเดียวกัน
    if ($db->tableExists($_eo_user) && $db->fieldExists($_eo_user, 'stock_id')) {
        if (ensureColumn($db, $_eo_user, 'inventory_item_id', 'int(11)', false, 0, '', 'id')) {
            $content[] = '<li class="correct">inventory_user: เพิ่มคอลัมน์ inventory_item_id</li>';
        }
        $db->query("UPDATE `$_eo_user` SET `inventory_item_id` = `stock_id` WHERE `inventory_item_id` = 0");
        $db->query("ALTER TABLE `$_eo_user` DROP COLUMN `stock_id`");
        $content[] = '<li class="correct">inventory_user: stock_id → inventory_item_id</li>';
    }

    // ตารางเดิมว่างจากหน้าที่แล้ว — เก็บไว้เป็น _bak (ไม่ลบข้อมูลของใคร) ให้ชื่อนี้เป็นของตารางยอดคงเหลือ
    $_eo_bak = $_eo_stock.'_bak';
    if ($db->tableExists($_eo_bak)) {
        $_eo_bak = $_eo_stock.'_bak'.date('YmdHis');
    }
    $db->query("RENAME TABLE `$_eo_stock` TO `$_eo_bak`");
    noteRowsMoved($_eo_stock, $_eo_bak, $_eo_before);
    $content[] = '<li class="correct">inventory: ย้ายเลขครุภัณฑ์ของระบบเดิม '.number_format($_eo_moved)
        .' รายการเข้า inventory_items แล้วเก็บตารางเดิมไว้เป็น '.$_eo_bak.' (ตรวจแล้วลบเองได้)</li>';
}

// =============================================================================
// inventory_user — ผู้รับผิดชอบครุภัณฑ์ (ของ E-Office) ทั้งฐานเดิมและฐานใหม่
// =============================================================================
if (ensureTable($db, $prefix, $_eo_user)) {
    $content[] = '<li class="correct">inventory: สร้างตาราง inventory_user</li>';
}
if (convertToInnoDB($db, $_eo_user)) {
    $content[] = '<li class="correct">inventory_user: แปลงเป็น InnoDB</li>';
}
if (convertToUtf8mb4($db, $_eo_user)) {
    $content[] = '<li class="correct">inventory_user: แปลงเป็น utf8mb4</li>';
}
if ($db->fieldExists($_eo_user, 'create_date') && !$db->fieldExists($_eo_user, 'received_date')) {
    $db->query("ALTER TABLE `$_eo_user` CHANGE `create_date` `received_date` date DEFAULT NULL");
    $content[] = '<li class="correct">inventory_user: เปลี่ยนชื่อ create_date → received_date</li>';
}
if (!$db->indexExists($_eo_user, 'PRIMARY')) {
    $db->query("ALTER TABLE `$_eo_user` ADD PRIMARY KEY (`id`)");
}
if (!isAutoIncrement($db, $_eo_user, 'id')) {
    $db->query("ALTER TABLE `$_eo_user` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT");
    $content[] = '<li class="correct">inventory_user: กำหนด id เป็น AUTO_INCREMENT</li>';
}
foreach ([
    'inventory_item_id' => ['int(11)', false, 0, 'id'],
    'member_id' => ['int(11)', false, 0, 'inventory_item_id'],
    'received_date' => ['date', true, null, 'member_id'],
    'return_date' => ['datetime', true, null, 'received_date']
] as $_col => $_def) {
    if (ensureColumn($db, $_eo_user, $_col, $_def[0], $_def[1], $_def[2], '', $_def[3])) {
        $content[] = '<li class="correct">inventory_user: ปรับคอลัมน์ '.$_col.'</li>';
    }
}
if (ensureIndexes($db, $_eo_user, [
    'idx_item' => '`inventory_item_id`, `return_date`',
    'idx_member' => '`member_id`, `return_date`'
])) {
    $content[] = '<li class="correct">inventory_user: ปรับดัชนี</li>';
}
if (dropIndexes($db, $_eo_user, ['member_id', 'stock_id'])) {
    $content[] = '<li class="correct">inventory_user: ลบดัชนีรุ่นเก่าที่ถูกแทนแล้ว</li>';
}
