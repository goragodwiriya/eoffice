<?php
/**
 * @filesource modules/repair/models/autocomplete.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Repair\Autocomplete;

/**
 * ค้นหาพัสดุสำหรับ autocomplete
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * ค้นหาพัสดุที่ยังใช้งานอยู่จากชื่อพัสดุหรือเลขครุภัณฑ์
     * คืนค่ารูปแบบ [{value, text, topic, product_no}]
     *
     * @param string $search
     * @param int $limit
     *
     * @return array
     */
    public static function find($search, $limit = 20, $memberId = 0)
    {
        if ($search === '') {
            return [];
        }

        $keyword = '%'.$search.'%';
        $result = static::createQuery()
            ->select('V.topic', 'I.product_no')
            ->from('inventory V')
            ->join('inventory_items I', [['I.inventory_id', 'V.id']], 'INNER')
            ->where([['V.is_active', 1]])
            ->where([
                ['V.topic', 'LIKE', $keyword],
                ['I.product_no', 'LIKE', $keyword]
            ], 'OR');
        // E-Office : แจ้งซ่อมได้เฉพาะเครื่องที่ตัวเองถือครอง หรือเครื่องที่ยังไม่มีผู้ถือครอง
        if ($memberId > 0 && class_exists('\\Inventory\\Holder\\Model')) {
            $result->whereRaw(\Inventory\Holder\Model::repairableSql($memberId));
        }
        $result = $result->orderBy('V.topic', 'I.product_no')
            ->limit($limit)
            ->fetchAll(true);

        $datas = [];
        foreach ($result as $item) {
            $datas[] = [
                'value' => $item['product_no'],
                'text' => $item['product_no'].' : '.$item['topic'],
                'topic' => $item['topic'],
                'product_no' => $item['product_no']
            ];
        }

        return $datas;
    }
}
