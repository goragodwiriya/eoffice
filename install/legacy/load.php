<?php
/**
 * install/legacy/load.php — สร้างฐานทดสอบจาก SQL ของระบบเดิม (ทดสอบการปรับรุ่น)
 *
 * ใช้:  php install/legacy/load.php <dbname> [sqlfile] [prefix]
 *   sqlfile ค่าเริ่มต้น install/legacy/eoffice-6.1.sql · prefix ค่าเริ่มต้น app
 *
 * ขั้นตอนทดสอบเต็ม (รันจาก root ของโปรเจ็ค)
 *   php install/legacy/load.php eoffice_legacy
 *   cp install/legacy/eoffice-6.1-config.php /tmp/eo-config.php
 *   php install/cli-verify.php eoffice_legacy app --config=/tmp/eo-config.php --save-counts=/tmp/eo-counts.json
 *   php install/cli-upgrade.php admin@localhost admin --confirm-backup --db=eoffice_legacy --prefix=app --config=/tmp/eo-config.php
 *   php install/cli-fresh.php eoffice_fresh app --config=/tmp/eo-fresh.php
 *   php install/cli-verify.php eoffice_legacy app --config=/tmp/eo-config.php --counts=/tmp/eo-counts.json \
 *       --fresh=eoffice_fresh --moved=app_inventory_stock:app_inventory_stock_bak,app_line:app_telegram_group --allow-extra
 * (--allow-extra : ของเหลือที่ตั้งใจเก็บไว้ — app_inventory_stock_bak, user.code)
 *
 * ทดสอบความสามารถของ E-Office ทั้งชุด (ปรับรุ่น + เรียก Controller จริง) : php install/legacy/test-eoffice.php
 * ทดสอบข้อมูลตัวอย่างของการติดตั้งใหม่ (cli-fresh + เรียก Controller จริง)  : php install/legacy/test-sample.php
 */
if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}
$root = dirname(__DIR__, 2).'/';
$cfg = (include $root.'settings/database.php')['mysql'];
$dbname = isset($argv[1]) ? preg_replace('/[^a-z0-9_]/i', '', $argv[1]) : '';
if ($dbname === '') {
    fwrite(STDERR, "ใช้: php install/legacy/load.php <dbname> [sqlfile] [prefix]\n");
    exit(1);
}
$sqlfile = isset($argv[2]) ? $argv[2] : __DIR__.'/eoffice-6.1.sql';
$prefix = isset($argv[3]) ? $argv[3] : 'app';
$pdo = new PDO(
    'mysql:host='.$cfg['hostname'].';port='.(empty($cfg['port']) ? 3306 : $cfg['port']).';charset=utf8mb4',
    $cfg['username'], $cfg['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$pdo->exec("DROP DATABASE IF EXISTS `$dbname`");
$pdo->exec("CREATE DATABASE `$dbname` CHARACTER SET utf8 COLLATE utf8_general_ci");
$pdo->exec("USE `$dbname`");
$sql = preg_replace('/^--.*$/m', '', str_replace('{prefix}', $prefix, file_get_contents($sqlfile)));
$n = 0;
foreach (preg_split('/;\s*\n/', $sql) as $q) {
    if (trim($q) !== '') {
        $pdo->exec($q);
        ++$n;
    }
}
echo "สร้าง $dbname จาก ".basename($sqlfile)." ($n คำสั่ง)\n";
