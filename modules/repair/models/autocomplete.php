<?php
/**
 * @filesource modules/repair/models/autocomplete.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Repair\Autocomplete;

use Gcms\Login;
use Kotchasan\Database\Sql;
use Kotchasan\Http\Request;

/**
 * ค้นหา สำหรับ autocomplete
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * ค้นหา Inventory สำหรับ autocomplete
     * เฉพาะรายการที่ตัวเองรับผิดชอบ และ ที่ไม่มีผู้รับผิดชอบ
     * คืนค่าเป็น JSON
     *
     * @param Request $request
     */
    public function find(Request $request)
    {
        if ($request->initSession() && $request->isReferer() && $login = Login::isMember()) {
            try {
                // ข้อมูลที่ส่งมา
                if ($request->post('topic')->exists()) {
                    $search = $request->post('topic')->topic();
                    $order = 'V.topic';
                } elseif ($request->post('product_no')->exists()) {
                    $search = $request->post('product_no')->topic();
                    $order = 'S.product_no';
                }
                if (!empty($search)) {
                    $where = array($order, 'LIKE', "%$search%");
                    // query
                    $query = $this->db()->createQuery()
                        ->select('S.inventory_id', 'V.topic', 'S.product_no')
                        ->from('inventory V')
                        ->join('inventory_stock S', 'INNER', array('S.inventory_id', 'V.id'))
                        ->join('inventory_user U', 'LEFT', array('U.stock_id', 'S.id'))
                        ->where($where)
                        ->andWhere(array(
                            array('member_id', $login['id']),
                            Sql::ISNULL('U.id')
                        ), 'OR')
                        ->limit($request->post('count', 20)->toInt())
                        ->toArray();
                    if (isset($order)) {
                        $query->order($order);
                    }
                    $result = $query->execute();
                    if (!empty($result)) {
                        // คืนค่า JSON
                        echo json_encode($result);
                    }
                }
            } catch (\Kotchasan\InputItemException $e) {
            }
        }
    }
}
