<?php
/**
 * @filesource modules/car/controllers/initmenu.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Car\Initmenu;

use Gcms\Login;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * Init Menu
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Car\Base\Controller
{
    /**
     * ฟังก์ชั่นเริ่มต้นการทำงานของโมดูลที่ติดตั้ง
     * และจัดการเมนูของโมดูล
     *
     * @param Request                $request
     * @param \Index\Menu\Controller $menu
     * @param array                  $login
     */
    public static function execute(Request $request, $menu, $login)
    {
        if ($login || empty(self::$cfg->car_login_type)) {
            $menu->addTopLvlMenu('vehicles', '{LNG_Book a vehicle}', 'index.php?module=car-vehicles', null, 'member');
        }
        if ($login) {
            $menu->addTopLvlMenu('booking', '{LNG_My Booking}', null, null, 'member');
            $submenus = [];
            foreach (Language::get('BOOKING_STATUS', []) as $status => $text) {
                $submenus[] = array(
                    'text' => $text,
                    'url' => 'index.php?module=car&amp;status='.$status
                );
            }
            $menu->add('booking', '{LNG_Vehicle}', null, $submenus, 'carbooking');
            $submenus = [];
            // สามารถตั้งค่าระบบได้
            if (Login::checkPermission($login, 'can_config')) {
                $submenus['settings'] = array(
                    'text' => '{LNG_Settings}',
                    'url' => 'index.php?module=car-settings'
                );
            }
            // สามารถจัดการได้
            if (Login::checkPermission($login, 'can_manage_car')) {
                $submenus['setup'] = array(
                    'text' => '{LNG_List of} {LNG_Vehicle}',
                    'url' => 'index.php?module=car-setup'
                );
                foreach (Language::get('CAR_OPTIONS', []) as $type => $text) {
                    $submenus[] = array(
                        'text' => $text,
                        'url' => 'index.php?module=car-categories&amp;type='.$type
                    );
                }
                foreach (Language::get('CAR_SELECT', []) as $type => $text) {
                    $submenus[] = array(
                        'text' => $text,
                        'url' => 'index.php?module=car-categories&amp;type='.$type
                    );
                }
            }
            if (!empty($submenus)) {
                $menu->add('settings', '{LNG_Vehicle}', null, $submenus, 'car');
            }
            // สามารถอนุมัติ, ดูรายงาน ได้
            $canReportApprove = self::reportApprove($login);
            // สามารถดูรายงานได้ (จอง)
            if ($canReportApprove) {
                $menu->add('report', '{LNG_Book a vehicle}', 'index.php?module=car-report', null, 'vehicle');
            }
        }
    }
}
