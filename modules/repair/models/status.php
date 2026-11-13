<?php
/**
 * @filesource modules/repair/models/status.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Repair\Status;

/**
 * สถานะการซ่อม เก็บอยู่ในตาราง category (type = repairstatus)
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * ประเภทหมวดหมู่ของสถานะการซ่อม
     */
    const TYPE = 'repairstatus';

    /**
     * อ่านสถานะการซ่อมทั้งหมด
     *
     * @param bool $activeOnly true (default) เฉพาะที่เปิดใช้งาน
     *
     * @return array
     */
    public static function all($activeOnly = true)
    {
        $where = [['type', self::TYPE]];
        if ($activeOnly) {
            $where[] = ['is_active', 1];
        }

        return static::createQuery()
            ->select('category_id', 'topic', 'color', 'is_active')
            ->from('category')
            ->where($where)
            ->orderBy('category_id')
            ->fetchAll(true);
    }

    /**
     * สถานะการซ่อมสำหรับใส่ลงใน select
     *
     * @param bool $activeOnly
     *
     * @return array
     */
    public static function toOptions($activeOnly = true)
    {
        $result = [];
        foreach (self::all($activeOnly) as $item) {
            $result[] = [
                'value' => $item['category_id'],
                'text' => $item['topic']
            ];
        }

        return $result;
    }

    /**
     * สถานะการซ่อมในรูปแบบ [category_id => ['topic' => .., 'color' => ..]]
     *
     * @param bool $activeOnly
     *
     * @return array
     */
    public static function map($activeOnly = true)
    {
        $result = [];
        foreach (self::all($activeOnly) as $item) {
            $result[$item['category_id']] = [
                'topic' => $item['topic'],
                'color' => $item['color']
            ];
        }

        return $result;
    }

    /**
     * บันทึกสถานะการซ่อมทั้งหมด (ลบของเดิมแล้วเพิ่มใหม่)
     *
     * @param array $items
     *
     * @return int
     */
    public static function save($items)
    {
        $db = \Kotchasan\DB::create();
        $db->delete('category', [['type', self::TYPE]], 0);

        foreach ($items as $item) {
            $item['type'] = self::TYPE;
            $item['language'] = '';
            $db->insert('category', $item);
        }

        return count($items);
    }
}
