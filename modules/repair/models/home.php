<?php
/**
 * @filesource modules/repair/models/home.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Repair\Home;

use Gcms\Login;
use Kotchasan\Database\Sql;

/**
 * โมเดลสำหรับอ่านข้อมูลแสดงในหน้า  Home
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * อ่านงานซ่อมใหม่วันนี้
     *
     * @return object
     */
    public static function getNew($login)
    {
        $where = array(
            array(Sql::DATE('S.create_date'), date('Y-m-d'))
        );
        // พนักงาน
        $isStaff = Login::checkPermission($login, array('can_manage_repair', 'can_repair'));
        if ($isStaff) {
            $status = isset(self::$cfg->repair_first_status) ? self::$cfg->repair_first_status : 1;
            $where[] = array('S.status', $status);
        } else {
            $where[] = array('R.customer_id', $login['id']);
        }
        $q1 = static::createQuery()
            ->select('repair_id', Sql::MAX('id', 'id'))
            ->from('repair_status')
            ->groupBy('repair_id');
        $query = static::createQuery()
            ->selectCount()
            ->from('repair_status S')
            ->join(array($q1, 'T'), 'INNER', array(array('T.repair_id', 'S.repair_id'), array('T.id', 'S.id')))
            ->where($where);
        if (!$isStaff) {
            $query->join('repair R', 'INNER', array('R.id', 'S.repair_id'));
        }
        $search = $query->toArray()
            ->execute();
        if (!empty($search)) {
            return (object) array(
                'isStaff' => $isStaff,
                'count' => $search[0]['count']
            );
        }
        return 0;
    }
}
