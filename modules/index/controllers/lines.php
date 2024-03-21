<?php
/**
 * @filesource modules/index/controllers/lines.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Index\Lines;

use Gcms\Login;
use Kotchasan\Html;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=lines
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Controller
{
    /**
     * รายชื่อไลน์กลุ่ม
     *
     * @param Request $request
     *
     * @return string
     */
    public function render(Request $request)
    {
        // ข้อความ title bar
        $this->title = Language::get('LINE group account');
        // เลือกเมนู
        $this->menu = 'settings';
        // แอดมิน, ไม่ใช่สมาชิกตัวอย่าง
        if (Login::notDemoMode(Login::isAdmin())) {
            // แสดงผล
            $section = Html::create('section');
            // breadcrumbs
            $breadcrumbs = $section->add('nav', array(
                'class' => 'breadcrumbs'
            ));
            $ul = $breadcrumbs->add('ul');
            $ul->appendChild('<li><span class="icon-settings">{LNG_Settings}</span></li>');
            $ul->appendChild('<li><span>{LNG_LINE group account}</span></li>');
            $section->add('header', array(
                'innerHTML' => '<h2 class="icon-line">'.$this->title.'</h2>'
            ));
            // menu
            $section->appendChild(\Index\Tabmenus\View::render($request, 'settings', 'lines'));
            $div = $section->add('div', array(
                'class' => 'content_bg'
            ));
            // แสดงตาราง
            $div->appendChild(\Index\Lines\View::create()->render($request));
            // คืนค่า HTML
            return $section->render();
        }
        // 404
        return \Index\Error\Controller::execute($this, $request->getUri());
    }
}
