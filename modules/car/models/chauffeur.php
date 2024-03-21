<?php
/**
 * @filesource modules/car/models/chauffeur.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Car\Chauffeur;

/**
 * รายชื่อคนขับรถ
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * @var array
     */
    private $datas = [];

    /**
     * อ่านรายชื่อคนขับรถจากฐานข้อมูล
     * สำหรับการแสดงผล
     *
     * @param int $id
     *
     * @return static
     */
    public static function init($id = 0)
    {
        // Model
        $model = new static;
        // Query
        $query = $model->db()->createQuery()
            ->select('id', 'name')
            ->from('user')
            ->where(array(
                array('status', self::$cfg->chauffeur_status),
                array('active', 1)
            ))
            ->cacheOn();
        if ($id > 0) {
            $query->orWhere(array('id', $id));
        }
        foreach ($query->execute() as $item) {
            $model->datas[$item->id] = $item->name;
        }
        return $model;
    }

    /**
     * ลิสต์รายการ คนขับรถ
     * สำหรับใส่ลงใน select
     *
     * @return array
     */
    public function toSelect()
    {
        $result = [];
        foreach ($this->datas as $id => $item) {
            $result[$id] = $item;
        }
        return $result;
    }

    /**
     * อ่านชื่อ คนขับรถ จาก $id
     * ไม่พบ คืนค่าว่าง
     *
     * @param int $id
     *
     * @return string
     */
    public function get($id)
    {
        return isset($this->datas[$id]) ? $this->datas[$id] : '';
    }
}
