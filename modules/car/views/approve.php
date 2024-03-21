<?php
/**
 * @filesource modules/car/views/approve.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Car\Approve;

use Kotchasan\Date;
use Kotchasan\Html;
use Kotchasan\Language;

/**
 * module=car-approve
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Car\Tools\View
{
    /**
     * ฟอร์มแก้ไข การจอง (admin)
     *
     * @param object $index
     * @param array $login
     *
     * @return string
     */
    public function render($index, $login)
    {
        // สามารถอนุมัติได้
        $canApprove = \Car\Base\Controller::canApprove($login, $index);
        // สามารถแก้ไขได้ แอดมิน และตามที่กำหนดใน $car_approve_can_edit
        $canEdit = $canApprove == -1 || !empty(self::$cfg->car_approve_can_edit[$canApprove]);
        // form
        $form = Html::create('form', array(
            'id' => 'setup_frm',
            'class' => 'setup_frm',
            'autocomplete' => 'off',
            'action' => 'index.php/car/model/approve/submit',
            'onsubmit' => 'doFormSubmit',
            'ajax' => true,
            'token' => true
        ));
        $fieldset = $form->add('fieldset', array(
            'title' => '{LNG_Details of} {LNG_Booking} '.self::toStatus((array) $index, true)
        ));
        $groups = $fieldset->add('groups');
        // vehicle_id
        $vehicles = \Car\Vehicles\Model::toSelect(false, $index->vehicle_id);
        if (count($vehicles) < 10) {
            $groups->add('select', array(
                'id' => 'vehicle_id',
                'labelClass' => 'g-input icon-shipping',
                'itemClass' => 'width50',
                'label' => '{LNG_Vehicle}',
                'options' => $vehicles,
                'value' => $index->vehicle_id
            ));
        } else {
            $groups->add('text', array(
                'id' => 'vehicle_id',
                'labelClass' => 'g-input icon-shipping',
                'itemClass' => 'width50',
                'label' => '{LNG_Vehicle}',
                'placeholder' => Language::replace('Search :name and select from the list', array(':name' => 'Vehicle')),
                'datalist' => $vehicles,
                'value' => $index->vehicle_id
            ));
        }
        // travelers
        $groups->add('number', array(
            'id' => 'travelers',
            'labelClass' => 'g-input icon-group',
            'itemClass' => 'width50',
            'label' => '{LNG_Number of travelers}',
            'unit' => '{LNG_persons}',
            'value' => $index->travelers
        ));
        // detail
        $fieldset->add('textarea', array(
            'id' => 'detail',
            'labelClass' => 'g-input icon-file',
            'itemClass' => 'item',
            'label' => '{LNG_Usage details}',
            'rows' => 3,
            'value' => $index->detail
        ));
        $groups = $fieldset->add('groups');
        // name
        $groups->add('text', array(
            'id' => 'name',
            'labelClass' => 'g-input icon-customer',
            'itemClass' => 'width50',
            'label' => '{LNG_Contact name}',
            'disabled' => true,
            'value' => $index->name
        ));
        // phone
        $groups->add('text', array(
            'id' => 'phone',
            'labelClass' => 'g-input icon-phone',
            'itemClass' => 'width50',
            'label' => '{LNG_Phone}',
            'disabled' => true,
            'value' => $index->phone
        ));
        $groups = $fieldset->add('groups');
        // begin_date
        $begin = empty($index->begin) ? time() : strtotime($index->begin);
        $groups->add('date', array(
            'id' => 'begin_date',
            'label' => '{LNG_Begin date}',
            'labelClass' => 'g-input icon-calendar',
            'itemClass' => 'width50',
            'value' => date('Y-m-d', $begin)
        ));
        // begin_time
        $groups->add('time', array(
            'id' => 'begin_time',
            'label' => '{LNG_Begin time}',
            'labelClass' => 'g-input icon-clock',
            'itemClass' => 'width50',
            'value' => date('H:i', $begin)
        ));
        $groups = $fieldset->add('groups');
        // end_date
        $end = empty($index->end) ? time() : strtotime($index->end);
        $groups->add('date', array(
            'id' => 'end_date',
            'label' => '{LNG_End date}',
            'labelClass' => 'g-input icon-calendar',
            'itemClass' => 'width50',
            'value' => date('Y-m-d', $end)
        ));
        // end_time
        $groups->add('time', array(
            'id' => 'end_time',
            'label' => '{LNG_End time}',
            'labelClass' => 'g-input icon-clock',
            'itemClass' => 'width50',
            'value' => date('H:i', $end)
        ));
        // ตัวเลือก checkbox
        $category = \Car\Category\Model::init();
        foreach (Language::get('CAR_OPTIONS', []) as $key => $label) {
            if (!$category->isEmpty($key)) {
                $fieldset->add('checkboxgroups', array(
                    'id' => $key,
                    'labelClass' => 'g-input icon-list',
                    'itemClass' => 'item',
                    'label' => $label,
                    'options' => $category->toSelect($key),
                    'value' => isset($index->{$key}) ? explode(',', $index->{$key}) : []
                ));
            }
        }
        // comment
        $fieldset->add('textarea', array(
            'id' => 'comment',
            'labelClass' => 'g-input icon-file',
            'itemClass' => 'item',
            'label' => '{LNG_Other}',
            'rows' => 3,
            'value' => $index->comment
        ));
        $groups = $fieldset->add('groups');
        // status
        $groups->add('select', array(
            'id' => 'status',
            'labelClass' => 'g-input icon-star0',
            'itemClass' => 'width50',
            'label' => '{LNG_Status}',
            'options' => Language::get('BOOKING_STATUS'),
            'disabled' => true,
            'value' => $index->status
        ));
        // chauffeur
        $groups->add('select', array(
            'id' => 'chauffeur',
            'labelClass' => 'g-input icon-customer',
            'itemClass' => 'width50',
            'label' => '{LNG_Chauffeur}',
            'options' => array(-1 => '{LNG_Do not want} ({LNG_Self drive})', 0 => '{LNG_Not specified} ({LNG_anyone})')+\Car\Chauffeur\Model::init($index->chauffeur)->toSelect(),
            'disabled' => true,
            'value' => $index->chauffeur
        ));
        // reason
        $fieldset->add('text', array(
            'id' => 'reason',
            'labelClass' => 'g-input icon-question',
            'itemClass' => 'item',
            'label' => '{LNG_Reason}',
            'disabled' => true,
            'value' => $index->reason
        ));
        if ($canApprove != 0) {
            $fieldset = $form->add('fieldset', array(
                'class' => 'submit'
            ));
            if ($canEdit) {
                // submit
                $fieldset->add('submit', array(
                    'class' => 'button blue large icon-save border',
                    'value' => '{LNG_Save}'
                ));
            }
            $booking_status = Language::get('BOOKING_STATUS');
            // อนุมัติ
            $fieldset->add('button', array(
                'class' => 'button save large icon-valid',
                'id' => 'change_status1',
                'value' => $booking_status[1]
            ));
            // ไม่อนุมัติ
            $fieldset->add('button', array(
                'class' => 'button red large icon-invalid',
                'id' => 'change_status2',
                'value' => $booking_status[2]
            ));
        }
        // id
        $fieldset->add('hidden', array(
            'id' => 'id',
            'value' => $index->id
        ));
        // Javascript
        $form->script('initCarApprove();');
        // คืนค่า HTML
        return $form->render();
    }
}
