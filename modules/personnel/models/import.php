<?php
/**
 * @filesource modules/personnel/models/import.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Personnel\Import;

use Kotchasan\Language;
use Kotchasan\Text;

/**
 * นำเข้ารายชื่อบุคลากรจากไฟล์ CSV
 *
 * หัวคอลัมน์และกติกาเหมือนระบบเดิม
 *   ชื่อ *  ต้องกรอก
 *   เลขประจำตัวประชาชน ** ห้ามซ้ำ (ไม่มีเลขประชาชนจะตรวจซ้ำจากชื่อแทน)
 *   หมวดหมู่ (ตำแหน่ง แผนก) ชั้น ห้อง กรอกเป็น ID
 *   กรอกเลขประชาชนและวันเกิดครบ = เข้าระบบได้ (ชื่อผู้ใช้ = เลขประชาชน รหัสผ่าน = วันเกิด)
 *   รายการที่ซ้ำหรือไม่ถูกต้องจะถูกข้าม นำเข้าซ้ำได้ ระบบข้ามรายการที่เคยนำเข้าแล้ว
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\KBase
{
    /**
     * @var \Kotchasan\DB
     */
    private $db;

    /**
     * @var array
     */
    private $header = [];

    /**
     * @var array
     */
    private $categories = [];

    /**
     * @var array
     */
    private $details = [];

    /**
     * จำนวนที่นำเข้าสำเร็จ
     *
     * @var int
     */
    public $row = 0;

    /**
     * จำนวนที่ไม่ได้เก็บเบอร์โทรเพราะซ้ำกับสมาชิกคนอื่น
     *
     * @var int
     */
    public $phoneSkipped = 0;

    /**
     * จำนวนรายการเดิมที่ถูกอัปเดตจากไฟล์
     *
     * @var int
     */
    public $updated = 0;

    /**
     * @var \Personnel\Category\Model
     */
    private $category;

    /**
     * หัวคอลัมน์ของไฟล์นำเข้า (ใช้ทั้งไฟล์ตัวอย่างและตอนอ่าน)
     *
     * @return array
     */
    public static function header()
    {
        $header = [
            Language::trans('{LNG_Full Name} *'),
            Language::trans('{LNG_Employee ID} **'),
            Language::get('Phone')
        ];
        foreach (\Personnel\Category\Model::items() as $label) {
            $header[] = $label;
        }
        foreach (\Personnel\Person\Model::details() as $label) {
            $header[] = $label;
        }

        return $header;
    }

    /**
     * ชุดอักขระของไฟล์ที่ส่งออก — ไซต์ที่ยังไม่เคยตั้ง csv_language ได้ UTF-8
     * (ค่าว่างทำให้ iconv แปลงทุกช่องเป็นค่าว่าง ไฟล์ที่ได้จึงมีแต่จุลภาค)
     *
     * @return string UTF-8 | TIS-620
     */
    public static function writeCharset()
    {
        $charset = strtoupper((string) (self::$cfg->csv_language ?? ''));

        return $charset === 'TIS-620' ? 'TIS-620' : 'UTF-8';
    }

    /**
     * ชุดอักขระของไฟล์ที่นำเข้า — ไม่ได้ตั้งไว้ให้ตรวจจากไฟล์เอง (AUTO)
     *
     * @return string
     */
    public static function readCharset()
    {
        $charset = strtoupper((string) (self::$cfg->csv_language ?? ''));

        return in_array($charset, ['UTF-8', 'TIS-620'], true) ? $charset : 'AUTO';
    }

    /**
     * ข้อมูลสำหรับไฟล์ personnel.csv — บุคลากรปัจจุบันทั้งหมดในรูปแบบเดียวกับที่นำเข้า
     * แก้ไขแล้วนำเข้ากลับได้ทันที ถ้ายังไม่มีบุคลากรเลยได้แถวตัวอย่าง 2 แถว
     * หมวด (ตำแหน่ง/แผนก) ส่งออกเป็นชื่อ ตอนนำเข้ารับได้ทั้งชื่อและรหัส
     *
     * @return array
     */
    public static function export()
    {
        $category = \Personnel\Category\Model::init();
        $types = array_keys(\Personnel\Category\Model::items());
        $details = \Personnel\Person\Model::details();
        $query = \Kotchasan\Model::createQuery()
            ->select('P.id', 'U.name', 'P.employee_code', 'U.phone', 'P.custom', ...array_map(function ($type) {
                return 'P.'.$type;
            }, $types))
            ->from('personnel P')
            ->join('user U', [['U.id', 'P.id']], 'INNER')
            ->orderBy('P.position')
            ->orderBy('P.order')
            ->orderBy('P.id');
        $rows = [];
        foreach ($query->fetchAll() as $item) {
            $row = [$item->name, (string) $item->employee_code, (string) $item->phone];
            foreach ($types as $type) {
                $row[] = $category->get($type, $item->$type);
            }
            $custom = \Personnel\Person\Model::decodeCustom($item->custom);
            foreach (array_keys($details) as $key) {
                $row[] = isset($custom[$key]) ? (string) $custom[$key] : '';
            }
            $rows[] = $row;
        }

        return empty($rows) ? self::sample($category) : $rows;
    }

    /**
     * แถวตัวอย่าง (ยังไม่มีบุคลากร) — หมวดใช้ชื่อหมวดแรกที่มีอยู่จริง
     *
     * @param \Personnel\Category\Model $category
     *
     * @return array
     */
    public static function sample($category = null)
    {
        $category = $category ?: \Personnel\Category\Model::init();
        $rows = [
            ['นายสมชาย โนนกระโทก', 'EMP101', '0812345678'],
            ['นางสมศรี รักงานดี', 'EMP102', '0898765432']
        ];
        foreach (array_keys(\Personnel\Category\Model::items()) as $type) {
            $first = $category->getFirstKey($type);
            $rows[0][] = $first === null ? '' : $category->get($type, $first);
            $rows[1][] = $first === null ? '' : $category->get($type, $first);
        }
        foreach (\Personnel\Person\Model::details() as $label) {
            $rows[0][] = '';
            $rows[1][] = '';
        }

        return $rows;
    }

    /**
     * นำเข้าไฟล์
     *
     * @param string $file
     * @param string $charset
     *
     * @return static
     */
    public static function import($file, $charset)
    {
        $obj = new static();
        $obj->db = \Kotchasan\DB::create();
        $obj->header = self::header();
        $obj->categories = \Personnel\Category\Model::items();
        $obj->category = \Personnel\Category\Model::init();
        $obj->details = \Personnel\Person\Model::details();
        \Kotchasan\Csv::read($file, [$obj, 'importRow'], $obj->header, $charset);

        return $obj;
    }

    /**
     * รหัสหมวดจากค่าในไฟล์ — รับได้ทั้งรหัส (1) และชื่อ (ผู้อำนวยการ) ไม่รู้จัก = 0
     *
     * @param string $type
     * @param string $value
     *
     * @return int
     */
    private function categoryId($type, $value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 0;
        }
        $items = $this->category->toArray($type);
        if (ctype_digit($value) && isset($items[(int) $value])) {
            return (int) $value;
        }
        foreach ($items as $id => $topic) {
            if (mb_strtolower(trim($topic)) === mb_strtolower($value)) {
                return (int) $id;
            }
        }

        return 0;
    }

    /**
     * นำเข้า 1 แถว
     * บุคลากรที่มีอยู่แล้ว (รหัสพนักงานตรงกัน หรือชื่อตรงกันเมื่อแถวนั้นไม่มีรหัส) จะถูกอัปเดต
     * ช่องที่เว้นว่างในไฟล์ = คงค่าเดิมไว้ · คนใหม่สร้างบัญชีที่ยังเข้าระบบไม่ได้
     *
     * @param array $data
     *
     * @return void
     */
    public function importRow($data)
    {
        $name = Text::topic($data[$this->header[0]] ?? '');
        if ($name === '') {
            return;
        }
        $code = \Personnel\Person\Model::normalizeEmployeeCode($data[$this->header[1]] ?? '');
        $phone = \Personnel\Account\Model::digits($data[$this->header[2]] ?? '');
        $personnel = [];
        foreach ($this->categories as $type => $label) {
            $id = $this->categoryId($type, $data[$label] ?? '');
            if ($id > 0) {
                $personnel[$type] = $id;
            }
        }
        $custom = [];
        foreach ($this->details as $key => $label) {
            $value = Text::topic($data[$label] ?? '');
            if ($value !== '') {
                $custom[$key] = $value;
            }
        }

        // บุคลากรเดิม : รหัสพนักงานก่อน ถ้าแถวนี้ไม่มีรหัสค่อยหาจากชื่อ
        $query = \Kotchasan\Model::createQuery()
            ->select('P.id', 'P.custom', 'P.employee_code')
            ->from('personnel P')
            ->join('user U', [['U.id', 'P.id']], 'INNER');
        if ($code !== '') {
            $query->where([['P.employee_code', $code]]);
        } else {
            $query->where([['U.name', $name]]);
        }
        $index = $query->orderBy('P.id')->first();
        if (!$index && $code !== '') {
            // ไฟล์ใส่รหัสให้คนเดิมที่ยังไม่มีรหัส
            $index = \Kotchasan\Model::createQuery()
                ->select('P.id', 'P.custom', 'P.employee_code')
                ->from('personnel P')
                ->join('user U', [['U.id', 'P.id']], 'INNER')
                ->where([['U.name', $name], ['P.employee_code', null]])
                ->orderBy('P.id')
                ->first();
            if ($index) {
                $personnel['employee_code'] = $code;
            }
        }

        if ($index) {
            $user = ['name' => $name];
            if ($phone !== '') {
                if (\Personnel\Account\Model::isUnique('phone', $phone, (int) $index->id)) {
                    $user['phone'] = $phone;
                } else {
                    ++$this->phoneSkipped;
                }
            }
            if (!empty($custom)) {
                $personnel['custom'] = json_encode($custom + \Personnel\Person\Model::decodeCustom($index->custom), JSON_UNESCAPED_UNICODE);
            }
            \Personnel\Account\Model::update($this->db, (int) $index->id, $user);
            if (!empty($personnel)) {
                $this->db->update('personnel', [['id', (int) $index->id]], $personnel);
            }
            if (isset($personnel['department'])) {
                \Personnel\Person\Model::syncDepartment((int) $index->id, $personnel['department']);
            }
            ++$this->updated;

            return;
        }

        $user = ['name' => $name, 'phone' => $phone];
        if (!\Personnel\Account\Model::isUnique('phone', $user['phone'])) {
            // เบอร์โทรในตารางสมาชิกห้ามซ้ำ นำเข้าคนนี้โดยไม่เก็บเบอร์โทร
            $user['phone'] = '';
            ++$this->phoneSkipped;
        }
        $user['status'] = (int) self::$cfg->teacher_status;
        // ไม่สร้างชื่อผู้ใช้/รหัสผ่านจากข้อมูลส่วนตัว — ผู้ดูแลตั้งการเข้าระบบเองที่หน้าสมาชิก
        $id = \Personnel\Account\Model::create($this->db, $user);
        $this->db->insert('personnel', $personnel + [
            'id' => $id,
            'position' => 0,
            'department' => 0,
            'order' => 0,
            'custom' => empty($custom) ? null : json_encode($custom, JSON_UNESCAPED_UNICODE),
            'employee_code' => $code === '' ? null : $code
        ]);
        if (!empty($personnel['department'])) {
            \Personnel\Person\Model::syncDepartment($id, $personnel['department']);
        }
        ++$this->row;
    }
}
