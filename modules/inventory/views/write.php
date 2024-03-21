<?php
/**
 * @filesource modules/inventory/views/write.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Inventory\Write;

use Kotchasan\Html;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=inventory-write
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
{
    /**
     * ฟอร์มเพิ่ม/แก้ไข Inventory
     *
     * @param Request $request
     * @param object $index
     *
     * @return string
     */
    public function render(Request $request, $index)
    {
        $form = Html::create('form', array(
            'id' => 'setup_frm',
            'class' => 'setup_frm',
            'autocomplete' => 'off',
            'action' => 'index.php/inventory/model/write/submit',
            'onsubmit' => 'doFormSubmit',
            'ajax' => true,
            'token' => true
        ));
        $fieldset = $form->add('fieldset', array(
            'titleClass' => 'icon-write',
            'title' => '{LNG_Details of} {LNG_Equipment}'
        ));
        $groups = $fieldset->add('groups');
        // product_no
        $groups->add('text', array(
            'id' => 'product_no',
            'labelClass' => 'g-input icon-number',
            'itemClass' => 'width50',
            'label' => '{LNG_Serial/Registration No.}',
            'maxlength' => 20,
            'value' => isset($index->product_no) ? $index->product_no : ''
        ));
        // topic
        $groups->add('text', array(
            'id' => 'topic',
            'labelClass' => 'g-input icon-edit',
            'itemClass' => 'width50',
            'label' => '{LNG_Equipment}',
            'placeholder' => '{LNG_Details of} {LNG_Equipment}',
            'maxlength' => 64,
            'value' => isset($index->topic) ? $index->topic : ''
        ));
        // category
        $category = \Inventory\Category\Model::init();
        $n = 0;
        foreach (Language::get('INVENTORY_CATEGORIES', []) as $key => $label) {
            if ($n % 2 == 0) {
                $groups = $fieldset->add('groups');
            }
            $groups->add('text', array(
                'id' => $key,
                'labelClass' => 'g-input icon-menus',
                'itemClass' => 'width50',
                'label' => $label,
                'datalist' => $category->toSelect($key),
                'value' => isset($index->{$key}) ? $index->{$key} : 0,
                'text' => ''
            ));
            $n++;
        }
        // detail
        $fieldset->add('textarea', array(
            'id' => 'detail',
            'labelClass' => 'g-input icon-file',
            'itemClass' => 'item',
            'label' => '{LNG_Detail}',
            'rows' => 3,
            'value' => isset($index->detail) ? $index->detail : ''
        ));
        $groups = $fieldset->add('groups');
        // stock
        $groups->add('number', array(
            'id' => 'stock',
            'labelClass' => 'g-input icon-number',
            'itemClass' => 'width50',
            'label' => '{LNG_Stock}',
            'value' => isset($index->stock) ? $index->stock : 1
        ));
        // unit
        $groups->add('text', array(
            'id' => 'unit',
            'labelClass' => 'g-input icon-menus',
            'itemClass' => 'width50',
            'label' => '{LNG_Unit}',
            'datalist' => $category->toSelect('unit'),
            'value' => isset($index->unit) ? $index->unit : 0,
            'text' => ''
        ));
        // picture
        if (is_file(ROOT_PATH.DATA_FOLDER.'inventory/'.$index->id.'.jpg')) {
            $img = WEB_URL.DATA_FOLDER.'inventory/'.$index->id.'.jpg?'.time();
        } else {
            $img = WEB_URL.'modules/inventory/img/noimage.png';
        }
        $fieldset->add('file', array(
            'id' => 'picture',
            'labelClass' => 'g-input icon-upload',
            'itemClass' => 'item',
            'label' => '{LNG_Image}',
            'comment' => '{LNG_Browse image uploaded, type :type} ({LNG_resized automatically})',
            'dataPreview' => 'imgPicture',
            'previewSrc' => $img,
            'accept' => self::$cfg->inventory_img_typies
        ));
        $groups = $fieldset->add('groups');
        // device_user
        $groups->add('text', array(
            'id' => 'device_user',
            'labelClass' => 'g-input icon-user',
            'itemClass' => 'width50',
            'label' => '{LNG_Device user}',
            'value' => $index->device_user
        ));
        // create_date
        $groups->add('date', array(
            'id' => 'create_date',
            'labelClass' => 'g-input icon-calendar',
            'itemClass' => 'width50',
            'label' => '{LNG_Received date}',
            'value' => $index->create_date
        ));
        // status
        $fieldset->add('select', array(
            'id' => 'status',
            'labelClass' => 'g-input icon-valid',
            'itemClass' => 'item',
            'label' => '{LNG_Status}',
            'options' => Language::get('INVENTORY_STATUS'),
            'value' => $index->status
        ));
        $fieldset = $form->add('fieldset', array(
            'class' => 'submit'
        ));
        // submit
        $fieldset->add('submit', array(
            'class' => 'button save large icon-save',
            'value' => '{LNG_Save}'
        ));
        // id
        $fieldset->add('hidden', array(
            'id' => 'id',
            'value' => $index->id
        ));
        // member_id
        $fieldset->add('hidden', array(
            'id' => 'member_id',
            'value' => $index->member_id
        ));
        \Gcms\Controller::$view->setContentsAfter(array(
            '/:type/' => implode(', ', self::$cfg->inventory_img_typies)
        ));
        // Javascript
        $form->script('initInventoryWrite();');
        // คืนค่า HTML
        return $form->render();
    }
}
