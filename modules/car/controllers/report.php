<?php
/**
 * @filesource modules/car/controllers/report.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Car\Report;

use Gcms\Login;
use Kotchasan\Html;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=car-report
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Car\Base\Controller
{
    /**
     * รายงานการจอง (admin)
     *
     * @param Request $request
     *
     * @return string
     */
    public function render(Request $request)
    {
        // ข้อความ title bar
        $this->title = Language::get('Vehicle booking report');
        // เลือกเมนู
        $this->menu = 'report';
        // สมาชิก
        $login = Login::isMember();
        if ($login) {
            // ค่าที่ส่งมา
            $params = array(
                'from' => $request->request('from')->date(),
                'to' => $request->request('to')->date(),
                'vehicle_id' => $request->request('vehicle_id')->toInt(),
                'department' => $request->request('department')->topic(),
                'chauffeur' => $request->request('chauffeur', -2)->toInt(),
                'status' => $request->request('status', -1, 'carReport_status')->toInt(),
                'booking_status' => Language::get('BOOKING_STATUS')
            );
            setcookie('carReport_status', $params['status'], time() + 2592000, '/', HOST, HTTPS, true);
            // สามารถอนุมัติได้
            $params['approve'] = self::reportApprove($login);
            if ($params['approve'] > 0 && empty(self::$cfg->car_approve_department[$params['approve']])) {
                // ผู้อนุมัติ ภายในแผนกของตัวเอง
                $params['department'] = $login['department'][0];
            }
            if ($params['approve'] == -2) {
                // คนขับรถ สถานะอนุมัติแล้วเท่านั้น
                $params['status'] = 1;
                $params['chauffeur'] = $login['id'];
            }
            if ($params['approve'] != 0) {
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
                $ul->appendChild('<li><span class="icon-verfied">{LNG_Report}</span></li>');
                $ul->appendChild('<li><span>{LNG_Book a vehicle}</span></li>');
                if (isset($title)) {
                    $ul->appendChild('<li><span>'.$title.'</span></li>');
                }
                $section->add('header', array(
                    'innerHTML' => '<h2 class="icon-report">'.$this->title.'</h2>'
                ));
                // menu
                $section->appendChild(\Index\Tabmenus\View::render($request, 'report', 'vehicle'));
                $div = $section->add('div', array(
                    'class' => 'content_bg'
                ));
                // แสดงตาราง
                $div->appendChild(\Car\Report\View::create()->render($request, $params));
                // คืนค่า HTML
                return $section->render();
            }
        }
        // 404
        return \Index\Error\Controller::execute($this, $request->getUri());
    }
}
