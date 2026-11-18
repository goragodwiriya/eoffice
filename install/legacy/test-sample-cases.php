<?php
/**
 * install/legacy/test-sample-cases.php — กรณีทดสอบของ test-sample.php (รันใน sandbox หลังติดตั้งใหม่)
 *
 * สมาชิกตัวอย่าง : 1 แอดมิน · 2 สมชาย ช่างซ่อม · 3 สมศรี ผู้จัดการ · 4 วิชัย พนักงานขับรถ · 5 มานี สมาชิก
 */
$rows = function ($res) {
    return $res['data']['data'] ?? [];
};
$ids = function ($res) use ($rows) {
    $ids = array_map('intval', array_column($rows($res), 'id'));
    sort($ids);

    return $ids;
};

echo "สมาชิกตัวอย่าง\n";
$users = edoc_rows('SELECT id, password, active FROM {prefix}_user ORDER BY id');
ok('สมาชิก 5 คน (ผู้ดูแล + ตัวอย่าง 4)', count($users), 5);
ok('สมาชิกตัวอย่างไม่มีรหัสผ่าน (เข้าระบบไม่ได้จนกว่าผู้ดูแลตั้งให้)', array_values(array_unique(array_column(array_slice($users, 1), 'password'))), ['']);
ok('รหัสผ่านว่างตรวจผ่านไม่ได้', \Kotchasan\Password::verify('', '', '', 'x'), false);

echo "จองห้องประชุม\n";
ok('ใบจองของสมศรี', $ids(edoc_call(new \Booking\Mybookings\Controller(), 'index', edoc_request(3, 'GET', []))), [1]);
ok('ใบจองรออนุมัติอยู่ในหน้าอนุมัติของผู้ดูแล', in_array(2, $ids(edoc_call(new \Booking\Approvals\Controller(), 'index', edoc_request(1, 'GET', []))), true));
$res = edoc_call(new \Booking\Booking\Controller(), 'get', edoc_request(3, 'GET', ['id' => 1]));
ok('ใบจองมีอุปกรณ์จากหมวดตัวอย่าง', array_map('intval', (array) ($res['data']['accessories'] ?? [])), [1, 2, 4]);

echo "จองรถ\n";
ok('ใบจองรถของมานี', $ids(edoc_call(new \Car\Mybookings\Controller(), 'index', edoc_request(5, 'GET', []))), [3]);
$res = edoc_call(new \Car\Booking\Controller(), 'get', edoc_request(3, 'GET', ['id' => 1]));
ok('วิชัยอยู่ในตัวเลือกพนักงานขับรถ', in_array('4', array_column($res['data']['options']['chauffeur'] ?? [], 'value'), true));
ok('ตัวเลือกอุปกรณ์เสริมของรถมาจากหมวดตัวอย่าง', count($res['data']['options']['car_accessories'] ?? []), 3);
$meta = edoc_rows("SELECT M.name, C.topic FROM {prefix}_vehicles_meta M LEFT JOIN {prefix}_category C ON C.type = M.name AND C.category_id = M.value WHERE M.vehicle_id = 1 ORDER BY M.name");
ok('ยี่ห้อ/ประเภทของรถอ้างหมวดที่มีจริง', array_column($meta, 'topic'), ['Hino', 'รถบัส']);

$res = edoc_call(new \Index\Calendar\Controller(), 'index', edoc_request(1, 'GET', ['start' => date('Y-m-d', strtotime('-7 days')), 'end' => date('Y-m-d', strtotime('+14 days'))]));
$events = $res['data']['data'] ?? ($res['data'] ?? []);
ok('ปฏิทินรวมมีการจองห้องและรถที่อนุมัติแล้ว', count((array) $events) >= 4);

echo "สารบรรณ\n";
$res = edoc_call(new \Edocument\Inbox\Controller(), 'index', edoc_request(1, 'GET', []));
ok('เอกสารขาเข้าของผู้ดูแล', $ids($res), [2, 5]);
$cards = \Edocument\Init\Controller::initDashboardCards([], null, (object) ['id' => 1, 'status' => 1, 'permission' => [], 'metas' => ['department' => ['1']]]);
ok('การ์ดหน้าแรก: เอกสารใหม่ 1 ฉบับ', $cards[0]['value'] ?? null, 1);
ok('เอกสารขาเข้าของมานี (แผนก 3)', $ids(edoc_call(new \Edocument\Inbox\Controller(), 'index', edoc_request(5, 'GET', []))), [1, 4]);

echo "ครุภัณฑ์\n";
ok('ทะเบียนครุภัณฑ์ 5 รายการ', count($rows(edoc_call(new \Inventory\Inventories\Controller(), 'index', edoc_request(3, 'GET', [])))), 5);
$res = edoc_call(new \Inventory\Items\Controller(), 'get', edoc_request(3, 'GET', ['id' => 1]));
$items = array_column($res['data']['data']['options']['data'] ?? [], 'member_id', 'product_no');
ok('เลขครุภัณฑ์และผู้ถือครองของ Notebook', $items, ['NB-0001' => '3', 'NB-0002' => '0']);
$res = edoc_call(new \Inventory\Mydevices\Controller(), 'index', edoc_request(3, 'GET', ['sort' => 'product_no asc']));
ok('อุปกรณ์ของสมศรี', array_column($rows($res), 'product_no'), ['MN-0001', 'NB-0001']);
$balance = edoc_rows('SELECT V.id, V.stock, (SELECT SUM(qty) FROM {prefix}_inventory_stock S WHERE S.inventory_id = V.id) AS ledger FROM {prefix}_inventory V ORDER BY V.id');
ok('ยอดคงเหลือของสินค้า = ยอดในสมุดบัญชี', array_map(function ($r) {
    return (float) $r['stock'] === (float) $r['ledger'];
}, $balance), [true, true, true, true, true]);

echo "แจ้งซ่อม\n";
ok('รายการซ่อมของผู้ดูแล', $ids(edoc_call(new \Repair\Jobs\Controller(), 'index', edoc_request(1, 'GET', []))), [1, 2, 3]);
ok('ประวัติแจ้งซ่อมของสมศรี', $ids(edoc_call(new \Repair\History\Controller(), 'index', edoc_request(3, 'GET', []))), [1, 3]);
ok('ช่างเห็นงานของตัวเองและงานที่ยังไม่มีช่าง', $ids(edoc_call(new \Repair\Jobs\Controller(), 'index', edoc_request(2, 'GET', []))), [1, 2, 3]);
$res = edoc_call(new \Repair\Detail\Controller(), 'get', edoc_request(5, 'GET', ['id' => 2]));
ok('ประวัติการซ่อมของ JOB0002 ครบ 3 ขั้น', [count($res['data']['data']['timeline']['data'] ?? []), $res['data']['data']['topic'] ?? null], [3, 'เครื่องพิมพ์ Canon PIXMA G3010']);
$found = array_column(edoc_call(new \Repair\Autocomplete\Controller(), 'find', edoc_request(5, 'GET', ['q' => '-000']))['data'] ?? [], 'product_no');
sort($found);
ok('มานีแจ้งซ่อมได้เครื่องของตัวเองและเครื่องที่ไม่มีผู้ถือ', $found, ['MN-0002', 'NB-0002', 'PJ-0001', 'PR-0001']);
$cards = \Repair\Init\Controller::initDashboardCards([], null, (object) ['id' => 1, 'status' => 1, 'permission' => []]);
ok('การ์ดหน้าแรก: งานซ่อมวันนี้ 1 งาน', $cards[0]['value'] ?? null, 1);
edoc_call(new \Repair\Receive\Controller(), 'save', edoc_request(5, 'POST', ['id' => 0, 'product_no' => 'PJ-0001', 'job_description' => 'หลอดภาพไม่ติด']));
ok('ใบแจ้งซ่อมใบต่อไปเป็น JOB0004', edoc_rows("SELECT job_id FROM {prefix}_repair WHERE product_no = 'PJ-0001'")[0]['job_id'] ?? null, 'JOB0004');

echo "บุคลากร\n";
ok('รายชื่อบุคลากร (สถานะ 1 และ 2)', $ids(edoc_call(new \Personnel\Setup\Controller(), 'index', edoc_request(1, 'GET', []))), [1, 2, 3]);
$res = edoc_call(new \Personnel\Person\Controller(), 'get', edoc_request(1, 'GET', ['id' => 3]));
$details = array_column($res['data']['data']['details'] ?? [], 'value', 'name');
ok('ข้อมูลเพิ่มเติม (งาน) ของบุคลากร', $details['custom[job]'] ?? null, 'งานพัสดุและยานพาหนะ');

echo "บุคลากร: รหัสพนักงาน (ไม่แสดงสาธารณะ) และไม่ใช้ข้อมูลส่วนตัวเข้าระบบ\n";
$cols = array_column(edoc_rows('SHOW COLUMNS FROM {prefix}_personnel'), 'Field');
ok('ตารางบุคลากรมีรหัสพนักงาน ไม่มีเลขประชาชน/ชั้น/ห้อง', [in_array('employee_code', $cols), array_values(array_intersect(['id_card', 'class', 'room'], $cols))], [true, []]);
$view = function ($member, $id) {
    $res = edoc_call(new \Personnel\Setup\Controller(), 'action', edoc_request($member, 'POST', ['action' => 'view', 'id' => $id]));

    return $res['data']['data'] ?? [];
};
ok('สมาชิกทั่วไปดูรายละเอียดบุคลากร ไม่เห็นรหัสพนักงาน', [$view(5, 3)['employee_code'] ?? null, $view(5, 3)['can_manage'] ?? null], ['', 0]);
ok('ผู้ดูแลเห็นรหัสพนักงาน', $view(1, 3)['employee_code'] ?? null, 'EMP003');
$lists = edoc_call(new \Personnel\Lists\Controller(), 'index', edoc_request(5, 'GET', []));
ok('รายชื่อบุคลากร (สาธารณะ) ไม่มีรหัสพนักงานใน API', array_key_exists('employee_code', $rows($lists)[0] ?? []), false);
$res = edoc_call(new \Personnel\Person\Controller(), 'get', edoc_request(3, 'GET', ['id' => 3]));
ok('บุคลากรแก้ข้อมูลตัวเอง ไม่เห็นรหัสพนักงาน', [$res['data']['data']['employee_code'] ?? null, $res['data']['data']['can_manage'] ?? null], ['', 0]);
edoc_call(new \Personnel\Person\Controller(), 'save', edoc_request(3, 'POST', ['id' => 3, 'name' => 'สมศรี รักงาน', 'phone' => '0810000003', 'employee_code' => 'HACK', 'position' => 3, 'department' => 2]));
ok('บุคลากรเปลี่ยนรหัสพนักงานของตัวเองไม่ได้', edoc_rows('SELECT employee_code FROM {prefix}_personnel WHERE id = 3')[0]['employee_code'] ?? null, 'EMP003');
$res = edoc_call(new \Personnel\Person\Controller(), 'save', edoc_request(1, 'POST', ['id' => 0, 'name' => 'บุคลากรใหม่', 'phone' => '0819999999', 'employee_code' => 'EMP001', 'position' => 4, 'department' => 1]));
ok('รหัสพนักงานซ้ำ บันทึกไม่ได้', [$res['__status'] ?? null, isset($res['data']['errors']['employee_code']) || isset($res['errors']['employee_code'])], [400, true]);
$res = edoc_call(new \Personnel\Person\Controller(), 'save', edoc_request(1, 'POST', ['id' => 0, 'name' => 'บุคลากรใหม่', 'phone' => '0819999999', 'employee_code' => 'EMP 009!', 'position' => 4, 'department' => 1]));
$new = edoc_rows("SELECT U.username, P.employee_code FROM {prefix}_user U JOIN {prefix}_personnel P ON P.id = U.id WHERE U.name = 'บุคลากรใหม่'");
ok('เพิ่มบุคลากรใหม่: ไม่สร้างชื่อผู้ใช้ให้ รหัสพนักงานตัดอักขระที่ไม่ใช้', [$res['__status'] ?? null, array_key_exists('username', $new[0] ?? []) ? $new[0]['username'] : 'x', $new[0]['employee_code'] ?? null], [200, null, 'EMP009']);

echo "บุคลากร: ไฟล์ personnel.csv (ดาวน์โหลดข้อมูลปัจจุบัน แก้ไข แล้วนำเข้ากลับ)\n";
$csv = edoc_subprocess('\\Personnel\\Import\\Controller', 'sample', 1, []);
$lines = array_values(array_filter(preg_split('/\r?\n/', preg_replace('/^\xEF\xBB\xBF/', '', $csv)), 'strlen'));
$table = array_map(function ($line) {
    return str_getcsv($line, ',', '"', '');
}, $lines);
ok('ไฟล์มีหัวคอลัมน์ (ไม่ใช่ช่องว่าง)', $table[0] ?? null, \Personnel\Import\Model::header());
$byCode = array_column(array_slice($table, 1), null, 1);
ok('ไฟล์มีบุคลากรปัจจุบันทุกคน', count($table) - 1, (int) edoc_rows('SELECT COUNT(*) AS c FROM {prefix}_personnel')[0]['c']);
$job3 = (string) (json_decode(edoc_rows("SELECT custom FROM {prefix}_personnel WHERE employee_code = 'EMP003'")[0]['custom'] ?? '{}', true)['job'] ?? '');
ok('ตำแหน่ง/แผนก/ข้อมูลเพิ่มเติมส่งออกเป็นชื่อ', array_slice($byCode['EMP003'] ?? [], 3), ['หัวหน้างาน', 'จัดซื้อจัดจ้าง', $job3]);
// แก้ในไฟล์แล้วนำเข้ากลับ : EMP003 เปลี่ยนตำแหน่ง (ใส่ชื่อ) และแผนก (ใส่รหัส) · EMP002 เว้นช่องงานไว้ · เพิ่มคนใหม่ 1 คน
$rowsOut = [$table[0]];
foreach (array_slice($table, 1) as $r) {
    if ($r[1] === 'EMP003') {
        $r[3] = 'ผู้อำนวยการ';
        $r[4] = '3';
    } elseif ($r[1] === 'EMP002') {
        $r[5] = '';
    }
    $rowsOut[] = $r;
}
$rowsOut[] = ['นายใหม่ นำเข้า', 'EMP200', '', 'เจ้าหน้าที่', 'บริหาร', 'งานสารบรรณ'];
$file = tempnam(sys_get_temp_dir(), 'pcsv');
$fh = fopen($file, 'w');
fwrite($fh, "\xEF\xBB\xBF");
foreach ($rowsOut as $r) {
    fputcsv($fh, $r, ',', '"', '');
}
fclose($fh);
$result = \Personnel\Import\Model::import($file, \Personnel\Import\Model::readCharset());
ok('นำเข้ากลับ: อัปเดตคนเดิม เพิ่มคนใหม่', [$result->updated, $result->row], [count($table) - 1, 1]);
$p3 = edoc_rows("SELECT position, department, custom FROM {prefix}_personnel WHERE employee_code = 'EMP003'")[0] ?? [];
ok('คนเดิมได้ตำแหน่ง (จากชื่อ) และแผนก (จากรหัส) ใหม่', [(int) ($p3['position'] ?? 0), (int) ($p3['department'] ?? 0)], [1, 3]);
ok('แผนกของสมาชิกเปลี่ยนตาม', array_column(edoc_rows("SELECT value FROM {prefix}_user_meta WHERE member_id = 3 AND name = 'department'"), 'value'), ['3']);
ok('ช่องที่เว้นว่าง = คงค่าเดิม', json_decode(edoc_rows("SELECT custom FROM {prefix}_personnel WHERE employee_code = 'EMP002'")[0]['custom'] ?? '{}', true)['job'] ?? null, 'งานซ่อมบำรุง');
$p200 = edoc_rows("SELECT U.username, P.position, P.department FROM {prefix}_personnel P JOIN {prefix}_user U ON U.id = P.id WHERE P.employee_code = 'EMP200'")[0] ?? [];
ok('คนใหม่จากไฟล์: หมวดจากชื่อ ไม่มีชื่อผู้ใช้', [(int) ($p200['position'] ?? 0), (int) ($p200['department'] ?? 0), array_key_exists('username', $p200) ? $p200['username'] : 'x'], [4, 1, null]);
ok('นำเข้าซ้ำไฟล์เดิม ไม่เพิ่มคนซ้ำ', [\Personnel\Import\Model::import($file, 'AUTO')->row, (int) edoc_rows("SELECT COUNT(*) AS c FROM {prefix}_personnel WHERE employee_code = 'EMP200'")[0]['c']], [0, 1]);
@unlink($file);
$sample = \Personnel\Import\Model::sample();
ok('ยังไม่มีบุคลากร = ได้แถวตัวอย่าง 2 แถว ครบทุกคอลัมน์', [count($sample), count($sample[0]), $sample[0][3]], [2, count(\Personnel\Import\Model::header()), 'ผู้อำนวยการ']);

echo "กลุ่ม Telegram\n";
$res = edoc_call(new \Telegramgroup\Groups\Controller(), 'index', edoc_request(1, 'GET', ['sort' => 'id asc']));
ok('กลุ่มตัวอย่าง 2 กลุ่ม ชื่อกลุ่มแผนกมาจากหมวดแผนก', array_column($rows($res), 'name'), ['กลุ่มผู้บริหาร (ทุกแผนก)', 'จัดซื้อจัดจ้าง']);
ok('กลุ่มตัวอย่างปิดอยู่ ยังไม่ส่งข้อความ', \Telegramgroup\Group\Model::chatIds('booking', '2'), []);

summary();
