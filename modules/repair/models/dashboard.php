<?php
/**
 * @filesource modules/repair/models/dashboard.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Repair\Dashboard;

use Gcms\Api as ApiController;
use Kotchasan\Database\Sql;

/**
 * ข้อมูลสรุปงานซ่อมสำหรับหน้าแรก
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * นับงานซ่อมของวันนี้
     * เจ้าหน้าที่นับจากสถานะเริ่มต้น สมาชิกนับเฉพาะงานของตัวเอง
     *
     * @param object $login
     *
     * @return array
     */
    public static function summary($login)
    {
        $isStaff = ApiController::hasPermission($login, ['can_manage_repair', 'can_repair']);

        $where = [
            [Sql::DATE('S.created_at'), date('Y-m-d')]
        ];
        if ($isStaff) {
            $where[] = ['S.status', (int) self::$cfg->repair_first_status];
        } else {
            $where[] = ['R.customer_id', (int) $login->id];
        }

        $query = static::createQuery()
            ->selectCount()
            ->from('repair_status S')
            ->join([\Repair\Jobs\Model::latestStatusQuery(), 'T'], [['T.repair_id', 'S.repair_id'], ['T.max_id', 'S.id']], 'INNER');

        if (!$isStaff) {
            $query->join('repair R', [['R.id', 'S.repair_id']], 'INNER');
        }

        $today = $query->where($where)->first();

        return [
            'is_staff' => $isStaff ? 1 : 0,
            'today' => $today ? (int) $today->count : 0,
            'total' => self::totalOpen($login, $isStaff),
            'url' => $isStaff ? '/repair-jobs' : '/repair-history'
        ];
    }

    /**
     * นับงานซ่อมที่ยังไม่ปิด (สถานะที่ยังเปิดใช้งานอยู่ ไม่รวมสถานะสุดท้าย)
     *
     * @param object $login
     * @param bool $isStaff
     *
     * @return int
     */
    protected static function totalOpen($login, $isStaff)
    {
        $where = [];
        if (!$isStaff) {
            $where[] = ['R.customer_id', (int) $login->id];
        }

        $query = static::createQuery()
            ->selectCount()
            ->from('repair R')
            ->join([\Repair\Jobs\Model::latestStatusQuery(), 'T'], [['T.repair_id', 'R.id']], 'LEFT')
            ->join('repair_status S', [['S.id', 'T.max_id']], 'LEFT')
            ->where($where);

        $result = $query->first();

        return $result ? (int) $result->count : 0;
    }
}
