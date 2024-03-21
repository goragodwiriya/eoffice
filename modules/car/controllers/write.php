<?php
/**
 * @filesource modules/car/controllers/write.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Car\Write;

use Gcms\Login;
use Kotchasan\Html;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=car-write
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Controller
{
    /**
     * เพิ่ม-แก้ไข รถยนต์
     *
     * @param Request $request
     *
     * @return string
     */
    public function render(Request $request)
    {
        // ข้อความ title bar
        $this->title = Language::get('Vehicle');
        // เลือกเมนู
        $this->menu = 'settings';
        // สมาชิก
        $login = Login::isMember();
        // ตรวจสอบรายการที่เลือก
        $index = \Car\Write\Model::get($request->request('id')->toInt());
        // สามารถจัดการได้
        if ($index && Login::checkPermission($login, 'can_manage_car')) {
            // ข้อความ title bar
            $title = Language::get($index->id == 0 ? 'Add' : 'Edit');
            $this->title = $title.' '.$this->title;
            // แสดงผล
            $section = Html::create('section');
            // breadcrumbs
            $breadcrumbs = $section->add('nav', array(
                'class' => 'breadcrumbs'
            ));
            $ul = $breadcrumbs->add('ul');
            $ul->appendChild('<li><span class="icon-settings">{LNG_Settings}</span></li>');
            $ul->appendChild('<li><span>{LNG_Book a vehicle}</span></li>');
            $ul->appendChild('<li><a href="{BACKURL?module=car-setup&id=0}">{LNG_List of}</a></li>');
            $ul->appendChild('<li><span>'.$title.'</span></li>');
            $section->add('header', array(
                'innerHTML' => '<h2 class="icon-write">'.$this->title.'</h2>'
            ));
            // menu
            $section->appendChild(\Index\Tabmenus\View::render($request, 'settings', 'car'));
            $div = $section->add('div', array(
                'class' => 'content_bg'
            ));
            // แสดงฟอร์ม
            $div->appendChild(\Car\Write\View::create()->render($index, $login));
            // คืนค่า HTML
            return $section->render();
        }
        // 404
        return \Index\Error\Controller::execute($this, $request->getUri());
    }
}
