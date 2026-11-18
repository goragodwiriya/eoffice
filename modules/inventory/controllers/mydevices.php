<?php
/**
 * @filesource modules/inventory/controllers/mydevices.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Inventory\Mydevices;

use Kotchasan\Http\Request;

/**
 * GET api/inventory/mydevices — อุปกรณ์ของฉัน (ระบบเดิม module=inventory)
 * ครุภัณฑ์ที่สมาชิกที่เข้าระบบถือครองอยู่ สมาชิกทุกคนเปิดได้ เห็นเฉพาะของตัวเอง
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Table
{
    /**
     * @var array
     */
    protected $allowedSortColumns = ['id', 'topic', 'product_no', 'received_date', 'category_id', 'type_id', 'model_id'];

    /**
     * @var \Gcms\Category|null
     */
    private $categories;

    /**
     * @param Request $request
     * @param object|null $login
     *
     * @return mixed
     */
    protected function checkAuthorization(Request $request, $login)
    {
        return $login ? true : $this->errorResponse('Forbidden', 403);
    }

    /**
     * @param Request $request
     * @param object|null $login
     *
     * @return array
     */
    protected function getCustomParams(Request $request, $login): array
    {
        return [
            'member_id' => (int) $login->id,
            'category_id' => $request->get('category_id')->topic(),
            'type_id' => $request->get('type_id')->topic(),
            'model_id' => $request->get('model_id')->topic()
        ];
    }

    /**
     * @param array $params
     * @param object|null $login
     *
     * @return \Kotchasan\QueryBuilder\QueryBuilder
     */
    protected function toDataTable($params, $login = null)
    {
        return \Inventory\Holder\Model::myDevices($params);
    }

    /**
     * @param array $params
     * @param object|null $login
     *
     * @return array
     */
    protected function getFilters($params, $login = null)
    {
        $categories = $this->categories();

        return [
            'category_id' => $categories->toOptions('category_id'),
            'type_id' => $categories->toOptions('type_id'),
            'model_id' => $categories->toOptions('model_id')
        ];
    }

    /**
     * @param array $datas
     * @param object|null $login
     *
     * @return array
     */
    protected function formatDatas(array $datas, $login = null): array
    {
        $categories = $this->categories();
        foreach ($datas as $item) {
            $item->image = \Inventory\Product\Model::imageUrl($item->inventory_id);
            $item->barcode = \Inventory\Items\Model::barcodeImage((string) $item->product_no);
            foreach (['category_id', 'type_id', 'model_id'] as $key) {
                $item->{$key.'_text'} = $categories->get($key, $item->$key, '');
            }
            $item->status_text = \Kotchasan\Language::get('INVENTORY_STATUS', '', (int) $item->is_active);
        }

        return $datas;
    }

    /**
     * @return \Gcms\Category
     */
    private function categories()
    {
        if ($this->categories === null) {
            $this->categories = \Inventory\Category\Controller::init();
        }

        return $this->categories;
    }
}
