<?php
/**
 * @filesource modules/repair/views/settings.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Repair\Settings;

use Kotchasan\Html;
use Kotchasan\Language;

/**
 * module=repair-settings
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
{
    /**
     * ตั้งค่าโมดูล
     *
     * @return string
     */
    public function render()
    {
        $form = Html::create('form', array(
            'id' => 'setup_frm',
            'class' => 'setup_frm',
            'autocomplete' => 'off',
            'action' => 'index.php/repair/model/settings/submit',
            'onsubmit' => 'doFormSubmit',
            'ajax' => true,
            'token' => true
        ));
        $fieldset = $form->add('fieldset', array(
            'titleClass' => 'icon-config',
            'title' => '{LNG_Module settings}'
        ));
        // สถานะการซ่อม
        $statuses = \Repair\Status\Model::create();
        // repair_first_status
        $fieldset->add('select', array(
            'id' => 'repair_first_status',
            'labelClass' => 'g-input icon-tools',
            'itemClass' => 'item',
            'label' => '{LNG_Initial repair status}',
            'options' => $statuses->toSelect(),
            'value' => isset(self::$cfg->repair_first_status) ? self::$cfg->repair_first_status : 1
        ));
        // repair_status_success
        $fieldset->add('select', array(
            'id' => 'repair_status_success',
            'labelClass' => 'g-input icon-star0',
            'itemClass' => 'item',
            'label' => '{LNG_Repair status successful}',
            'options' => $statuses->toSelect(),
            'value' => isset(self::$cfg->repair_status_success) ? self::$cfg->repair_status_success : 7
        ));
        $comment = '{LNG_Prefix, if changed The number will be counted again. You can enter %Y%M (year, month).}';
        $comment .= ', {LNG_Number such as %04d (%04d means 4 digits, maximum 11 digits)}';
        $groups = $fieldset->add('groups', array(
            'comment' => $comment
        ));
        // repair_prefix
        $groups->add('text', array(
            'id' => 'repair_prefix',
            'labelClass' => 'g-input icon-number',
            'itemClass' => 'width50',
            'label' => '{LNG_Prefix}',
            'placeholder' => 'JOB%Y%M-',
            'value' => isset(self::$cfg->repair_prefix) ? self::$cfg->repair_prefix : ''
        ));
        // repair_job_no
        $groups->add('text', array(
            'id' => 'repair_job_no',
            'labelClass' => 'g-input icon-number',
            'itemClass' => 'width50',
            'label' => '{LNG_Job No.}',
            'placeholder' => '%04d, JOB%04d',
            'value' => isset(self::$cfg->repair_job_no) ? self::$cfg->repair_job_no : 'JOB%04d'
        ));
        $fieldset = $form->add('fieldset', array(
            'titleClass' => 'icon-comments',
            'title' => '{LNG_Notification}'
        ));
        // repair_send_mail
        $booleans = Language::get('BOOLEANS');
        $fieldset->add('select', array(
            'id' => 'repair_send_mail',
            'labelClass' => 'g-input icon-email',
            'itemClass' => 'item',
            'label' => '{LNG_Emailing}',
            'comment' => '{LNG_Send notification messages When making a transaction}',
            'options' => $booleans,
            'value' => isset(self::$cfg->repair_send_mail) ? self::$cfg->repair_send_mail : 1
        ));
        // repair_line_id
        $fieldset->add('select', array(
            'id' => 'repair_line_id',
            'itemClass' => 'item',
            'label' => '{LNG_LINE group account}',
            'labelClass' => 'g-input icon-line',
            'comment' => '{LNG_Send notification to LINE group when making a transaction}',
            'options' => array(0 => $booleans[0])+\Index\Linegroup\Model::create()->toSelect(),
            'value' => isset(self::$cfg->repair_line_id) ? self::$cfg->repair_line_id : 0
        ));
        $fieldset = $form->add('fieldset', array(
            'class' => 'submit'
        ));
        // submit
        $fieldset->add('submit', array(
            'class' => 'button save large icon-save',
            'value' => '{LNG_Save}'
        ));
        // คืนค่า HTML
        return $form->render();
    }
}
