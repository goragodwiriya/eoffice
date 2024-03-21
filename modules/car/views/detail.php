<?php
/**
 * @filesource modules/car/views/detail.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Car\Detail;

use Kotchasan\Language;

/**
 * module=car-detail
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Car\Tools\View
{
    /**
     * แสดงรายละเอียด ยานพาหนะ
     *
     * @param object $index
     *
     * @return string
     */
    public function vehicle($index)
    {
        $content = '<article class=modal_detail>';
        $content .= '<header><h3 class=icon-shipping>{LNG_Details of} {LNG_Vehicle}</h3></header>';
        if (is_file(ROOT_PATH.DATA_FOLDER.'car/'.$index->id.'.jpg')) {
            $content .= '<figure class="center"><img src="'.WEB_URL.DATA_FOLDER.'car/'.$index->id.'.jpg"></figure>';
        }
        $content .= '<table class="border data fullwidth"><tbody>';
        $content .= '<tr><th>{LNG_Vehicle No.}</th><td><span class="term" style="background-color:'.$index->color.'">'.$index->number.'</span></td></tr>';
        $category = \Car\Category\Model::init();
        foreach (Language::get('CAR_SELECT', []) as $key => $label) {
            if (isset($index->{$key})) {
                $content .= '<tr><th>'.$label.'</th><td>'.$category->get($key, $index->{$key}).'</td></tr>';
            }
        }
        $content .= '<tr><th>{LNG_Number of seats}</th><td>'.$index->seats.'</td></tr>';
        if (!empty($index->detail)) {
            $content .= '<tr><th>{LNG_Detail}</th><td>'.nl2br($index->detail).'</td></tr>';
        }
        $content .= '</tbody></table>';
        $content .= '</article>';
        // คืนค่า HTML
        return Language::trans($content);
    }

    /**
     * แสดงรายละเอียดการจอง
     *
     * @param object $index
     *
     * @return string
     */
    public function booking($index)
    {
        $category = \Car\Category\Model::init();
        $content = '<article class=modal_detail>';
        $content .= '<header><h3 class="icon-shipping cuttext">{LNG_Details of} {LNG_Booking}</h3></header>';
        $content .= '<table class="border data fullwidth"><tbody>';
        $content .= '<tr><th class=top>{LNG_Usage details}</th><td>'.nl2br($index->detail).'</td></tr>';
        $content .= '<tr><th>{LNG_Number of travelers}</th><td>'.$index->travelers.' {LNG_persons}</td></tr>';
        $content .= '<tr><th>{LNG_Contact name}</th><td>'.$index->contact;
        if ($index->phone != '') {
            $content .= ' <a href="tel:'.$index->phone.'"><span class="icon-phone">'.$index->phone.'</span></a>';
        }
        $content .= '</td></tr>';
        $content .= '<tr><th>{LNG_Vehicle}</th><td><span class="term" style="background-color:'.$index->color.'">'.$index->number.'</span></td></tr>';
        foreach (Language::get('CAR_SELECT', []) as $key => $label) {
            if (isset($index->{$key})) {
                $content .= '<tr><th>'.$label.'</th><td>'.$category->get($key, $index->{$key}).'</td></tr>';
            }
        }
        $content .= '<tr><th>{LNG_Chauffeur}</th><td>';
        if ($index->chauffeur > 0) {
            $content .= $index->chauffeur_name;
            if (!empty($index->chauffeur_phone)) {
                $content .= ' <a href="tel:'.$index->chauffeur_phone.'"><span class="icon-phone">'.$index->chauffeur_phone.'</span></a>';
            }
        } else {
            $chauffeur = array(-1 => '{LNG_Self drive}', 0 => '{LNG_Not specified} ({LNG_anyone})');
            $content .= $chauffeur[$index->chauffeur];
        }
        $content .= '</td></tr>';
        $content .= '<tr><th class=top>{LNG_Date}</th><td>';
        $content .= self::dateRange(array(
            'begin' => $index->begin,
            'end' => $index->end
        ));
        $content .= '</td></tr>';
        foreach (Language::get('CAR_OPTIONS', []) as $key => $label) {
            if (!empty($index->{$key})) {
                $options = explode(',', $index->{$key});
                $vals = [];
                foreach ($category->toSelect($key) as $i => $v) {
                    if (in_array($i, $options)) {
                        $vals[] = $v;
                    }
                }
                $content .= '<tr><th>'.$label.'</th><td>'.implode(', ', $vals).'</td></tr>';
            }
        }
        $content .= '<tr><th>{LNG_Status}</th><td>'.self::toStatus((array) $index, true).'</td></tr>';
        if (!empty($index->reason)) {
            $content .= '<tr><th>{LNG_Reason}</th><td>'.$index->reason.'</td></tr>';
        }
        if (!empty($index->comment)) {
            $content .= '<tr><th>{LNG_Other}</th><td>'.nl2br($index->comment).'</td></tr>';
        }
        $content .= '</tbody></table>';
        $content .= '</article>';
        // คืนค่า HTML
        return Language::trans($content);
    }
}
