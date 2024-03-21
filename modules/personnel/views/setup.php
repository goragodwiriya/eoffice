<?php
/**
 * @filesource modules/personnel/views/setup.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Personnel\Setup;

use Gcms\Login;
use Kotchasan\DataTable;
use Kotchasan\Http\Request;

/**
 * module=personnel-setup
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
{
    /**
     * @var object
     */
    private $category;
    /**
     * @var array
     */
    private $login;

    /**
     * แสดงรายการบุคลากร สำหรับผู้ดูแล.
     *
     * @param Request $request
     * @param array   $login
     *
     * @return string
     */
    public function render(Request $request, $login)
    {
        $this->login = Login::checkPermission($login, 'can_manage_personnel') ? true : $login;
        // หมวดหมู่
        $this->category = \Index\Category\Model::init(true);
        // URL สำหรับส่งให้ตาราง
        $uri = $request->createUriWithGlobals(WEB_URL.'index.php');
        // ตาราง
        $table = new DataTable(array(
            /* Uri */
            'uri' => $uri,
            /* Model */
            'model' => \Personnel\Setup\Model::toDataTable(),
            /* รายการต่อหน้า */
            'perPage' => $request->cookie('personSetup_perPage', 30)->toInt(),
            /* เรียงลำดับ */
            'sort' => $request->cookie('personSetup_sort', 'id DESC')->toString(),
            /* ฟังก์ชั่นจัดรูปแบบการแสดงผลแถวของตาราง */
            'onRow' => array($this, 'onRow'),
            /* ตั้งค่าการกระทำของของตัวเลือกต่างๆ ด้านล่างตาราง ซึ่งจะใช้ร่วมกับการขีดถูกเลือกแถว */
            'action' => 'index.php/personnel/model/setup/action',
            'actionCallback' => 'dataTableActionCallback',
            /* คอลัมน์ที่สามารถค้นหาได้ */
            'searchColumns' => array('name', 'phone'),
            /* ตัวเลือกด้านบนของตาราง ใช้จำกัดผลลัพท์การ query */
            'filters' => array(
                'department' => array(
                    'name' => 'department',
                    'default' => 0,
                    'text' => '{LNG_Department}',
                    'options' => array(0 => '{LNG_all items}') + $this->category->toSelect('department'),
                    'value' => $request->request('department')->toInt()
                ),
                'position' => array(
                    'name' => 'position',
                    'default' => 0,
                    'text' => '{LNG_Position}',
                    'options' => array(0 => '{LNG_all items}') + $this->category->toSelect('position'),
                    'value' => $request->request('position')->toInt()
                )
            ),
            /* ส่วนหัวของตาราง และการเรียงลำดับ (thead) */
            'headers' => array(
                'name' => array(
                    'text' => '{LNG_Name}',
                    'sort' => 'name'
                ),
                'phone' => array(
                    'text' => '{LNG_Phone}',
                    'class' => 'center'
                ),
                'department' => array(
                    'text' => '{LNG_Department}',
                    'class' => 'center',
                    'sort' => 'department'
                ),
                'position' => array(
                    'text' => '{LNG_Position}',
                    'class' => 'center',
                    'sort' => 'position'
                ),
                'id' => array(
                    'text' => '{LNG_Image}',
                    'class' => 'center'
                )
            ),
            /* รูปแบบการแสดงผลของคอลัมน์ (tbody) */
            'cols' => array(
                'phone' => array(
                    'class' => 'center'
                ),
                'department' => array(
                    'class' => 'center'
                ),
                'position' => array(
                    'class' => 'center'
                ),
                'id' => array(
                    'class' => 'center'
                )
            ),
            /* ฟังก์ชั่นตรวจสอบการแสดงผลปุ่มในแถว */
            'onCreateButton' => array($this, 'onCreateButton'),
            /* ปุ่มแสดงในแต่ละแถว */
            'buttons' => array(
                'view' => array(
                    'class' => 'icon-info button brown notext',
                    'id' => ':id',
                    'title' => '{LNG_Details of} {LNG_Personnel}'
                ),
                'edit' => array(
                    'class' => 'icon-edit button green notext',
                    'href' => $uri->createBackUri(array('module' => 'personnel-write', 'id' => ':id')),
                    'title' => '{LNG_Edit}'
                )
            )
        ));
        // save cookie
        setcookie('personSetup_perPage', $table->perPage, time() + 2592000, '/', HOST, HTTPS, true);
        setcookie('personSetup_sort', $table->sort, time() + 2592000, '/', HOST, HTTPS, true);
        // คืนค่า HTML
        return $table->render();
    }

    /**
     * จัดรูปแบบการแสดงผลในแต่ละแถว
     *
     * @param array $item
     *
     * @return array
     */
    public function onRow($item, $o, $prop)
    {
        $item['position'] = $this->category->get('position', $item['position']);
        $item['department'] = $this->category->get('department', $item['department']);
        if (is_file(ROOT_PATH.DATA_FOLDER.'personnel/'.$item['id'].'.jpg')) {
            $item['id'] = '<img src="'.WEB_URL.DATA_FOLDER.'personnel/'.$item['id'].'.jpg'.'" style="max-height:50px" alt=thumbnail>';
        } else {
            $item['id'] = '<img src="'.WEB_URL.'modules/personnel/img/noimage.jpg" style="max-height:50px" alt=thumbnail>';
        }
        $item['phone'] = self::showPhone($item['phone']);
        return $item;
    }

    /**
     * ฟังกชั่นตรวจสอบว่าสามารถสร้างปุ่มได้หรือไม่
     *
     * @param string $btn
     * @param array $attributes
     * @param array $item
     *
     * @return array
     */
    public function onCreateButton($btn, $attributes, $item)
    {
        if ($btn == 'edit') {
            return $this->login === true || $this->login['id'] == $item['id'] ? $attributes : false;
        }
        return $attributes;
    }
}
