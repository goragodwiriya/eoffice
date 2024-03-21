<?php
/**
 * @filesource modules/repair/views/history.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Repair\History;

use Gcms\Login;
use Kotchasan\DataTable;
use Kotchasan\Date;
use Kotchasan\Http\Request;

/**
 * module=repair-history
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
{
    /**
     * @var obj
     */
    private $statuses;
    /**
     * @var obj
     */
    private $operators;

    /**
     * ประวัติการแจ้งซ่อม
     *
     * @param Request $request
     * @param array   $login
     *
     * @return string
     */
    public function render(Request $request, $login)
    {
        $params = array(
            'customer_id' => $login['id'],
            'status' => $request->request('status', -1)->toInt()
        );
        // สถานะการซ่อม
        $this->statuses = \Repair\Status\Model::create();
        // รายชื่อช่างซ่อม
        $this->operators = \Repair\Operator\Model::create();
        // URL สำหรับส่งให้ตาราง
        $uri = self::$request->createUriWithGlobals(WEB_URL.'index.php');
        // ตาราง
        $table = new DataTable(array(
            /* Uri */
            'uri' => $uri,
            /* Model */
            'model' => \Repair\History\Model::toDataTable($params),
            /* แบ่งหน้า */
            'perPage' => $request->cookie('repairHistory_perPage', 30)->toInt(),
            /* เรียงลำดับ */
            'sort' => $request->cookie('repairHistory_sort', 'create_date desc')->toString(),
            /* ฟังก์ชั่นจัดรูปแบบการแสดงผลแถวของตาราง */
            'onRow' => array($this, 'onRow'),
            /* คอลัมน์ที่ไม่ต้องแสดงผล */
            'hideColumns' => array('id'),
            /* คอลัมน์ที่สามารถค้นหาได้ */
            'searchColumns' => array('topic'),
            /* ตัวเลือกด้านบนของตาราง ใช้จำกัดผลลัพท์การ query */
            'filters' => array(
                array(
                    'name' => 'status',
                    'text' => '{LNG_Repair status}',
                    'options' => array(-1 => '{LNG_all items}') + $this->statuses->toSelect(),
                    'value' => $params['status']
                )
            ),
            /* ส่วนหัวของตาราง และการเรียงลำดับ (thead) */
            'headers' => array(
                'job_id' => array(
                    'text' => '{LNG_Job No.}'
                ),
                'topic' => array(
                    'text' => '{LNG_Equipment}'
                ),
                'create_date' => array(
                    'text' => '{LNG_Received date}',
                    'class' => 'center',
                    'sort' => 'create_date'
                ),
                'operator_id' => array(
                    'text' => '{LNG_Operator}',
                    'class' => 'center'
                ),
                'status' => array(
                    'text' => '{LNG_Repair status}',
                    'class' => 'center',
                    'sort' => 'status'
                ),
                'comment' => array(
                    'text' => '{LNG_Comment}'
                )
            ),
            /* รูปแบบการแสดงผลของคอลัมน์ (tbody) */
            'cols' => array(
                'topic' => array(
                    'class' => 'nowrap'
                ),
                'create_date' => array(
                    'class' => 'center nowrap'
                ),
                'operator_id' => array(
                    'class' => 'center nowrap'
                ),
                'status' => array(
                    'class' => 'center'
                ),
                'comment' => array(
                    'class' => 'topic'
                )
            ),
            /* ปุ่มแสดงในแต่ละแถว */
            'buttons' => array(
                'print' => array(
                    'class' => 'icon-print button print',
                    'href' => WEB_URL.'modules/repair/print.php?id=:id',
                    'target' => 'print',
                    'title' => '{LNG_Print} {LNG_Repair receipt}'
                ),
                'description' => array(
                    'class' => 'icon-report button purple',
                    'href' => $uri->createBackUri(array('module' => 'repair-detail', 'id' => ':id')),
                    'title' => '{LNG_Repair job description}'
                )
            )
        ));
        // save cookie
        setcookie('repairHistory_perPage', $table->perPage, time() + 2592000, '/', HOST, HTTPS, true);
        setcookie('repairHistory_sort', $table->sort, time() + 2592000, '/', HOST, HTTPS, true);
        // คืนค่า HTML
        return $table->render();
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
        $item['create_date'] = Date::format($item['create_date'], 'd M Y');
        $item['status'] = '<mark class=term style="background-color:'.$this->statuses->getColor($item['status']).'">'.$this->statuses->get($item['status']).'</mark>';
        $item['operator_id'] = $this->operators->get($item['operator_id']);
        $item['comment'] = '<span class=two_lines title="'.$item['comment'].'">'.$item['comment'].'</span>';
        return $item;
    }
}
