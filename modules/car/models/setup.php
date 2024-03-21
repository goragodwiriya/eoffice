<?php
/**
 * @filesource modules/car/models/setup.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Car\Setup;

use Gcms\Login;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=car-setup
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
        $select = array(
            'R.number',
            'R.id',
            'R.color'
        );
        $query = static::createQuery()
            ->from('vehicles R');
        $n = 1;
        foreach (Language::get('CAR_SELECT', []) as $type => $text) {
            $query->join('vehicles_meta M'.$n, 'LEFT', array(array('M'.$n.'.vehicle_id', 'R.id'), array('M'.$n.'.name', $type)));
            $select[] = 'M'.$n.'.value '.$type;
            ++$n;
        }
        $select[] = 'R.seats';
        $select[] = 'R.published';
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
        // session, referer, สามารถจัดการโมดูลได้, ไม่ใช่สมาชิกตัวอย่าง
        if ($request->initSession() && $request->isReferer() && $login = Login::isMember()) {
            if (Login::notDemoMode($login) && Login::checkPermission($login, 'can_manage_car')) {
                // รับค่าจากการ POST
                $action = $request->post('action')->toString();
                // id ที่ส่งมา
                if (preg_match_all('/,?([0-9]+),?/', $request->post('id')->toString(), $match)) {
                    // ตาราง
                    $table = $this->getTableName('vehicles');
                    if ($action === 'delete') {
                        // ลบ
                        $this->db()->delete($table, array('id', $match[1]), 0);
                        $this->db()->delete($this->getTableName('vehicles_meta'), array('vehicle_id', $match[1]), 0);
                        // log
                        \Index\Log\Model::add(0, 'car', 'Delete', '{LNG_Delete} {LNG_Vehicle} ID : '.implode(', ', $match[1]), $login['id']);
                        // reload
                        $ret['location'] = 'reload';
                    } elseif ($action === 'published') {
                        // สถานะการเผยแพร่
                        $search = $this->db()->first($table, (int) $match[1][0]);
                        if ($search) {
                            $published = $search->published == 1 ? 0 : 1;
                            $this->db()->update($table, $search->id, array('published' => $published));
                            $ret['title'] = Language::get('PUBLISHEDS', '', $published);
                            // log
                            \Index\Log\Model::add(0, 'car', 'Save', $ret['title'].' ID : '.implode(', ', $match[1]), $login['id']);
                            // คืนค่า
                            $ret['elem'] = 'published_'.$search->id;
                            $ret['class'] = 'icon-published'.$published;
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
