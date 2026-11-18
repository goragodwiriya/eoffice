<?php
/**
 * @filesource modules/repair/models/jobs.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Repair\Jobs;

use Kotchasan\Database\Sql;

/**
 * รายการงานซ่อม (สำหรับเจ้าหน้าที่และช่างซ่อม)
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * Query สถานะล่าสุดของแต่ละงานซ่อม
     *
     * @return \Kotchasan\QueryBuilder\QueryBuilderInterface
     */
    public static function latestStatusQuery()
    {
        return static::createQuery()
            ->select('repair_id', Sql::MAX('id', 'max_id'))
            ->from('repair_status')
            ->groupBy('repair_id');
    }

    /**
     * Query ข้อมูลสำหรับส่งให้กับ DataTable
     *
     * @param array $params
     *
     * @return \Kotchasan\QueryBuilder\QueryBuilderInterface
     */
    public static function toDataTable($params)
    {
        $where = [];
        if (is_array($params['operator_id'])) {
            $where[] = ['S.operator_id', array_map('intval', $params['operator_id'])];
        } elseif (!empty($params['operator_id'])) {
            $where[] = ['S.operator_id', (int) $params['operator_id']];
        }
        if (!empty($params['status'])) {
            $where[] = ['S.status', (int) $params['status']];
        }
        if (!empty($params['from'])) {
            $where[] = [Sql::DATE('R.created_at'), '>=', $params['from']];
        }
        if (!empty($params['to'])) {
            $where[] = [Sql::DATE('R.created_at'), '<=', $params['to']];
        }

        $query = static::createQuery()
            ->select(
                'R.id',
                'R.job_id',
                'U.name',
                'U.phone',
                'V.topic',
                'R.created_at',
                'S.operator_id',
                'S.status'
            )
            ->from('repair R')
            ->join([self::latestStatusQuery(), 'T'], [['T.repair_id', 'R.id']], 'LEFT')
            ->join('repair_status S', [['S.id', 'T.max_id']], 'LEFT')
            ->join('inventory_items I', [['I.product_no', 'R.product_no']], 'LEFT')
            ->join('inventory V', [['V.id', 'I.inventory_id']], 'LEFT')
            ->join('user U', [['U.id', 'R.customer_id']], 'LEFT')
            ->where($where);

        if (!empty($params['search'])) {
            $search = '%'.$params['search'].'%';
            $query->where([
                ['U.name', 'LIKE', $search],
                ['U.phone', 'LIKE', $search],
                ['R.job_id', 'LIKE', $search],
                ['V.topic', 'LIKE', $search]
            ], 'OR');
        }

        return $query;
    }

    /**
     * ลบงานซ่อมพร้อมประวัติการดำเนินการ
     *
     * @param array $ids
     *
     * @return int
     */
    public static function remove(array $ids)
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (empty($ids)) {
            return 0;
        }

        $db = \Kotchasan\DB::create();
        $count = $db->delete('repair', [['id', $ids]], 0);
        $db->delete('repair_status', [['repair_id', $ids]], 0);

        return $count;
    }
}
