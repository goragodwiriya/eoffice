<?php
/**
 * @filesource modules/booking/views/home.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Booking\Home;

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
            'innerHTML' => '<h2 class="icon-calendar">{LNG_Booking calendar}</h2>'
        ));
        $div = $section->add('div', array(
            'class' => 'setup_frm'
        ));
        $div->add('div', array(
            'id' => 'booking-calendar',
            'class' => 'margin-left-right'
        ));
        // คืนค่าปีที่มีการจองสูงสุดและต่ำสุด
        $range = \Booking\Home\Model::getYearRange();
        /* Javascript */
        $section->script('initCalendar("booking", '.$range->min.', '.$range->max.');');
        // คืนค่า HTML
        return $section->render();
    }
}
