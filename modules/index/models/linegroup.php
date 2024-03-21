<?php
/**
 * @filesource modules/index/models/linegroup.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Index\Linegroup;

/**
 * Line Group
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model
{
    /**
     * @var array
     */
    private $datas = [];

    /**
     * Query ข้อมูลจากฐานข้อมูล
     *
     * @return static
     */
    public static function create()
    {
        // Model
        $model = new static;
        // Query
        $query = \Kotchasan\Model::createQuery()
            ->select('id', 'name')
            ->from('line')
            ->order('name')
            ->toArray()
            ->cacheOn();
        foreach ($query->execute() as $item) {
            $model->datas[$item['id']] = $item['name'];
        }
        return $model;
    }

    /**
     * คืนค่ารายการที่เลือก ไม่พบคืนค่าว่าง
     *
     * @param string $id
     *
     * @return string
     */
    public function get($id)
    {
        return isset($this->datas[$id]) ? $this->datas[$id] : '';
    }

    /**
     * ลิสต์รายการ
     * สำหรับใส่ลงใน select
     *
     * @return array
     */
    public function toSelect()
    {
        return $this->datas;
    }
}
