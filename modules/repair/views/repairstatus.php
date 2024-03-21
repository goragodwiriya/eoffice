<?php
/**
 * @filesource modules/repair/views/repairstatus.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Repair\Repairstatus;

use Kotchasan\Html;
use Kotchasan\Http\Request;

/**
 * module=repair-repairstatus
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
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
        $form = Html::create('form', array(
            'id' => 'setup_frm',
            'class' => 'setup_frm'
        ));
        $fieldset = $form->add('fieldset', array(
            'titleClass' => 'icon-star0',
            'title' => '{LNG_Details of} {LNG_Repair status}'
        ));
        $list = $fieldset->add('ul', array(
            'class' => 'editinplace_list',
            'id' => 'list'
        ));
        foreach (\Repair\Repairstatus\Model::all() as $item) {
            $list->appendChild(self::createRow($item));
        }
        $fieldset = $form->add('fieldset', array(
            'class' => 'submit'
        ));
        $a = $fieldset->add('a', array(
            'class' => 'button add large',
            'id' => 'list_add_0_repairstatus'
        ));
        $a->add('span', array(
            'class' => 'icon-plus',
            'innerHTML' => '{LNG_Add} {LNG_Repair status}'
        ));
        $form->script('initEditInplace("list", "repair/model/repairstatus/action", "list_add_0_repairstatus");');
        // คืนค่า HTML
        return $form->render();
    }

    /**
     * ฟังก์ชั่นสร้างแถวของรายการหมวดหมู่
     *
     * @param array $item
     *
     * @return string
     */
    public static function createRow($item)
    {
        $id = $item['category_id'].'_'.$item['type'];
        $row = '<li class="row" id="list_'.$id.'">';
        $row .= '<div class="no">['.$item['category_id'].']</div>';
        $row .= '<div><span id="list_name_'.$id.'" title="{LNG_Click to edit}" class="editinplace">'.$item['topic'].'</span></div>';
        $row .= '<div class="right">';
        $row .= '<span id="list_published_'.$id.'" class="icon-published'.$item['published'].'"></span>';
        if ($item['type'] == 'repairstatus') {
            $row .= '<span id="list_color_'.$id.'" class="icon-color" title="'.$item['color'].'"></span>';
        }
        $row .= '<span id="list_delete_'.$id.'" class="icon-delete" title="{LNG_Delete}"></span>';
        $row .= '</div>';
        $row .= '</li>';
        return $row;
    }
}
