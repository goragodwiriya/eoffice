<?php
/**
 * @filesource modules/repair/models/receive.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Repair\Receive;

/**
 * ใบแจ้งซ่อม (เพิ่ม/แก้ไข)
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * อ่านใบแจ้งซ่อมที่เลือก
     * $id = 0 คือรายการใหม่ ไม่พบคืนค่า null
     *
     * @param int $id
     *
     * @return object|null
     */
    public static function get($id)
    {
        $id = (int) $id;

        if ($id === 0) {
            return (object) [
                'id' => 0,
                'product_no' => '',
                'topic' => '',
                'job_description' => '',
                'comment' => '',
                'customer_id' => 0
            ];
        }

        $result = static::createQuery()
            ->select('R.*', 'V.topic')
            ->from('repair R')
            ->join('inventory_items I', [['I.product_no', 'R.product_no']], 'LEFT')
            ->join('inventory V', [['V.id', 'I.inventory_id']], 'LEFT')
            ->where([['R.id', $id]])
            ->first();

        if ($result) {
            $result->job_description = \Kotchasan\Text::untextarea($result->job_description);
            $result->comment = '';
        }

        return $result ?: null;
    }

    /**
     * ค้นหาพัสดุจากเลขครุภัณฑ์ ไม่พบคืนค่า null
     *
     * @param string $product_no
     *
     * @return object|null
     */
    public static function findProduct($product_no, $memberId = 0)
    {
        if ($product_no === '') {
            return null;
        }

        $result = static::createQuery()
            ->select('I.product_no', 'V.topic')
            ->from('inventory_items I')
            ->join('inventory V', [['V.id', 'I.inventory_id']], 'LEFT')
            ->where([['I.product_no', $product_no]]);
        // E-Office : เครื่องที่คนอื่นถือครองอยู่ แจ้งซ่อมแทนไม่ได้ (เงื่อนไขเดียวกับ autocomplete)
        if ($memberId > 0 && class_exists('\\Inventory\\Holder\\Model')) {
            $result->whereRaw(\Inventory\Holder\Model::repairableSql($memberId));
        }
        $result = $result->first();

        return $result ?: null;
    }

    /**
     * บันทึกใบแจ้งซ่อมใหม่ คืนค่า ID
     * (ชื่อเมธอดต้องไม่ชนกับ Kotchasan\Model::create())
     *
     * @param array $repair
     * @param string $comment ข้อความของผู้แจ้ง
     * @param int $member_id
     *
     * @return int
     */
    public static function createJob($repair, $comment, $member_id)
    {
        $db = \Kotchasan\DB::create();
        $repair['created_at'] = date('Y-m-d H:i:s');
        $repair['customer_id'] = $member_id;
        // ค่าเริ่มต้นเดียวกับ E-Office เดิม (Gcms\Config: repair_job_no = JOB%04d, repair_prefix ว่าง)
        $repair['job_id'] = \Index\Number\Model::get(0, self::$cfg->repair_job_no ?? 'JOB%04d', 'repair', 'job_id', self::$cfg->repair_prefix ?? '');

        $id = $db->insert('repair', $repair);

        $db->insert('repair_status', [
            'repair_id' => $id,
            'member_id' => $member_id,
            'comment' => $comment,
            'status' => (int) self::$cfg->repair_first_status,
            'created_at' => $repair['created_at'],
            'operator_id' => 0,
            'cost' => 0
        ]);

        return $id;
    }

    /**
     * แก้ไขใบแจ้งซ่อม
     * (ชื่อเมธอดต้องไม่ชนกับ Kotchasan\Model::update())
     *
     * @param int $id
     * @param array $repair
     *
     * @return void
     */
    public static function updateJob($id, $repair)
    {
        \Kotchasan\DB::create()->update('repair', [['id', (int) $id]], $repair);
    }
}
