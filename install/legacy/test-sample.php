<?php
/**
 * install/legacy/test-sample.php — ทดสอบข้อมูลตัวอย่างของการติดตั้งใหม่ (end-to-end)
 *
 * สำเนาโปรเจ็คไป sandbox → install/cli-fresh.php ตัวจริง (สคีมา + ข้อมูลตัวอย่างของทุกโมดูล + ผู้ดูแล)
 * → เรียก Controller จริงของแต่ละโมดูลด้วยสมาชิกตัวอย่าง ตรวจว่าข้อมูลอ้างอิงกันถูกต้องและแสดงผลได้
 *
 * ใช้:  php install/legacy/test-sample.php        (ฐานทดสอบ eoffice_sample_test ลบทิ้งเมื่อจบ)
 */
if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}
$root = realpath(__DIR__.'/../..');
$testDb = 'eoffice_sample_test';
$dbConfig = include $root.'/settings/database.php';
if ($dbConfig['mysql']['dbname'] === $testDb) {
    exit("ชื่อฐานทดสอบซ้ำกับฐานจริง\n");
}
$sandbox = sys_get_temp_dir().'/eoffice-sample-'.getmypid();
exec('rm -rf '.escapeshellarg($sandbox));
mkdir($sandbox, 0777, true);
exec('tar -cf - --exclude=./.git --exclude=./node_modules --exclude=./datas --exclude=./settings -C '.escapeshellarg($root).' . | tar -xf - -C '.escapeshellarg($sandbox));
foreach (['settings', 'datas/cache', 'datas/logs'] as $dir) {
    @mkdir($sandbox.'/'.$dir, 0777, true);
}
$db = $dbConfig;
$db['mysql']['dbname'] = $testDb;
file_put_contents($sandbox.'/settings/database.php', '<'."?php\nreturn ".var_export($db, true).';');
exec('cd '.escapeshellarg($sandbox).' && php install/cli-fresh.php '.$testDb.' '.escapeshellarg($db['mysql']['prefix'])
    .' --config='.escapeshellarg($sandbox.'/settings/config.php').' 2>&1', $out, $code);
echo $code === 0 ? "ติดตั้งใหม่พร้อมข้อมูลตัวอย่างสำเร็จ\n" : "ติดตั้งใหม่ล้มเหลว\n".implode("\n", $out)."\n";
$script = $sandbox.'/eoffice-sample-cases.php';
file_put_contents($script, '<'.'?php require getenv("EDOC_BOOT"); require '.var_export(__DIR__.'/test-sample-cases.php', true).';');
passthru('EDOC_ROOT='.escapeshellarg($sandbox).' EDOC_BOOT='.escapeshellarg($sandbox.'/modules/edocument/tests/bootstrap.php')
    .' php '.escapeshellarg($script), $status);
exec('rm -rf '.escapeshellarg($sandbox));
$pdo = new PDO('mysql:host='.$dbConfig['mysql']['hostname'], $dbConfig['mysql']['username'], $dbConfig['mysql']['password']);
$pdo->exec("DROP DATABASE IF EXISTS `$testDb`");
exit($status);
