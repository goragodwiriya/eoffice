<?php
/**
 * @filesource modules/inventory/models/setup.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Inventory\Setup;

use Gcms\Login;
use Kotchasan\Database\Sql;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=inventory-setup
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
     * @return \Kotchasan\Database\QueryBuilder
     */
    public static function toDataTable()
    {
        $q1 = static::createQuery()
            ->select('stock_id', Sql::MAX('create_date', 'create_date'))
            ->from('inventory_user')
            ->where(Sql::ISNULL('return_date'))
            ->groupBy('stock_id');
        $q2 = static::createQuery()
            ->select('I.stock_id', 'I.member_id', 'I.create_date')
            ->from('inventory_user I')
            ->join(array($q1, 'N'), 'INNER', array(array('N.stock_id', 'I.stock_id'), array('N.create_date', 'I.create_date')));
        $select = array(
            'R.id',
            'S.product_no',
            'R.topic',
            'S.stock',
            'R.unit',
            'R.status',
            'I.member_id',
            'U.name device_user'
        );
        $query = static::createQuery()
            ->from('inventory R')
            ->join('inventory_stock S', 'LEFT', array('S.inventory_id', 'R.id'))
            ->join(array($q2, 'I'), 'LEFT', array('I.stock_id', 'S.id'))
            ->join('user U', 'LEFT', array('U.id', 'I.member_id'));
        $n = 1;
        foreach (Language::get('INVENTORY_CATEGORIES') as $type => $text) {
            $query->join('inventory_meta M'.$n, 'LEFT', array(array('M'.$n.'.inventory_id', 'R.id'), array('M'.$n.'.name', $type)));
            $select[] = 'M'.$n.'.value '.$type;
            ++$n;
        }
        return $query->select($select);
    }

    /**
     * รับค่าจาก action (setup.php)
     *
     * @param Request $request
     */
    public function action(Request $request)
    {
        $ret = [];
        // session, referer, can_manage_inventory, ไม่ใช่สมาชิกตัวอย่าง
        if ($request->initSession() && $request->isReferer() && $login = Login::isMember()) {
            if (Login::notDemoMode($login) && Login::checkPermission($login, 'can_manage_inventory')) {
                // รับค่าจากการ POST
                $action = $request->post('action')->toString();
                // Database
                $db = $this->db();
                // table
                $table = $this->getTableName('inventory');
                // id ที่ส่งมา
                if (preg_match_all('/,?([0-9]+),?/', $request->post('id')->filter('0-9,'), $match)) {
                    if ($action === 'delete') {
                        // ลบ
                        $table_stock = $this->getTableName('inventory_stock');
                        $stock = [];
                        foreach ($db->select($table_stock, array('inventory_id', $match[1])) as $item) {
                            $stock[] = $item['id'];
                        }
                        $db->delete($table, array('id', $match[1]), 0);
                        $db->delete($this->getTableName('inventory_meta'), array('inventory_id', $match[1]), 0);
                        $db->delete($this->getTableName('inventory_user'), array('stock_id', $stock), 0);
                        $db->delete($table_stock, array('inventory_id', $match[1]), 0);
                        // ลบรูปภาพ
                        $dir = ROOT_PATH.DATA_FOLDER.'inventory/';
                        foreach ($match[1] as $id) {
                            if (is_file($dir.$id.'.jpg')) {
                                unlink($dir.$id.'.jpg');
                            }
                        }
                        // log
                        \Index\Log\Model::add(0, 'inventory', 'Delete', '{LNG_Delete} {LNG_Inventory} ID : '.implode(', ', $match[1]), $login['id']);
                        // reload
                        $ret['location'] = 'reload';
                    } elseif ($action == 'status') {
                        // สถานะ
                        $search = $db->first($table, (int) $match[1][0]);
                        if ($search) {
                            $status = $search->status == 1 ? 0 : 1;
                            $db->update($table, $search->id, array('status' => $status));
                            // คืนค่า
                            $ret['elem'] = 'status_'.$search->id;
                            $ret['title'] = Language::get('INVENTORY_STATUS', '', $status);
                            $ret['class'] = 'icon-valid '.($status == '1' ? 'access' : 'disabled');
                            // log
                            \Index\Log\Model::add($search->id, 'inventory', 'Status', $ret['title'].' ID : '.$search->id, $login['id']);
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
