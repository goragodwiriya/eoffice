<?php
/**
 * @filesource modules/repair/controllers/repairstatus.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Repair\Repairstatus;

use Gcms\Login;
use Kotchasan\Html;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=repair-repairstatus
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Controller
{
    /**
     * สถานะการซ่อม
     *
     * @param Request $request
     *
     * @return string
     */
    public function render(Request $request)
    {
        // ข้อความ title bar
        $this->title = Language::get('Repair status');
        // เลือกเมนู
        $this->menu = 'settings';
        // สามารถตั้งค่าระบบได้
        if (Login::checkPermission(Login::isMember(), 'can_config')) {
            // แสดงผล
            $section = Html::create('section');
            // breadcrumbs
            $breadcrumbs = $section->add('nav', array(
                'class' => 'breadcrumbs'
            ));
            $ul = $breadcrumbs->add('ul');
            $ul->appendChild('<li><span class="icon-tools">{LNG_Settings}</span></li>');
            $ul->appendChild('<li><span>{LNG_Repair}</span></li>');
            $ul->appendChild('<li><span>{LNG_Repair status}</span></li>');
            $section->add('header', array(
                'innerHTML' => '<h2 class="icon-star0">'.$this->title.'</h2>'
            ));
            // menu
            $section->appendChild(\Index\Tabmenus\View::render($request, 'settings', 'repair'));
            $div = $section->add('div', array(
                'class' => 'content_bg'
            ));
            // แสดงฟอร์ม
            $div->appendChild(\Repair\Repairstatus\View::create()->render($request));
            // คืนค่า HTML
            return $section->render();
        }
        // 404
        return \Index\Error\Controller::execute($this, $request->getUri());
    }
}
