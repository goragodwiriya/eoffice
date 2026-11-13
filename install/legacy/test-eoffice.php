<?php
/**
 * install/legacy/test-eoffice.php — ทดสอบความสามารถของ E-Office ที่พอร์ตมา (end-to-end)
 *
 * สร้างฐานทดสอบจาก install/legacy/eoffice-6.1.sql → สำเนาโปรเจ็คไป sandbox → รัน install/cli-upgrade.php ตัวจริง
 * → เรียก Controller จริงของแต่ละโมดูล (ผ่าน modules/edocument/tests/bootstrap.php) แล้วตรวจผลกับฐานข้อมูล
 *
 * ใช้:  php install/legacy/test-eoffice.php        (ฐานทดสอบ eoffice_feature_test ลบทิ้งเมื่อจบ)
 */
if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}
$root = realpath(__DIR__.'/../..');
$testDb = 'eoffice_feature_test';
$dbConfig = include $root.'/settings/database.php';
if ($dbConfig['mysql']['dbname'] === $testDb) {
    exit("ชื่อฐานทดสอบซ้ำกับฐานจริง\n");
}
passthru('php '.escapeshellarg($root.'/install/legacy/load.php').' '.$testDb);
$sandbox = sys_get_temp_dir().'/eoffice-tests-'.getmypid();
exec('rm -rf '.escapeshellarg($sandbox));
mkdir($sandbox, 0777, true);
exec('tar -cf - --exclude=./.git --exclude=./node_modules --exclude=./datas --exclude=./settings -C '.escapeshellarg($root).' . | tar -xf - -C '.escapeshellarg($sandbox));
foreach (['settings', 'datas/cache', 'datas/logs', 'datas/repair'] as $dir) {
    @mkdir($sandbox.'/'.$dir, 0777, true);
}
$db = $dbConfig;
$db['mysql']['dbname'] = $testDb;
file_put_contents($sandbox.'/settings/database.php', '<'."?php\nreturn ".var_export($db, true).';');
copy($root.'/install/legacy/eoffice-6.1-config.php', $sandbox.'/settings/config.php');
exec('cd '.escapeshellarg($sandbox).' && php install/cli-upgrade.php admin@localhost admin --confirm-backup 2>&1', $out, $code);
echo $code === 0 ? "ปรับรุ่นฐานเดิมสำเร็จ\n" : "ปรับรุ่นล้มเหลว\n".implode("\n", $out)."\n";
$script = $sandbox.'/eoffice-cases.php';
file_put_contents($script, '<'.'?php require getenv("EDOC_BOOT"); require '.var_export(__DIR__.'/test-eoffice-cases.php', true).';');
passthru('EDOC_ROOT='.escapeshellarg($sandbox).' EDOC_BOOT='.escapeshellarg($sandbox.'/modules/edocument/tests/bootstrap.php')
    .' php '.escapeshellarg($script), $status);
exec('rm -rf '.escapeshellarg($sandbox));
$pdo = new PDO('mysql:host='.$dbConfig['mysql']['hostname'], $dbConfig['mysql']['username'], $dbConfig['mysql']['password']);
$pdo->exec("DROP DATABASE IF EXISTS `$testDb`");
exit($status);
