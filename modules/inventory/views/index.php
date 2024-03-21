<?php
/**
 * @filesource modules/inventory/views/index.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Inventory\Index;

use Kotchasan\DataTable;
use Kotchasan\Date;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=inventory
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
     * ตารางอุปกรณ์ของฉัน
     *
     * @param Request $request
     * @param array   $login
     *
     * @return string
     */
    public function render(Request $request, $login)
    {
        $this->inventory_status = Language::get('INVENTORY_STATUS');
        $fields = array('id', 'topic', 'product_no');
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
            'status' => array('class' => 'center')
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
        $fields[] = 'status';
        $headers['status'] = array(
            'text' => '',
            'class' => 'center notext',
            'sort' => 'status'
        );
        $fields[] = 'create_date';
        $headers['create_date'] = array(
            'text' => '{LNG_Received date}',
            'class' => 'center',
            'sort' => 'create_date'
        );
        $cols['create_date'] = array('class' => 'center');
        // URL สำหรับส่งให้ตาราง
        $uri = $request->createUriWithGlobals(WEB_URL.'index.php');
        // ตาราง
        $table = new DataTable(array(
            /* Uri */
            'uri' => $uri,
            /* Model */
            'model' => \Inventory\Index\Model::toDataTable($login['id']),
            /* ฟิลด์ที่กำหนด (หากแตกต่างจาก Model) */
            'fields' => $fields,
            /* รายการต่อหน้า */
            'perPage' => $request->cookie('inventory_perPage', 30)->toInt(),
            /* เรียงลำดับ */
            'sort' => $request->cookie('inventory_sort', 'id desc')->toString(),
            /* ฟังก์ชั่นจัดรูปแบบการแสดงผลแถวของตาราง */
            'onRow' => array($this, 'onRow'),
            /* คอลัมน์ที่สามารถค้นหาได้ */
            'searchColumns' => array('topic', 'product_no'),
            /* ตัวเลือกด้านบนของตาราง ใช้จำกัดผลลัพท์การ query */
            'filters' => $filters,
            /* ส่วนหัวของตาราง และการเรียงลำดับ (thead) */
            'headers' => $headers,
            /* รูปแบบการแสดงผลของคอลัมน์ (tbody) */
            'cols' => $cols
        ));
        // save cookie
        setcookie('inventory_perPage', $table->perPage, time() + 2592000, '/', HOST, HTTPS, true);
        setcookie('inventory_sort', $table->sort, time() + 2592000, '/', HOST, HTTPS, true);
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
        $item['status'] = '<span class="icon-valid '.($item['status'] == 0 ? 'disabled' : 'access').'" title="'.$this->inventory_status[$item['status']].'"></span>';
        $thumb = is_file(ROOT_PATH.DATA_FOLDER.'inventory/'.$item['id'].'.jpg') ? WEB_URL.DATA_FOLDER.'inventory/'.$item['id'].'.jpg' : WEB_URL.'modules/inventory/img/noimage.png';
        $item['id'] = '<img src="'.$thumb.'" style="max-height:50px;max-width:50px" alt=thumbnail>';
        $item['create_date'] = Date::format($item['create_date'], 'd M Y');
        return $item;
    }
}
