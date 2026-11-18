<?php
/**
 * @filesource modules/personnel/models/person.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Personnel\Person;

use Kotchasan\Language;

/**
 * ข้อมูลบุคลากรหนึ่งคน (personnel + user)
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * อ่านบุคลากรตาม id (0 = รายการใหม่)
     *
     * @param int $id
     *
     * @return object|null
     */
    public static function get($id)
    {
        $id = (int) $id;
        if ($id === 0) {
            $item = (object) [
                'id' => 0,
                'name' => '',
                'employee_code' => '',
                'phone' => '',
                'order' => 0,
                'active' => 1,
                'permission' => '',
                'custom' => []
            ];
            foreach (\Personnel\Category\Model::items() as $type => $label) {
                $item->$type = 0;
            }

            return $item;
        }
        $item = static::createQuery()
            ->select('P.*', 'U.name', 'U.phone', 'U.sex', 'U.permission', 'U.active', 'U.status', 'U.username')
            ->from('personnel P')
            ->join('user U', [['U.id', 'P.id']], 'INNER')
            ->where([['P.id', $id]])
            ->first();
        if ($item) {
            $item->custom = self::decodeCustom($item->custom);
        }

        return $item;
    }

    /**
     * ข้อมูลเพิ่มเติม (JSON) เป็น array
     * ระบบเดิมเก็บเป็น PHP serialize — ตัวปรับรุ่นแปลงให้แล้ว แต่ยังอ่านได้เผื่อแถวที่หลงเหลือ
     *
     * @param string|null $custom
     *
     * @return array
     */
    public static function decodeCustom($custom)
    {
        if ($custom === null || $custom === '') {
            return [];
        }
        $data = json_decode($custom, true);
        if (!is_array($data) && strpos($custom, 'a:') === 0) {
            $data = @unserialize($custom, ['allowed_classes' => false]);
        }

        return is_array($data) ? $data : [];
    }

    /**
     * ช่องข้อมูลเพิ่มเติมของบุคลากร (คีย์ภาษา PERSONNEL_DETAILS) [key => label]
     *
     * @return array
     */
    public static function details()
    {
        $details = Language::get('PERSONNEL_DETAILS', []);

        return is_array($details) ? $details : [];
    }

    /**
     * รหัสพนักงาน: ตัวอักษร ตัวเลข - _ / . ไม่เกิน 20 ตัว
     *
     * @param string $code
     *
     * @return string
     */
    public static function normalizeEmployeeCode($code)
    {
        return mb_substr(preg_replace('/[^A-Za-z0-9\-_\/.]/', '', (string) $code), 0, 20);
    }

    /**
     * รหัสพนักงานนี้มีบุคลากรคนอื่นใช้อยู่แล้วหรือไม่ (ว่าง = ไม่ตรวจ)
     *
     * @param int    $id   บุคลากรที่กำลังแก้ไข (0 = ใหม่)
     * @param string $code
     *
     * @return bool
     */
    public static function employeeCodeExists($id, $code)
    {
        if ($code === '') {
            return false;
        }
        $search = \Kotchasan\DB::create()->first('personnel', [['employee_code', $code]], ['id']);

        return $search && (int) $search->id !== (int) $id;
    }

    /**
     * รูปของบุคลากร
     *
     * @param int $id
     *
     * @return string|null URL ของรูป หรือ null ถ้าไม่มี
     */
    public static function picture($id)
    {
        $file = DATA_FOLDER.'personnel/'.(int) $id.self::$cfg->stored_img_type;

        return is_file(ROOT_PATH.$file) ? WEB_URL.$file : null;
    }

    /**
     * ลบบุคลากร (ทั้ง personnel, user และรูป) ยกเว้นผู้ดูแลระบบสูงสุด id 1
     *
     * @param array $ids
     *
     * @return array id ที่ลบจริง
     */
    public static function remove(array $ids)
    {
        $ids = array_values(array_filter(array_map('intval', $ids), fn($id) => $id > 1));
        if (empty($ids)) {
            return [];
        }
        $db = \Kotchasan\DB::create();
        $exists = [];
        foreach ($db->select('personnel', [['id', $ids]], [], ['id']) as $item) {
            $exists[] = (int) $item->id;
        }
        if (empty($exists)) {
            return [];
        }
        foreach ($exists as $id) {
            $file = ROOT_PATH.DATA_FOLDER.'personnel/'.$id.self::$cfg->stored_img_type;
            if (is_file($file)) {
                @unlink($file);
            }
        }
        $db->delete('personnel', [['id', $exists]], 0);
        $db->delete('user_meta', [['member_id', $exists]], 0);
        $db->delete('user', [['id', $exists]], 0);
        foreach ($exists as $id) {
            \Index\Auth\Model::logoutAllSessions($id);
        }

        return $exists;
    }

    /**
     * E-Office : บุคลากร = สมาชิกที่มีสถานะอยู่ใน personnel_status
     * สมาชิกที่เพิ่มจากหน้าสมาชิก (ไม่ได้เพิ่มผ่านหน้าบุคลากร) ต้องขึ้นในรายชื่อเอง
     * จึงสร้างแถวบุคลากรที่ยังไม่มีให้ก่อนแสดงรายการ แผนกตั้งต้นมาจากแผนกของสมาชิก
     */
    public static function syncFromStatus()
    {
        $statuses = array_values(array_unique(array_map('intval', (array) (self::$cfg->personnel_status ?? []))));
        if (empty($statuses)) {
            return;
        }
        $db = \Kotchasan\DB::create();
        $personnel = $db->getTableName('personnel');
        $user = $db->getTableName('user');
        $meta = $db->getTableName('user_meta');
        $db->raw(
            'INSERT INTO `'.$personnel.'` (`id`, `position`, `department`, `order`, `custom`, `employee_code`)'
            .' SELECT U.`id`, 0, IFNULL((SELECT MIN(CAST(M.`value` AS UNSIGNED)) FROM `'.$meta.'` M'
            .' WHERE M.`member_id` = U.`id` AND M.`name` = \'department\'), 0), 0, NULL, NULL'
            .' FROM `'.$user.'` U WHERE U.`status` IN ('.implode(',', $statuses).')'
            .' AND NOT EXISTS (SELECT 1 FROM `'.$personnel.'` P WHERE P.`id` = U.`id`)'
        );
    }

    /**
     * แผนกของบุคลากรคือแผนกของสมาชิก (user_meta department) ที่ระบบจองและสารบรรณใช้
     * เขียนคู่กันทุกครั้งที่บันทึกข้อมูลบุคลากร
     *
     * @param int $id
     * @param int $department 0 = ไม่ระบุ
     */
    public static function syncDepartment($id, $department)
    {
        $db = \Kotchasan\DB::create();
        $db->delete('user_meta', [['member_id', (int) $id], ['name', 'department']], 0);
        if ((int) $department > 0) {
            $db->insert('user_meta', [
                'member_id' => (int) $id,
                'name' => 'department',
                'value' => (string) (int) $department
            ]);
        }
    }
}
