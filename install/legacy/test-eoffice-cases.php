<?php
/**
 * install/legacy/test-eoffice-cases.php — กรณีทดสอบของ test-eoffice.php (รันใน sandbox หลังปรับรุ่นฐานเดิม)
 *
 * ข้อมูลตั้งต้น (install/legacy/eoffice-6.1.sql หลังปรับรุ่น)
 *   สมาชิก 1 แอดมิน (แผนก 1) · 2 บุคลากร ช่างซ่อม can_repair (แผนก 2) · 3 พนักงานขับรถ สมาชิกทั่วไป
 *   หน่วยย่อย 1=0001 (ไม่มีผู้ถือ) · 2=0002 (ไม่มีผู้ถือ) · 3=0003 (สมาชิก 1) · 4=0004 (สมาชิก 1)
 */
$rows = function ($res) {
    return $res['data']['data'] ?? [];
};

echo "การปรับรุ่น: สิทธิ์และหมวดที่เปลี่ยนชื่อ\n";
$perm = array_column(edoc_rows('SELECT id, permission FROM {prefix}_user WHERE id IN (1, 3)'), 'permission', 'id');
ok('can_manage_room → can_manage_booking', [strpos($perm[1], ',can_manage_booking,') !== false, strpos($perm[1], 'can_manage_room') === false], [true, true]);
ok('พนักงานขับรถเดิม (chauffeur_status) ได้สิทธิ์ can_drive_car', strpos((string) $perm[3], ',can_drive_car,') !== false);
ok('หมวดอุปกรณ์เสริมของรถเป็น car_accessory', count(edoc_rows("SELECT 1 FROM {prefix}_category WHERE type = 'car_accessory'")), 2);
$res = edoc_call(new \Car\Booking\Controller(), 'get', edoc_request(1, 'GET', ['id' => 0]));
ok('พนักงานขับรถเดิมอยู่ในตัวเลือกคนขับ', in_array('3', array_map('strval', array_column($res['data']['options']['chauffeur'] ?? [], 'value')), true));

echo "inventory: ผู้ถือครองครุภัณฑ์\n";
$res = edoc_call(new \Inventory\Items\Controller(), 'get', edoc_request(1, 'GET', ['id' => 1]));
$items = array_column($res['data']['data']['options']['data'] ?? [], null, 'product_no');
ok('ตารางเลขครุภัณฑ์มีผู้ถือครองที่ย้ายมาจากระบบเดิม', [$items['0004']['member_id'] ?? null, $items['0004']['received_date'] ?? null], ['1', '2021-01-23']);
ok('ตัวเลือกผู้ถือครองมีสมาชิก', count($res['data']['data']['options']['options']['member_id'] ?? []) > 1);

$res = edoc_call(new \Inventory\Items\Controller(), 'save', edoc_request(1, 'POST', [
    'id' => 1, 'product_no' => ['0004'], 'topic' => [''], 'stock' => [1], 'unit' => ['ชิ้น'],
    'member_id' => [2], 'received_date' => ['2026-01-05'], '_editable_tables' => ['inventoryItems']
]));
ok('บันทึกเปลี่ยนผู้ถือครอง', $res['__status'] ?? null, 200);
$hist = edoc_rows('SELECT member_id, received_date, return_date IS NULL AS open FROM {prefix}_inventory_user WHERE inventory_item_id = 4 ORDER BY id');
ok('ปิดแถวผู้ถือเดิม เปิดแถวใหม่ (ประวัติครบ)', array_map(function ($r) {
    return [(int) $r['member_id'], (int) $r['open']];
}, $hist), [[1, 0], [2, 1]]);
ok('วันที่รับของผู้ถือใหม่', $hist[1]['received_date'] ?? null, '2026-01-05');

$res = edoc_call(new \Inventory\Mydevices\Controller(), 'index', edoc_request(2, 'GET', []));
ok('อุปกรณ์ของฉัน (สมาชิก 2)', array_column($rows($res), 'product_no'), ['0004']);
$res = edoc_call(new \Inventory\Mydevices\Controller(), 'index', edoc_request(1, 'GET', []));
ok('อุปกรณ์ของฉัน (สมาชิก 1) เหลือเครื่องเดียว', array_column($rows($res), 'product_no'), ['0003']);
ok('อุปกรณ์ของฉัน มีชื่อหมวดและรูปบาร์โค้ด', [($rows($res)[0]['category_id_text'] ?? '') !== '', strpos($rows($res)[0]['barcode'] ?? '', 'data:image/png') === 0], [true, true]);

echo "repair: แจ้งซ่อมได้เฉพาะเครื่องของตัวเองหรือเครื่องที่ไม่มีผู้ถือ\n";
$res = edoc_call(new \Repair\Autocomplete\Controller(), 'find', edoc_request(3, 'GET', ['q' => '000']));
$found = array_column($res['data'] ?? [], 'product_no');
sort($found);
ok('สมาชิก 3 ค้นหาเจอเฉพาะเครื่องที่ไม่มีผู้ถือ', $found, ['0001', '0002']);
$res = edoc_call(new \Repair\Autocomplete\Controller(), 'find', edoc_request(2, 'GET', ['q' => '000']));
$found = array_column($res['data'] ?? [], 'product_no');
sort($found);
ok('สมาชิก 2 ค้นหาเจอเครื่องของตัวเองด้วย', $found, ['0001', '0002', '0004']);
$res = edoc_call(new \Repair\Receive\Controller(), 'save', edoc_request(3, 'POST', ['id' => 0, 'product_no' => '0003', 'job_description' => 'เสีย']));
ok('แจ้งซ่อมเครื่องที่คนอื่นถือครองไม่ได้', $res['__status'] ?? null, 400);

$img = tempnam(sys_get_temp_dir(), 'img');
file_put_contents($img, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
$res = edoc_call(new \Repair\Receive\Controller(), 'save', edoc_request(3, 'POST', [
    'id' => 0, 'product_no' => '0001', 'job_description' => 'เปิดไม่ติด'
], ['repair' => ['path' => $img, 'name' => 'photo.png', 'type' => 'image/png']]));
ok('แจ้งซ่อมเครื่องที่ไม่มีผู้ถือได้', $res['__status'] ?? null, 200);
$job = edoc_rows("SELECT id, job_id FROM {prefix}_repair WHERE product_no = '0001'");
$jobId = (int) ($job[0]['id'] ?? 0);
ok('เลขที่ใบแจ้งซ่อมนับต่อจากเลขของระบบเดิม', $job[0]['job_id'] ?? null, 'JOB0003');
$files = glob(ROOT_PATH.DATA_FOLDER.'repair/'.$jobId.'/*.png');
ok('ไฟล์แนบเก็บที่ datas/repair/<id>/', count($files), 1);

$res = edoc_call(new \Repair\Receive\Controller(), 'get', edoc_request(3, 'GET', ['id' => $jobId]));
$attach = $res['data']['data']->repair ?? ($res['data']['data']['repair'] ?? []);
ok('ฟอร์มแจ้งซ่อมแสดงไฟล์แนบเดิม', count((array) $attach), 1);
$res = edoc_call(new \Repair\Detail\Controller(), 'get', edoc_request(3, 'GET', ['id' => $jobId]));
ok('หน้ารายละเอียดมีไฟล์แนบและลิงก์พิมพ์', [count($res['data']['data']['files'] ?? []), strpos($res['data']['data']['print_url'] ?? '', 'typ=receipt') !== false], [1, true]);
$fileId = json_encode(['file' => basename($files[0] ?? 'x.png'), 'id' => $jobId]);
$res = edoc_call(new \Repair\Receive\Controller(), 'removeFile', edoc_request(2, 'POST', ['id' => $fileId]));
ok('ช่างลบไฟล์แนบได้', [$res['__status'] ?? null, count(glob(ROOT_PATH.DATA_FOLDER.'repair/'.$jobId.'/*.png'))], [200, 0]);

$html = (new \Repair\Export\Controller())->receipt(edoc_request(3, 'GET', ['id' => $jobId]));
$html = $html instanceof \Kotchasan\Http\Response ? (string) $html->getBody() : (string) $html;
ok('พิมพ์ใบรับซ่อมของตัวเองได้', strpos($html, 'เปิดไม่ติด') !== false && strpos($html, 'receipt-qr') !== false);
$res = (new \Repair\Export\Controller())->receipt(edoc_request(3, 'GET', ['id' => 1]));
ok('พิมพ์ใบรับซ่อมของคนอื่นไม่ได้', $res instanceof \Kotchasan\Http\Response ? $res->getStatusCode() : 0, 403);

$res = edoc_call(new \Repair\Jobs\Controller(), 'index', edoc_request(2, 'GET', []));
$jobs = array_map('intval', array_column($rows($res), 'id'));
sort($jobs);
ok('ช่าง (ไม่ใช่ผู้จัดการ) เห็นงานของตัวเองและงานที่ยังไม่มอบหมาย', $jobs, [1, 2, $jobId]);

$cards = \Repair\Init\Controller::initDashboardCards([], null, \Index\Auth\Model::getUserById(3) ?? (object) ['id' => 3, 'permission' => [], 'status' => 3]);
ok('การ์ดหน้าแรก: งานซ่อมวันนี้ของสมาชิก', [$cards[0]['value'] ?? null, $cards[0]['url'] ?? null], [1, '/repair-history']);

echo "personnel: บุคลากรตามสถานะสมาชิก และแผนกเดียวกับสมาชิก\n";
edoc_rows("UPDATE {prefix}_user SET status = 2 WHERE id = 3");
$res = edoc_call(new \Personnel\Setup\Controller(), 'index', edoc_request(1, 'GET', []));
$ids = array_map('intval', array_column($rows($res), 'id'));
sort($ids);
ok('สมาชิกที่เปลี่ยนเป็นสถานะบุคลากรขึ้นในรายชื่อเอง', $ids, [1, 2, 3]);
\Personnel\Person\Model::syncDepartment(3, 2);
ok('แผนกของบุคลากรเขียนลง user_meta', array_column(edoc_rows("SELECT value FROM {prefix}_user_meta WHERE member_id = 3 AND name = 'department'"), 'value'), ['2']);
$res = edoc_call(new \Personnel\Settings\Controller(), 'get', edoc_request(1, 'GET', []));
ok('ตั้งค่าบุคลากรมีสถานะสมาชิกที่เป็นบุคลากร', $res['data']['data']['personnel_status'] ?? null, [1, 2]);

echo "การแจ้งเตือน: สวิตช์อีเมล\n";
$res = edoc_call(new \Booking\Settings\Controller(), 'get', edoc_request(1, 'GET', []));
ok('booking_send_mail จากค่าเดิมของระบบเดิม', $res['data']['data']['booking_send_mail'] ?? null, 0);
$res = edoc_call(new \Car\Settings\Controller(), 'get', edoc_request(1, 'GET', []));
ok('car_email จากค่าเดิมของระบบเดิม', $res['data']['data']['car_email'] ?? null, 1);
$res = edoc_call(new \Repair\Settings\Controller(), 'get', edoc_request(1, 'GET', []));
ok('repair_send_mail / repair_status_success', [$res['data']['data']['repair_send_mail'] ?? null, $res['data']['data']['repair_status_success'] ?? null], [0, 7]);
$res = edoc_call(new \Edocument\Settings\Controller(), 'get', edoc_request(1, 'GET', []));
ok('edocument_send_mail', $res['data']['data']['edocument_send_mail'] ?? null, 1);

echo "กลุ่ม Telegram (แทนกลุ่ม LINE Notify)\n";
$res = edoc_call(new \Telegramgroup\Groups\Controller(), 'index', edoc_request(1, 'GET', []));
ok('กลุ่ม LINE เดิมย้ายมาเป็นกลุ่ม Telegram ที่ปิดไว้', [count($rows($res)), (int) ($rows($res)[0]['is_active'] ?? -1)], [1, 0]);
$res = edoc_call(new \Telegramgroup\Group\Controller(), 'save', edoc_request(1, 'POST', [
    'id' => 0, 'department' => '', 'name' => 'ทุกแผนก', 'chat_id' => '-1001', 'modules' => ['booking', 'repair'], 'is_active' => 1
]));
ok('เพิ่มกลุ่ม Telegram', $res['__status'] ?? null, 200);
edoc_call(new \Telegramgroup\Group\Controller(), 'save', edoc_request(1, 'POST', [
    'id' => 0, 'department' => '2', 'name' => '', 'chat_id' => '-1002', 'modules' => ['booking'], 'is_active' => 1
]));
$res = edoc_call(new \Telegramgroup\Group\Controller(), 'save', edoc_request(1, 'POST', ['id' => 0, 'department' => '', 'name' => '', 'chat_id' => '']));
ok('ไม่กรอกชื่อและรหัสแชท บันทึกไม่ได้', $res['__status'] ?? null, 400);
ok('การจองของแผนก 2 ส่งเข้ากลุ่มทุกแผนก + กลุ่มแผนก 2', \Telegramgroup\Group\Model::chatIds('booking', '2'), ['-1001', '-1002']);
ok('การจองของแผนก 3 ส่งเข้ากลุ่มทุกแผนกเท่านั้น', \Telegramgroup\Group\Model::chatIds('booking', '3'), ['-1001']);
ok('โมดูลที่กลุ่มไม่ได้เลือก ไม่ส่ง', \Telegramgroup\Group\Model::chatIds('car', '2'), []);
ok('กลุ่มที่ปิดอยู่ไม่ส่ง (กลุ่มที่ย้ายมา)', \Telegramgroup\Group\Model::chatIds('edocument', '1'), []);
$res = edoc_call(new \Telegramgroup\Groups\Controller(), 'action', edoc_request(1, 'POST', ['action' => 'active', 'id' => 1]));
ok('เปิดกลุ่มที่ยังไม่มีรหัสแชทไม่ได้', $res['__status'] ?? null, 400);

summary();
