<?php
/**
 * @filesource modules/car/models/approved.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Car\Approved;

use Gcms\Login;
use Kotchasan\ArrayTool;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=car-approved
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * บันทึกข้อมูลที่ส่งมาจากฟอร์ม (approved.php)
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
                $approve = $request->post('approved_approve')->toInt();
                $status = $request->post('approved_status')->toInt();
                $chauffeur = $request->post('approved_chauffeur')->toInt();
                $reason = $request->post('approved_reason')->topic();
                if ($status == 2 && $reason == '') {
                    // ไม่อนุมัติ ต้องมีเหตุผล
                    $ret['ret_approved_reason'] = 'Please fill in';
                } else {
                    $booking = static::createQuery()
                        ->from('car_reservation')
                        ->where(array('id', $request->post('approved_id')->toInt()))
                        ->first();
                    if ($booking) {
                        if ($status == 1 && $approve == $booking->closed) {
                            if ($chauffeur == 0) {
                                // อนุมัติ ต้องระบุคนขับรถ
                                $ret['ret_approved_chauffeur'] = 'Please select';
                            }
                        } else {
                            $chauffeur = $booking->chauffeur;
                        }
                        if (empty($ret)) {
                            // บันทึกการอนุมัติ
                            $ret['alert'] = self::approving($booking, $login, $approve, $status, $reason, $chauffeur);
                            // close
                            $ret['modal'] = 'close';
                            $ret['location'] = 'reload';
                            // clear
                            $request->removeToken();
                        }
                    }
                }
            } catch (\Kotchasan\InputItemException $e) {
                $ret['alert'] = $e->getMessage();
            }
        }
        if (empty($ret)) {
            $ret['alert'] = Language::get('Sorry, Item not found It&#39;s may be deleted');
        }
        // คืนค่าเป็น JSON
        echo json_encode($ret);
    }

    /**
     * อนุมัติ/ไม่อนุมัติ
     *
     * @param object $booking
     * @param array $login
     * @param int $approve
     * @param int $status
     * @param string $reason
     * @param int $chauffeur
     *
     * @return string
     */
    private static function approving($booking, $login, $approve, $status, $reason, $chauffeur)
    {
        if ($login['status'] == 1) {
            // แอดมิน ทำตามที่เลือก
            $save = array(
                'approve' => $approve,
                'status' => $status,
                'chauffeur' => $chauffeur
            );
        } elseif (isset(self::$cfg->car_approve_status[$approve])) {
            // ตรวจสอบว่าสามารถอนุมัติได้หรือไม่
            $canApprove = false;
            if (self::$cfg->car_approve_status[$approve] == $login['status']) {
                if (empty(self::$cfg->car_approve_department[$approve])) {
                    // อนุมัติภายในแผนก
                    $canApprove = in_array($booking->department, $login['department']);
                } elseif (in_array(self::$cfg->car_approve_department[$approve], $login['department'])) {
                    // แผนกที่กำหนด
                    $canApprove = true;
                }
            }
            // ผู้อนุมัติ
            if ($canApprove) {
                // อนุมัติ
                if ($status == 1) {
                    // ตรวจสอบห้องว่าง เฉพาะรายการที่จะอนุมัติ
                    if (!\Car\Checker\Model::availability((array) $booking)) {
                        return Language::get('Booking are not available at select time');
                    }
                    if ($approve == $booking->closed) {
                        // อนุมัติลำดับสูงสุด
                        $save = array(
                            'approve' => $approve,
                            'status' => 1,
                            'chauffeur' => $chauffeur
                        );
                    } else {
                        // รออนุมัติลำดับถัดไป
                        $nextApprove = ArrayTool::getNextKey(self::$cfg->car_approve_department, $approve);
                        $save = array(
                            'approve' => $nextApprove,
                            'status' => 0,
                            'chauffeur' => $chauffeur
                        );
                        // อนุมัติลำดับปัจจุบัน
                        \Index\Log\Model::add($booking->id, 'car', 'Status', \Car\Tools\View::toStatus($save), $login['id']);
                    }
                } else {
                    // อื่นๆ ทำตามที่เลือก
                    $save = array(
                        'approve' => $approve,
                        'status' => $status,
                        'chauffeur' => $chauffeur
                    );
                }
            }
        }
        if (empty($ret) && !empty($save)) {
            // ไม่อนุมัติ ระบุเหตุผล
            $save['reason'] = $save['status'] == 2 ? $reason : '';
            // save
            self::createQuery()
                ->update('car_reservation')
                ->set($save)
                ->where(array('id', $booking->id))
                ->execute();
            if ($booking->approve != $save['approve'] || $booking->status != $save['status']) {
                // log สถานะปัจจุบัน
                \Index\Log\Model::add($booking->id, 'car', 'Status', \Car\Tools\View::toStatus($save), $login['id']);
                // ข้อมูลการอนุมัติสำหรับส่งอีเมล
                $booking->approve = $save['approve'];
                $booking->status = $save['status'];
                $booking->chauffeur = $save['chauffeur'];
                $booking->reason = $save['reason'];
                // ส่งอีเมลแจ้งการการอนุมัติ
                return \Car\Email\Model::send((array) $booking);
            }
        }
        // ไม่ต้องทำอะไร
        return Language::get('Saved successfully');
    }
}
