<?php
/**
 * @filesource modules/car/models/approve.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Car\Approve;

use Gcms\Login;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=car-approve
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * อ่านข้อมูลรายการที่เลือก
     * คืนค่าข้อมูล object ไม่พบคืนค่า null
     *
     * @param int $id ID
     *
     * @return object|null
     */
    public static function get($id)
    {
        $today = date('Y-m-d H:i:s');
        $query = static::createQuery()
            ->from('car_reservation R')
            ->join('user U', 'LEFT', array('U.id', 'R.member_id'))
            ->where(array('R.id', $id));
        $select = array(
            'R.*',
            'U.name',
            'U.phone',
            'U.username',
            'SQL((CASE WHEN "'.$today.'" BETWEEN R.`begin` AND R.`end` THEN 1 WHEN "'.$today.'" > R.`end` THEN 2 ELSE 0 END) AS `today`)',
            'SQL(TIMESTAMPDIFF(MINUTE,"'.$today.'",R.`begin`) AS `remain`)'
        );
        $n = 1;
        foreach (Language::get('CAR_OPTIONS', []) as $key => $label) {
            $query->join('car_reservation_data M'.$n, 'LEFT', array(array('M'.$n.'.reservation_id', 'R.id'), array('M'.$n.'.name', $key)));
            $select[] = 'M'.$n.'.value '.$key;
            ++$n;
        }
        return $query->first($select);
    }

    /**
     * บันทึกข้อมูลที่ส่งมาจากฟอร์ม (approve.php)
     *
     * @param Request $request
     */
    public function submit(Request $request)
    {
        $ret = [];
        // session, token, สมาชิก
        if ($request->initSession() && $request->isSafe() && $login = Login::isMember()) {
            try {
                // ตรวจสอบรายการที่เลือก
                $index = self::get($request->post('id')->toInt());
                // สามารถอนุมัติได้
                if ($index) {
                    // สามารถอนุมัติได้
                    $canApprove = \Car\Base\Controller::canApprove($login, $index);
                    if ($canApprove == -1) {
                        // ค่าที่ส่งมา
                        $save = array(
                            'vehicle_id' => $request->post('vehicle_id')->toInt(),
                            'travelers' => $request->post('travelers')->toInt(),
                            'detail' => $request->post('detail')->textarea(),
                            'comment' => $request->post('comment')->textarea()
                        );
                        $begin_date = $request->post('begin_date')->date();
                        $begin_time = $request->post('begin_time')->time();
                        $end_date = $request->post('end_date')->date();
                        $end_time = $request->post('end_time')->time();
                        if ($save['vehicle_id'] == 0) {
                            // ไม่ได้เลือก vehicle_id
                            $ret['ret_vehicle_id'] = Language::replace('Search :name and select from the list', array(':name' => 'Vehicle'));
                        }
                        if ($save['travelers'] == 0) {
                            // ไม่ได้กรอก travelers
                            $ret['ret_travelers'] = 'Please fill in';
                        }
                        if ($save['detail'] == '') {
                            // ไม่ได้กรอก detail
                            $ret['ret_detail'] = 'Please fill in';
                        }
                        if (empty($begin_date)) {
                            // ไม่ได้กรอก begin_date
                            $ret['ret_begin_date'] = 'Please fill in';
                        }
                        if (empty($begin_time)) {
                            // ไม่ได้กรอก begin_time
                            $ret['ret_begin_time'] = 'Please fill in';
                        }
                        if (empty($end_date)) {
                            // ไม่ได้กรอก end
                            $ret['ret_end_date'] = 'Please fill in';
                        }
                        if (empty($end_time)) {
                            // ไม่ได้กรอก end_time
                            $ret['ret_end_time'] = 'Please fill in';
                        }
                        if ($end_date.$end_time > $begin_date.$begin_time) {
                            $save['begin'] = $begin_date.' '.$begin_time.':01';
                            $save['end'] = $end_date.' '.$end_time.':00';
                        } else {
                            // วันที่ ไม่ถูกต้อง
                            $ret['ret_end_date'] = Language::get('End date must be greater than begin date');
                        }
                        $datas = [];
                        foreach (Language::get('CAR_OPTIONS', []) as $key => $label) {
                            $values = $request->post($key, [])->toInt();
                            if (!empty($values)) {
                                $datas[$key] = implode(',', $values);
                            }
                        }
                        if (empty($ret)) {
                            // Database
                            $db = $this->db();
                            // save
                            $db->update($this->getTableName('car_reservation'), $index->id, $save);
                            // รายละเอียดการจอง
                            $table = $this->getTableName('car_reservation_data');
                            $db->delete($table, array('reservation_id', $index->id), 0);
                            foreach ($datas as $key => $value) {
                                if ($value != '') {
                                    $db->insert($table, array(
                                        'reservation_id' => $index->id,
                                        'name' => $key,
                                        'value' => $value
                                    ));
                                }
                                $save[$key] = $value;
                            }
                            // คืนค่า
                            $ret['alert'] = Language::get('Saved successfully');
                            // สถานะการจอง
                            $status = \Car\Tools\View::toStatus((array) $index, false);
                            // log
                            \Index\Log\Model::add($index->id, 'car', 'Status', $status, $login['id']);
                            // location
                            $ret['location'] = $request->getUri()->postBack('index.php', array('module' => 'car-report', 'status' => $index->status));
                            // เคลียร์
                            $request->removeToken();
                        }
                    }
                }
            } catch (\Kotchasan\InputItemException $e) {
                $ret['alert'] = $e->getMessage();
            }
        }
        if (empty($ret)) {
            $ret['alert'] = Language::get('Unable to complete the transaction');
        }
        // คืนค่าเป็น JSON
        echo json_encode($ret);
    }
}
