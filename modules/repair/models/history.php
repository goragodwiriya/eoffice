<?php
/**
 * @filesource modules/repair/models/history.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Repair\History;

/**
 * ประวัติการแจ้งซ่อมของสมาชิก
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * Query ข้อมูลสำหรับส่งให้กับ DataTable
     *
     * @param array $params
     *
     * @return \Kotchasan\QueryBuilder\QueryBuilderInterface
     */
    public static function toDataTable($params)
    {
        $where = [
            ['R.customer_id', (int) $params['customer_id']]
        ];
        if (!empty($params['status'])) {
            $where[] = ['S.status', (int) $params['status']];
        }

        $query = static::createQuery()
            ->select(
                'R.id',
                'R.job_id',
                'V.topic',
                'R.created_at',
                'S.operator_id',
                'S.status',
                'S.comment'
            )
            ->from('repair R')
            ->join([\Repair\Jobs\Model::latestStatusQuery(), 'T'], [['T.repair_id', 'R.id']], 'LEFT')
            ->join('repair_status S', [['S.id', 'T.max_id']], 'LEFT')
            ->join('inventory_items I', [['I.product_no', 'R.product_no']], 'LEFT')
            ->join('inventory V', [['V.id', 'I.inventory_id']], 'LEFT')
            ->where($where);

        if (!empty($params['search'])) {
            $search = '%'.$params['search'].'%';
            $query->where([
                ['R.job_id', 'LIKE', $search],
                ['V.topic', 'LIKE', $search]
            ], 'OR');
        }

        return $query;
    }
}
