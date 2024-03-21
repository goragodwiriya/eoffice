<?php
/**
 * @filesource modules/repair/views/detail.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Repair\Detail;

use Gcms\Login;
use Kotchasan\DataTable;
use Kotchasan\Date;
use Kotchasan\Template;

/**
 * module=repair-detail
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
{
    /**
     * @var mixed
     */
    private $statuses;

    /**
     * แสดงรายละเอียดการซ่อม
     *
     * @param object $index
     * @param array $login
     *
     * @return string
     */
    public function render($index, $login)
    {
        // สถานะการซ่อม
        $this->statuses = \Repair\Status\Model::create();
        // อ่านสถานะการทำรายการทั้งหมด
        $statuses = \Repair\Detail\Model::getAllStatus($index->id);
        // URL สำหรับส่งให้ตาราง
        $uri = self::$request->createUriWithGlobals(WEB_URL.'index.php');
        // ตาราง
        $table = new DataTable(array(
            /* Uri */
            'uri' => $uri,
            /* array datas */
            'datas' => $statuses,
            /* ฟังก์ชั่นจัดรูปแบบการแสดงผลแถวของตาราง */
            'onRow' => array($this, 'onRow'),
            /* คอลัมน์ที่ไม่ต้องแสดงผล */
            'hideColumns' => array('id'),
            /* ตั้งค่าการกระทำของของตัวเลือกต่างๆ ด้านล่างตาราง ซึ่งจะใช้ร่วมกับการขีดถูกเลือกแถว */
            'action' => 'index.php/repair/model/detail/action?repair_id='.$index->id,
            'actionCallback' => 'dataTableActionCallback',
            /* ส่วนหัวของตาราง และการเรียงลำดับ (thead) */
            'headers' => array(
                'name' => array(
                    'text' => '{LNG_Operator}'
                ),
                'status' => array(
                    'text' => '{LNG_Repair status}',
                    'class' => 'center'
                ),
                'create_date' => array(
                    'text' => '{LNG_Transaction date}',
                    'class' => 'center'
                ),
                'comment' => array(
                    'text' => '{LNG_Comment}'
                )
            ),
            /* รูปแบบการแสดงผลของคอลัมน์ (tbody) */
            'cols' => array(
                'status' => array(
                    'class' => 'center'
                ),
                'create_date' => array(
                    'class' => 'center'
                )
            )
        ));
        if (Login::checkPermission($login, array('can_manage_repair', 'can_repair'))) {
            /* ปุ่มแสดงในแต่ละแถว */
            $table->buttons = array(
                'delete' => array(
                    'class' => 'icon-delete button red notext',
                    'id' => ':id',
                    'title' => '{LNG_Delete}'
                )
            );
            // สามารถลบไฟล์แนบได้
            $canDelete = true;
        } else {
            // สามารถลบไฟล์แนบได้
            $canDelete = $index->status == self::$cfg->repair_first_status;
        }
        // template
        $template = Template::createFromFile(ROOT_PATH.'modules/repair/views/detail.html');
        $template->add(array(
            '/%NAME%/' => $index->name,
            '/%PHONE%/' => $index->phone,
            '/%TOPIC%/' => $index->topic,
            '/%PRODUCT_NO%/' => base64_encode(\Kotchasan\Barcode::create($index->product_no, 30, 9)->toPng()),
            '/%JOB_DESCRIPTION%/' => nl2br($index->job_description),
            '/%CREATE_DATE%/' => Date::format($index->create_date, 'd M Y'),
            '/%COMMENT%/' => $index->comment,
            '/%DETAILS%/' => $table->render(),
            '/%FILES%/' => \Repair\Download\Controller::files($index->id, $canDelete)
        ));
        // คืนค่า HTML
        return $template->render();
    }

    /**
     * จัดรูปแบบการแสดงผลในแต่ละแถว
     *
     * @param array  $item ข้อมูลแถว
     * @param int    $o    ID ของข้อมูล
     * @param object $prop กำหนด properties ของ TR
     *
     * @return array คืนค่า $item กลับไป
     */
    public function onRow($item, $o, $prop)
    {
        $item['comment'] = nl2br($item['comment']);
        $item['create_date'] = Date::format($item['create_date'], 'd M Y H:i');
        $item['status'] = '<mark class=term style="background-color:'.$this->statuses->getColor($item['status']).'">'.$this->statuses->get($item['status']).'</mark>';
        return $item;
    }
}
