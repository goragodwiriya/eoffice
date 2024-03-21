<?php
/**
 * @filesource modules/car/models/vehicles.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Car\Vehicles;

use Gcms\Login;
use Kotchasan\Database\Sql;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=car-vehicles
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
    public static function toDataTable($params = ['published' => 1])
    {
        $where = array();
        if (!empty($params['published'])) {
            $where[] = array('R.published', 1);
        }
        $query = static::createQuery()
            ->from('vehicles R')
            ->order('R.number')
            ->cacheOn();
        $concat = array('R.number');
        $n = 1;
        foreach (Language::get('CAR_SELECT', []) as $key => $label) {
            $query->join('vehicles_meta M'.$n, 'LEFT', array(array('M'.$n.'.vehicle_id', 'R.id'), array('M'.$n.'.name', $key)));
            $query->join('category C'.$n, 'LEFT', array(array('C'.$n.'.type', $key), array('C'.$n.'.category_id', 'M'.$n.'.value')));
            $concat[] = 'C'.$n.'.`topic`';
            if (!empty($params[$key])) {
                $where[] = array('M'.$n.'.value', $params[$key]);
            }
            ++$n;
        }
        $query->where($where);
        if (!empty($params['id'])) {
            $query->orWhere(array('R.id', $params['id']));
        }
        return $query->select('R.id', Sql::CONCAT($concat, 'number', ' '), 'R.color', 'R.detail', 'R.seats');
    }

    /**
     * Query ยานพาหนะ ใส่ลงใน select
     *
     * @param bool $published
     * @param int $vehicle_id
     *
     * @return array
     */
    public static function toSelect($published = true, $vehicle_id = 0)
    {
        $params = [];
        if ($published) {
            $params['published'] = 1;
            if ($vehicle_id > 0) {
                $params['id'] = $vehicle_id;
            }
        }
        $result = [];
        foreach (static::toDataTable($params)->cacheOn()->execute() as $item) {
            $result[$item->id] = $item->number;
        }
        return $result;
    }

    /**
     * รับค่าจาก action (vehicles.php)
     *
     * @param Request $request
     */
    public function action(Request $request)
    {
        $ret = [];
        // session, referer, Ajax
        if ($request->initSession() && $request->isReferer() && $request->isAjax()) {
            $action = $request->post('action')->toString();
            if ($action === 'detail') {
                // แสดงรายละเอียด ยานพาหนะ
                $search = \Car\Write\Model::get($request->post('id')->toInt());
                if ($search) {
                    $ret['modal'] = \Car\Detail\View::create()->vehicle($search);
                }
            } elseif ($action === 'car') {
                $url = WEB_URL.'index.php?module=car-booking&vehicle_id='.$request->post('id')->toInt();
                if (Login::isMember()) {
                    // จอง ยานพาหนะ
                    $ret['location'] = $url;
                } else {
                    // login
                    $ret['alert'] = Language::get('Please log in to continue');
                    $ret['location'] = WEB_URL.'index.php?module=welcome&action=login&ret='.urlencode($url);
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
