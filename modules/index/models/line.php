<?php
/**
 * @filesource modules/index/models/line.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Index\Line;

use Gcms\Login;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=line
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * ทดสอบการส่ง Line
     *
     * @param Request $request
     */
    public function test(Request $request)
    {
        // referer
        if ($request->isReferer() && $request->isAjax()) {
            // ทดสอบส่งข้อความ Line
            \Gcms\Line::notify(strip_tags(self::$cfg->web_title), $request->post('id')->quote());
        }
    }

    /**
     * อ่านรายการที่เลือก
     * $id = 0 รายการใหม่
     *
     * @param int $id
     *
     * @return object|bool ไม่พบข้อมูลคืนค่า false
     */
    public static function get($id)
    {
        if ($id > 0) {
            return static::createQuery()
                ->from('line')
                ->where(array('id', $id))
                ->first();
        } else {
            return (object) array(
                'id' => 0
            );
        }
    }

    /**
     * บันทึกข้อมูล (line.php)
     *
     * @param Request $request
     */
    public function submit(Request $request)
    {
        $ret = [];
        // session, token, member, can_config, ไม่ใช่สมาชิกตัวอย่าง
        if ($request->initSession() && $request->isSafe() && $login = Login::isMember()) {
            if (Login::checkPermission($login, 'can_config') && Login::notDemoMode($login)) {
                try {
                    // ค่าที่ส่งมา
                    $save = array(
                        'department' => $request->post('line_department')->toInt(),
                        'name' => $request->post('line_name')->topic(),
                        'token' => $request->post('line_token')->filter('a-zA-Z0-9')
                    );
                    $index = self::get($request->post('line_id')->toInt());
                    // แอดมิน
                    if ($index) {
                        // name
                        if ($save['department'] == 0 && $save['name'] == '') {
                            $ret['ret_line_name'] = 'Please fill in';
                        } elseif ($save['department'] > 0) {
                            $save['name'] = '';
                        }
                        // token_id
                        if ($save['token'] == '') {
                            $ret['ret_line_token'] = 'Please fill in';
                        }
                        if (empty($ret)) {
                            if ($index->id == 0) {
                                // ใหม่
                                $this->db()->insert($this->getTableName('line'), $save);
                            } else {
                                // แก้ไข
                                $this->db()->update($this->getTableName('line'), $index->id, $save);
                            }
                            // คืนค่า
                            $ret['alert'] = Language::get('Saved successfully');
                            $ret['modal'] = 'close';
                            $ret['location'] = 'reload';
                            // เคลียร์
                            $request->removeToken();
                        }
                    }
                } catch (\Kotchasan\InputItemException $e) {
                    $ret['alert'] = $e->getMessage();
                }
            }
        }
        if (empty($ret)) {
            $ret['alert'] = Language::get('Unable to complete the transaction');
        }
        // คืนค่าเป็น JSON
        echo json_encode($ret);
    }
}
