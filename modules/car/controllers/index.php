<?php
/**
 * @filesource modules/car/controllers/index.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Car\Index;

use Gcms\Login;
use Kotchasan\Html;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=car
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Controller
{
    /**
     * ตารางรายการจอง (user)
     *
     * @param Request $request
     *
     * @return string
     */
    public function render(Request $request)
    {
        // สมาชิก
        $login = Login::isMember();
        // ค่าที่ส่งมา
        $params = array(
            'from' => $request->request('from')->date(),
            'to' => $request->request('to')->date(),
            'vehicle_id' => $request->request('vehicle_id')->toInt(),
            'status' => $request->request('status', $request->cookie('carIndex_status', -1)->toInt())->toInt(),
            'booking_status' => Language::get('BOOKING_STATUS'),
            'member_id' => $login['id']
        );
        setcookie('carIndex_status', $params['status'], time() + 2592000, '/', HOST, HTTPS, true);
        // ข้อความ title bar
        $this->title = Language::get('My Booking');
        // เลือกเมนู
        $this->menu = 'booking';
        // สมาชิก
        if ($login) {
            // ข้อความ title bar
            if (isset($params['booking_status'][$params['status']])) {
                $title = $params['booking_status'][$params['status']];
                $this->title .= ' '.$title;
            }
            // แสดงผล
            $section = Html::create('section');
            // breadcrumbs
            $breadcrumbs = $section->add('nav', array(
                'class' => 'breadcrumbs'
            ));
            $ul = $breadcrumbs->add('ul');
            $ul->appendChild('<li><span class="icon-shipping">{LNG_Book a vehicle}</span></li>');
            $ul->appendChild('<li><span>{LNG_Booking}</span></li>');
            $section->add('header', array(
                'innerHTML' => '<h2 class="icon-list">'.$this->title.'</h2>'
            ));
            $div = $section->add('div', array(
                'class' => 'content_bg'
            ));
            // แสดงตาราง
            $div->appendChild(\Car\Index\View::create()->render($request, $params));
            // คืนค่า HTML
            return $section->render();
        }
        // 404
        return \Index\Error\Controller::execute($this, $request->getUri());
    }
}
