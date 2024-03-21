<?php
/**
 * @filesource modules/car/views/home.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Car\Home;

use Kotchasan\Html;

/**
 * หน้า Home
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
{
    /**
     * หน้า Home
     *
     * @param object $index
     * @param array  $login
     *
     * @return string
     */
    public function render($index, $login)
    {
        $section = Html::create('div');
        $section->add('header', array(
            'innerHTML' => '<h2 class="icon-shipping">{LNG_Booking calendar} {LNG_Vehicle}</h2>'
        ));
        $div = $section->add('div', array(
            'class' => 'setup_frm'
        ));
        $div->add('div', array(
            'id' => 'car-calendar',
            'class' => 'margin-left-right'
        ));
        // คืนค่าปีที่มีการจองสูงสุดและต่ำสุด
        $range = \Car\Home\Model::getYearRange();
        /* Javascript */
        $section->script('initCalendar("car", '.$range->min.', '.$range->max.');');
        // คืนค่า HTML
        return $section->render();
    }
}
