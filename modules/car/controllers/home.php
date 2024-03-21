<?php
/**
 * @filesource modules/car/controllers/home.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Car\Home;

use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * Controller สำหรับการแสดงผลหน้า Home
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Controller
{
    /**
     * ฟังก์ชั่นสร้าง card
     *
     * @param Request               $request
     * @param \Kotchasan\Collection $card
     * @param array                 $login
     */
    public static function addCard(Request $request, $card, $login)
    {
        if ($login) {
            $icons = ['icon-verfied', 'icon-valid', 'icon-invalid'];
            $booking_status = Language::get('BOOKING_STATUS');
            $today = date('Y-m-d');
            // query ข้อมูล card
            $datas = \Car\Home\Model::get($login, $today);
            // รายการลาของตัวเอง
            foreach ($booking_status as $status => $label) {
                if (!empty($datas[1][$status])) {
                    $url = WEB_URL.'index.php?module=car&amp;status=';
                    \Index\Home\Controller::renderCard($card, $icons[$status], '{LNG_My Booking}', number_format($datas[1][$status]), '{LNG_Book a vehicle} '.$label, $url.$status);
                }
            }
            if (isset($datas[0])) {
                // ผู้อนุมัติ, พนักงานขับรถ
                foreach ($datas[0] as $status => $value) {
                    if (isset($icons[$status]) && $value > 0) {
                        $url = WEB_URL.'index.php?module=car-report&amp;status='.$status;
                        if ($login['status'] == self::$cfg->chauffeur_status) {
                            $url .= '&amp;from='.$today.'&amp;to='.$today;
                            \Index\Home\Controller::renderCard($card, $icons[$status], '{LNG_Chauffeur}', number_format($value), '{LNG_Booking today} '.$booking_status[$status], $url);
                        } else {
                            \Index\Home\Controller::renderCard($card, $icons[$status], '{LNG_Can be approve}', number_format($value), '{LNG_Book a vehicle} '.$booking_status[$status], $url);
                        }
                    }
                }
            }
            \Index\Home\Controller::renderCard($card, 'icon-shipping', '{LNG_Vehicle}', number_format(\Car\Home\Model::cars()), '{LNG_All cars}', 'index.php?module=car-vehicles');
        }
    }

    /**
     * ฟังก์ชั่นสร้าง block
     *
     * @param Request $request
     * @param Collection $block
     * @param array $login
     */
    public static function addBlock(Request $request, $block, $login)
    {
        if ($login || empty(self::$cfg->car_login_type)) {
            $content = \Car\Home\View::create()->render($request, $login);
            $block->set('Car calendar', $content);
        }
    }
}
