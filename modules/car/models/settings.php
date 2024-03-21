<?php
/**
 * @filesource modules/car/models/settings.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Car\Settings;

use Gcms\Config;
use Gcms\Login;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=car-settings
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\KBase
{
    /**
     * รับค่าจากฟอร์ม (settings.php)
     *
     * @param Request $request
     */
    public function submit(Request $request)
    {
        $ret = [];
        // session, token, can_config, ไม่ใช่สมาชิกตัวอย่าง
        if ($request->initSession() && $request->isSafe() && $login = Login::isMember()) {
            if (Login::notDemoMode($login) && Login::checkPermission($login, 'can_config')) {
                try {
                    // โหลด config
                    $config = Config::load(ROOT_PATH.'settings/config.php');
                    // รับค่าจากการ POST
                    $config->car_login_type = $request->post('car_login_type')->toInt();
                    $config->chauffeur_status = $request->post('chauffeur_status')->toInt();
                    $config->car_w = max(100, $request->post('car_w')->toInt());
                    $config->car_approving = $request->post('car_approving')->toInt();
                    $config->car_delete = $request->post('car_delete')->toInt();
                    $config->car_cancellation = $request->post('car_cancellation')->toInt();
                    $config->car_notifications = $request->post('car_notifications')->toInt();
                    $config->car_email = $request->post('car_email')->toInt();
                    $config->car_line_id = $request->post('car_line_id')->toInt();
                    $config->car_approve_status = [];
                    $config->car_approve_department = [];
                    $car_approve_status = $request->post('car_approve_status', [])->toInt();
                    $car_approve_department = $request->post('car_approve_department', [])->topic();
                    $approve_level = $request->post('car_approve_level')->toInt();
                    for ($level = 1; $level <= $approve_level; $level++) {
                        $config->car_approve_status[$level] = $car_approve_status[$level];
                        $config->car_approve_department[$level] = $car_approve_department[$level];
                    }
                    // save config
                    if (Config::save($config, ROOT_PATH.'settings/config.php')) {
                        // log
                        \Index\Log\Model::add(0, 'car', 'Save', '{LNG_Module settings} {LNG_Book a vehicle}', $login['id']);
                        // คืนค่า
                        $ret['alert'] = Language::get('Saved successfully');
                        $ret['location'] = 'reload';
                        // เคลียร์
                        $request->removeToken();
                    } else {
                        // ไม่สามารถบันทึก config ได้
                        $ret['alert'] = Language::replace('File %s cannot be created or is read-only.', 'settings/config.php');
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
