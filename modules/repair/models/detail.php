<?php
/**
 * @filesource modules/repair/models/detail.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Repair\Detail;

/**
 * รายละเอียดงานซ่อมและประวัติการดำเนินการ
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * อ่านรายละเอียดงานซ่อมพร้อมสถานะล่าสุด ไม่พบคืนค่า null
     *
     * @param int $id
     *
     * @return object|null
     */
    public static function get($id)
    {
        $result = static::createQuery()
            ->select(
                'R.*',
                'U.name',
                'U.phone',
                'V.topic',
                'S.status',
                'S.comment',
                'S.operator_id',
                'S.id status_id'
            )
            ->from('repair R')
            ->join([\Repair\Jobs\Model::latestStatusQuery(), 'T'], [['T.repair_id', 'R.id']], 'LEFT')
            ->join('repair_status S', [['S.id', 'T.max_id']], 'LEFT')
            ->join('inventory_items I', [['I.product_no', 'R.product_no']], 'LEFT')
            ->join('inventory V', [['V.id', 'I.inventory_id']], 'LEFT')
            ->join('user U', [['U.id', 'R.customer_id']], 'LEFT')
            ->where([['R.id', (int) $id]])
            ->first();

        if ($result) {
            $result->job_description = \Kotchasan\Text::untextarea($result->job_description);
        }

        return $result ?: null;
    }

    /**
     * อ่านประวัติการดำเนินการทั้งหมดของงานซ่อม
     *
     * @param int $id
     *
     * @return array
     */
    public static function getAllStatus($id)
    {
        return static::createQuery()
            ->select('S.id', 'U.name', 'S.status', 'S.created_at', 'S.comment', 'S.cost')
            ->from('repair_status S')
            ->join('user U', [['U.id', 'S.operator_id']], 'LEFT')
            ->where([['S.repair_id', (int) $id]])
            ->orderBy('S.id')
            ->fetchAll(true);
    }

    /**
     * เพิ่มการดำเนินการใหม่ คืนค่า ID
     *
     * @param array $save
     *
     * @return int
     */
    public static function addStatus($save)
    {
        return \Kotchasan\DB::create()->insert('repair_status', $save);
    }

    /**
     * ลบการดำเนินการที่เลือก
     *
     * @param array $ids ID ของ repair_status
     *
     * @return int จำนวนรายการที่ลบ
     */
    public static function removeStatus(array $ids)
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (empty($ids)) {
            return 0;
        }

        return \Kotchasan\DB::create()->delete('repair_status', [['id', $ids]], 0);
    }
}
