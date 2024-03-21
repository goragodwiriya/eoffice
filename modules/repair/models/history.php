<?php
/**
 * @filesource modules/repair/models/history.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Repair\History;

use Gcms\Login;
use Kotchasan\Database\Sql;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=repair-history
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
     * @return \Kotchasan\Database\QueryBuilder
     */
    public static function toDataTable($params)
    {
        $where = array(
            array('R.customer_id', $params['customer_id'])
        );
        if ($params['status'] > -1) {
            $where[] = array('S.status', $params['status']);
        }
        $q1 = static::createQuery()
            ->select('repair_id', Sql::MAX('id', 'max_id'))
            ->from('repair_status')
            ->groupBy('repair_id');
        return static::createQuery()
            ->select('R.id', 'R.job_id', 'V.topic', 'R.create_date', 'S.operator_id', 'S.status', 'S.comment')
            ->from('repair R')
            ->join(array($q1, 'T'), 'LEFT', array('T.repair_id', 'R.id'))
            ->join('repair_status S', 'LEFT', array('S.id', 'T.max_id'))
            ->join('inventory V', 'LEFT', array('V.id', 'R.inventory_id'))
            ->where($where);
    }

    /**
     * รับค่าจาก action (history.php)
     *
     * @param Request $request
     */
    public function action(Request $request)
    {
        $ret = [];
        // session, referer, member, ไม่ใช่สมาชิกตัวอย่าง
        if ($request->initSession() && $request->isReferer() && $login = Login::isMember()) {
            if (Login::notDemoMode($login)) {
                // รับค่าจากการ POST
                $action = $request->post('action')->toString();
                // id ที่ส่งมา
                if (preg_match_all('/,?([0-9]+),?/', $request->post('id')->toString(), $match)) {
                    if ($action === 'status' && Login::checkPermission($login, array('can_manage_repair', 'can_repair'))) {
                        // อ่านข้อมูลรายการที่ต้องการ
                        $index = \Repair\Detail\Model::get($request->post('id')->toInt());
                        if ($index) {
                            $ret['modal'] = Language::trans(\Repair\Action\View::create()->render($index, $login));
                        }
                    }
                }
            }
        }
        if (empty($ret)) {
            $ret['alert'] = Language::get('Unable to complete the transaction');
        }
        // คืนค่า JSON
        echo json_encode($ret);
    }
}
