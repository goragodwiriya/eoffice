<?php
/**
 * @filesource modules/inventory/views/setup.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Inventory\Setup;

use Kotchasan\DataTable;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=inventory-setup
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
{
    /**
     * @var array
     */
    private $typies = [];
    /**
     * @var object
     */
    private $category;
    /**
     * @var array
     */
    private $inventory_status;

    /**
     * ตาราง Inventory
     *
     * @param Request $request
     *
     * @return string
     */
    public function render(Request $request)
    {
        $this->inventory_status = Language::get('INVENTORY_STATUS');
        $fields = array('id', 'topic', 'product_no', 'unit');
        $headers = array(
            'id' => array(
                'text' => '{LNG_Image}',
                'sort' => 'id'
            ),
            'topic' => array(
                'text' => '{LNG_Equipment}',
                'sort' => 'topic'
            ),
            'product_no' => array(
                'text' => '{LNG_Serial/Registration No.}',
                'sort' => 'product_no'
            )
        );
        $cols = array(
            'status' => array('class' => 'center'),
            'stock' => array('class' => 'center')
        );
        $filters = [];
        $this->category = \Inventory\Category\Model::init();
        foreach (Language::get('INVENTORY_CATEGORIES') as $type => $text) {
            $this->typies[] = $type;
            $fields[] = $type;
            $headers[$type] = array(
                'text' => $text,
                'class' => 'center'
            );
            $cols[$type] = array('class' => 'center');
            $filters[$type] = array(
                'name' => $type,
                'default' => 0,
                'text' => $text,
                'options' => array(0 => '{LNG_all items}') + $this->category->toSelect($type),
                'value' => $request->request($type)->toInt()
            );
        }
        $fields[] = 'stock';
        $headers['stock'] = array(
            'text' => '{LNG_Stock}',
            'class' => 'center',
            'sort' => 'stock'
        );
        $fields[] = 'device_user';
        $headers['device_user'] = array(
            'text' => '{LNG_Device user}'
        );
        $fields[] = 'status';
        $headers['status'] = array(
            'text' => '',
            'class' => 'center notext',
            'sort' => 'status'
        );
        // URL สำหรับส่งให้ตาราง
        $uri = $request->createUriWithGlobals(WEB_URL.'index.php');
        // ตาราง
        $table = new DataTable(array(
            /* Uri */
            'uri' => $uri,
            /* Model */
            'model' => \Inventory\Setup\Model::toDataTable(),
            /* ฟิลด์ที่กำหนด (หากแตกต่างจาก Model) */
            'fields' => $fields,
            /* รายการต่อหน้า */
            'perPage' => $request->cookie('inventorySetup_perPage', 30)->toInt(),
            /* เรียงลำดับ */
            'sort' => $request->cookie('inventorySetup_sort', 'id desc')->toString(),
            /* ฟังก์ชั่นจัดรูปแบบการแสดงผลแถวของตาราง */
            'onRow' => array($this, 'onRow'),
            /* คอลัมน์ที่ไม่ต้องแสดงผล */
            'hideColumns' => array('unit'),
            /* คอลัมน์ที่สามารถค้นหาได้ */
            'searchColumns' => array('topic', 'product_no', 'device_user'),
            /* ตั้งค่าการกระทำของของตัวเลือกต่างๆ ด้านล่างตาราง ซึ่งจะใช้ร่วมกับการขีดถูกเลือกแถว */
            'action' => 'index.php/inventory/model/setup/action',
            'actionCallback' => 'dataTableActionCallback',
            'actions' => array(
                array(
                    'id' => 'action',
                    'class' => 'ok',
                    'text' => '{LNG_With selected}',
                    'options' => array(
                        'delete' => '{LNG_Delete}'
                    )
                )
            ),
            /* ตัวเลือกด้านบนของตาราง ใช้จำกัดผลลัพท์การ query */
            'filters' => $filters,
            /* ส่วนหัวของตาราง และการเรียงลำดับ (thead) */
            'headers' => $headers,
            /* รูปแบบการแสดงผลของคอลัมน์ (tbody) */
            'cols' => $cols,
            /* ปุ่มแสดงในแต่ละแถว */
            'buttons' => array(
                array(
                    'class' => 'icon-edit button green',
                    'href' => $uri->createBackUri(array('module' => 'inventory-write', 'id' => ':id')),
                    'text' => '{LNG_Edit}'
                )
            ),
            /* ปุ่มเพิ่ม */
            'addNew' => array(
                'class' => 'float_button icon-new',
                'href' => $uri->createBackUri(array('module' => 'inventory-write', 'id' => 0)),
                'title' => '{LNG_Add} {LNG_Equipment}'
            )
        ));
        // save cookie
        setcookie('inventorySetup_perPage', $table->perPage, time() + 2592000, '/', HOST, HTTPS, true);
        setcookie('inventorySetup_sort', $table->sort, time() + 2592000, '/', HOST, HTTPS, true);
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
        $item['product_no'] = '<img style="max-width:none" src="data:image/png;base64,'.base64_encode(\Kotchasan\Barcode::create($item['product_no'], 50, 9)->toPng()).'">';
        foreach ($this->typies as $key) {
            $item[$key] = $this->category->get($key, $item[$key]);
        }
        $item['device_user'] = $item['device_user'] === null ? '' : '<a href="index.php?module=inventory-setup&amp;search='.rawurldecode($item['device_user']).'">'.$item['device_user'].'</a>';
        $item['status'] = '<a id=status_'.$item['id'].' class="icon-valid '.($item['status'] == 0 ? 'disabled' : 'access').'" title="'.$this->inventory_status[$item['status']].'"></a>';
        $thumb = is_file(ROOT_PATH.DATA_FOLDER.'inventory/'.$item['id'].'.jpg') ? WEB_URL.DATA_FOLDER.'inventory/'.$item['id'].'.jpg' : WEB_URL.'modules/inventory/img/noimage.png';
        $item['stock'] .= ' '.$this->category->get('unit', $item['unit']);
        $item['id'] = '<img src="'.$thumb.'" style="max-height:50px;max-width:50px" alt=thumbnail>';
        return $item;
    }
}
