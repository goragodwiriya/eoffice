<?php
/**
 * @filesource modules/car/views/vehicles.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Car\Vehicles;

use Kotchasan\DataTable;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=car-vehicles
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
{
    /**
     * ตารางยานพาหนะ
     *
     * @param Request $request
     *
     * @return string
     */
    public function render(Request $request)
    {
        $category = \Car\Category\Model::init();
        $filter_sources = Language::get('CAR_SELECT', []);
        $filters = [];
        $params = [
            'published' => 1
        ];
        foreach ($filter_sources as $key => $label) {
            $params[$key] = $request->request($key)->topic();
            $filters[] = array(
                'name' => $key,
                'text' => $label,
                'options' => array('' => '{LNG_all items}') + $category->toSelect($key),
                'value' => $params[$key]
            );
        }
        // URL สำหรับส่งให้ตาราง
        $uri = $request->createUriWithGlobals(WEB_URL.'index.php');
        // ตาราง
        $table = new DataTable(array(
            /* Uri */
            'uri' => $uri,
            /* Model */
            'model' => \Car\Vehicles\Model::toDataTable($params),
            /* รายการต่อหน้า */
            'perPage' => $request->cookie('car_perPage', 30)->toInt(),
            /* ฟังก์ชั่นจัดรูปแบบการแสดงผลแถวของตาราง */
            'onRow' => array($this, 'onRow'),
            /* คอลัมน์ที่ไม่ต้องแสดงผล */
            'hideColumns' => array('detail', 'color'),
            /* ตั้งค่าการกระทำของของตัวเลือกต่างๆ ด้านล่างตาราง ซึ่งจะใช้ร่วมกับการขีดถูกเลือกแถว */
            'action' => 'index.php/car/model/vehicles/action',
            'actionCallback' => 'dataTableActionCallback',
            /* ตัวเลือกด้านบนของตาราง ใช้จำกัดผลลัพท์การ query */
            'filters' => $filters,
            /* ส่วนหัวของตาราง และการเรียงลำดับ (thead) */
            'headers' => array(
                'id' => array(
                    'text' => ''
                ),
                'number' => array(
                    'text' => '{LNG_Vehicle}',
                    'sort' => 'number'
                ),
                'seats' => array(
                    'text' => '{LNG_Number of seats}',
                    'class' => 'center',
                    'sort' => 'seats'
                )
            ),
            /* รูปแบบการแสดงผลของคอลัมน์ (tbody) */
            'cols' => array(
                'number' => array(
                    'class' => 'top'
                ),
                'seats' => array(
                    'class' => 'center'
                )
            ),
            /* ปุ่มแสดงในแต่ละแถว */
            'buttons' => array(
                'car' => array(
                    'class' => 'icon-addtocart button blue',
                    'id' => ':id',
                    'text' => '{LNG_Book a vehicle}'
                ),
                'detail' => array(
                    'class' => 'icon-info button orange',
                    'id' => ':id',
                    'text' => '{LNG_Detail}'
                )
            )
        ));
        // save cookie
        setcookie('car_perPage', $table->perPage, time() + 2592000, '/', HOST, HTTPS, true);
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
     * @return array
     */
    public function onRow($item, $o, $prop)
    {
        $item['number'] = '<span class="term" style="background-color:'.$item['color'].'">'.$item['number'].'</span><p class=cuttext>'.strip_tags($item['detail']).'</p>';
        $thumb = is_file(ROOT_PATH.DATA_FOLDER.'car/'.$item['id'].'.jpg') ? WEB_URL.DATA_FOLDER.'car/'.$item['id'].'.jpg' : WEB_URL.'modules/car/img/noimage.png';
        $item['id'] = '<img src="'.$thumb.'" style="max-height:4em;max-width:8em;" alt=thumbnail>';
        return $item;
    }
}
