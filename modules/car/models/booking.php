<?php
/**
 * @filesource modules/car/models/booking.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Car\Booking;

use Gcms\Login;
use Kotchasan\Database\Sql;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=car-booking
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * อ่านข้อมูลรายการที่เลือก
     * ถ้า $id = 0 หมายถึงรายการใหม่
     * คืนค่าข้อมูล object ไม่พบคืนค่า null
     *
     * @param int   $id
     * @param int   $vehicle_id
     * @param array $login
     *
     * @return object|null
     */
    public static function get($id, $vehicle_id, $login)
    {
        if ($login) {
            if (empty($id)) {
                // ใหม่
                $booking = array(
                    'id' => 0,
                    'vehicle_id' => 0,
                    // สถานะอนุมัติ (เริ่มต้น)
                    'status' => 1,
                    'approve' => 1,
                    'closed' => 1,
                    // ไม่ใช่วันนี้
                    'today' => 0,
                    'name' => $login['name'],
                    'member_id' => $login['id'],
                    'phone' => isset($login['phone']) ? $login['phone'] : '',
                    'department' => empty($login['department']) ? '' : $login['department'][0]
                );
                if ($vehicle_id > 0) {
                    $vehicle = static::createQuery()
                        ->from('vehicles')
                        ->where(array('id', $vehicle_id))
                        ->first('id');
                    if ($vehicle) {
                        $booking['vehicle_id'] = $vehicle->id;
                    }
                }
                $approve_level = count(self::$cfg->car_approve_status);
                if ($approve_level > 0) {
                    // อนุมัติลำดับที่ 1
                    $booking['status'] = 0;
                    $booking['closed'] = $approve_level;
                }
                return (object) $booking;
            } else {
                // แก้ไข อ่านรายการที่เลือก
                $today = Sql::create('(CASE WHEN NOW() BETWEEN V.`begin` AND V.`end` THEN 1 WHEN NOW() > V.`end` THEN 2 ELSE 0 END) AS `today`');
                $query = static::createQuery()
                    ->from('car_reservation V')
                    ->join('user U', 'LEFT', array('U.id', 'V.member_id'))
                    ->where(array('V.id', $id));
                $select = array('V.*', 'U.name', 'U.phone', $today);
                $n = 1;
                foreach (Language::get('CAR_OPTIONS', []) as $key => $label) {
                    $query->join('car_reservation_data M'.$n, 'LEFT', array(array('M'.$n.'.reservation_id', 'V.id'), array('M'.$n.'.name', $key)));
                    $select[] = 'M'.$n.'.value '.$key;
                    ++$n;
                }
                return $query->first($select);
            }
        }
        // ไม่ได้เข้าระบบ
        return null;
    }

    /**
     * บันทึกข้อมูลที่ส่งมาจากฟอร์ม (booking.php)
     *
     * @param Request $request
     */
    public function submit(Request $request)
    {
        $ret = [];
        // session, token, สมาชิก
        if ($request->initSession() && $request->isSafe() && $login = Login::isMember()) {
            try {
                // ค่าที่ส่งมา
                $save = array(
                    'vehicle_id' => $request->post('vehicle_id')->toInt(),
                    'travelers' => $request->post('travelers')->toInt(),
                    'detail' => $request->post('detail')->textarea(),
                    'chauffeur' => $request->post('chauffeur')->toInt(),
                    'comment' => $request->post('comment')->textarea()
                );
                $begin_date = $request->post('begin_date')->date();
                $begin_time = $request->post('begin_time')->time();
                $end_date = $request->post('end_date')->date();
                $end_time = $request->post('end_time')->time();
                $user = array(
                    'phone' => $request->post('phone')->topic()
                );
                // ตรวจสอบรายการที่เลือก
                $index = self::get($request->post('id')->toInt(), 0, $login);
                if ($index && (
                    // เจ้าของ และ
                    $login['id'] == $index->member_id && (
                        // ใหม่ หรือ
                        $index->id == 0 || (
                            // ยังไม่ได้อนุมัติ และ
                            $index->status == 0 && $index->approve == 1 &&
                            // ไม่ใช่วันนี้
                            $index->today == 0
                        )))) {
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
                    if (empty($login['department'])) {
                        // สมาชิกไม่สังกัดแผนก
                        if (in_array('department', self::$cfg->categories_disabled)) {
                            $ret['error'] = Language::get('You are not affiliated with a department. Please contact the administrator.');
                        } else {
                            $ret['error'] = Language::get('You are not affiliated with a department. Please select a department first.');
                        }
                    }
                    if ($user['phone'] == '') {
                        // ไม่ได้กรอก phone
                        $ret['ret_phone'] = 'Please fill in';
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
                        // ตรวจสอบว่าง
                        if (!\Car\Checker\Model::availability($save)) {
                            $ret['ret_begin_date'] = Language::get('Booking are not available at select time');
                        }
                    } else {
                        // วันที่ ไม่ถูกต้อง
                        $ret['ret_end_date'] = Language::get('End date must be greater than begin date');
                    }
                    $datas = [];
                    // ตัวแปรสำหรับตรวจสอบการแก้ไข
                    $options_check = [];
                    foreach (Language::get('CAR_OPTIONS', []) as $key => $label) {
                        $options_check[] = $key;
                        $values = $request->post($key, [])->toInt();
                        if (!empty($values)) {
                            $datas[$key] = implode(',', $values);
                        }
                    }
                    if (empty($ret)) {
                        $save['member_id'] = $index->member_id;
                        $save['status'] = $index->status;
                        $save['approve'] = $index->approve;
                        $save['closed'] = $index->closed;
                        $save['department'] = $index->department;
                        // Database
                        $db = $this->db();
                        // table
                        $table = $this->getTableName('car_reservation');
                        if ($index->id == 0) {
                            $save['id'] = $db->getNextId($table);
                            $save['create_date'] = date('Y-m-d H:i:s');
                        } else {
                            $save['id'] = $index->id;
                            $save['create_date'] = $index->create_date;
                        }
                        if ($index->id == 0) {
                            // ใหม่
                            $db->insert($table, $save);
                            // ใหม่ ส่งอีเมลเสมอ
                            $changed = true;
                        } else {
                            // แก้ไข
                            $db->update($table, $save['id'], $save);
                            // ตรวจสอบการแก้ไข
                            $changed = false;
                            if (!empty(self::$cfg->car_notifications)) {
                                foreach ($save as $key => $value) {
                                    if ($value != $index->{$key}) {
                                        $changed = true;
                                        break;
                                    }
                                }
                                if (!$changed) {
                                    foreach ($options_check as $key) {
                                        if (isset($datas[$key])) {
                                            if ($datas[$key] != $index->{$key}) {
                                                $changed = true;
                                                break;
                                            }
                                        } elseif ($index->{$key} != '') {
                                            $changed = true;
                                            break;
                                        }
                                    }
                                }
                            }
                        }
                        if ($index->phone != $user['phone']) {
                            if (!empty(self::$cfg->car_notifications)) {
                                $changed = true;
                            }
                            // อัปเดตเบอร์โทรสมาชิก
                            $db->update($this->getTableName('user'), $login['id'], $user);
                        }
                        // รายละเอียดการจอง
                        $table = $this->getTableName('car_reservation_data');
                        $db->delete($table, array('reservation_id', $save['id']), 0);
                        foreach ($datas as $key => $value) {
                            if ($value != '') {
                                $db->insert($table, array(
                                    'reservation_id' => $save['id'],
                                    'name' => $key,
                                    'value' => $value
                                ));
                            }
                            $save[$key] = $value;
                        }
                        // สถานะการจอง
                        $status = \Car\Tools\View::toStatus($save, false);
                        // log
                        \Index\Log\Model::add($save['id'], 'car', 'Status', $status, $login['id']);
                        if (empty($ret) && $changed) {
                            // ส่งอีเมลไปยังผู้ที่เกี่ยวข้อง
                            $ret['alert'] = \Car\Email\Model::send($save);
                        } else {
                            // ไม่ส่งอีเมล
                            $ret['alert'] = Language::get('Saved successfully');
                        }
                        $ret['location'] = $request->getUri()->postBack('index.php', array('module' => 'car', 'status' => $save['status']));
                        // เคลียร์
                        $request->removeToken();
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
