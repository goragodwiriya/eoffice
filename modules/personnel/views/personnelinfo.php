<?php
/**
 * @filesource modules/personnel/views/personnelinfo.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Personnel\Personnelinfo;

use Kotchasan\Language;

/**
 * แสดงรายละเอียดบุคคลากร (modal)
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
{
    /**
     * แสดงฟอร์ม Modal สำหรับแสดงรายละเอียดบุคคลากร
     *
     * @param object $index
     * @param array  $login
     *
     * @return string
     */
    public function render($index, $login)
    {
        // picture
        if (is_file(ROOT_PATH.DATA_FOLDER.'personnel/'.$index->id.'.jpg')) {
            $img = WEB_URL.DATA_FOLDER.'personnel/'.$index->id.'.jpg';
        } else {
            $img = WEB_URL.'modules/personnel/img/noimage.jpg';
        }
        $content = [];
        $content[] = '<article class=personnel_view>';
        $content[] = '<header><h3 class=icon-info>{LNG_Details of} {LNG_Personnel}</h3></header>';
        $content[] = '<p><img src="'.$img.'" style="max-width:'.self::$cfg->personnel_w.'px;max-height:'.self::$cfg->personnel_h.'px"></p>';
        $content[] = '<div class="table fullwidth">';
        $content[] = '<p class=tr><span class="td icon-customer">{LNG_Name}</span><span class=td>:</span><span class=td>'.$index->name.'</span></p>';
        $content[] = '<p class=tr><span class="td icon-phone">{LNG_Phone}</span><span class=td>:</span><span class=td>'.self::showPhone($index->phone).'</span></p>';
        // หมวดหมู่
        $category = \Index\Category\Model::init(true);
        $content[] = '<p class=tr><span class="td icon-category">{LNG_Department}</span><span class=td>:</span><span class=td>'.$category->get('department', $index->department).'</span></p>';
        $content[] = '<p class=tr><span class="td icon-category">{LNG_Position}</span><span class=td>:</span><span class=td>'.$category->get('position', $index->position).'</span></p>';
        foreach (Language::get('PERSONNEL_DETAILS', []) as $type => $label) {
            if (!empty($index->{$type})) {
                $content[] = '<p class=tr><span class="td icon-edit">'.$label.'</span><span class=td>:</span><span class=td>'.$index->{$type}.'</span></p>';
            }
        }
        $content[] = '</div>';
        $content[] = '</article>';
        // คืนค่า HTML
        return implode('', $content);
    }
}
