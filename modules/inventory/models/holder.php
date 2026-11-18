<?php
/**
 * @filesource modules/inventory/models/holder.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Inventory\Holder;

/**
 * ผู้ถือครอง/ผู้ใช้งานครุภัณฑ์ (ตาราง inventory_user) — ความสามารถของ E-Office
 *
 * หนึ่งแถว = หน่วยย่อยหนึ่งชิ้นถูกมอบให้สมาชิกหนึ่งคน return_date ว่าง = ยังถือครองอยู่
 * การเปลี่ยนผู้ถือครองปิดแถวเดิม (ลงวันที่คืน) แล้วเปิดแถวใหม่ ประวัติจึงอยู่ครบ
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * ผู้ถือครองปัจจุบันของหน่วยย่อยที่ระบุ
     *
     * @param array $itemIds
     *
     * @return array [inventory_item_id => ['id', 'member_id', 'received_date', 'name']]
     */
    public static function activeMap(array $itemIds)
    {
        $ids = array_values(array_filter(array_map('intval', $itemIds)));
        if (empty($ids)) {
            return [];
        }
        $rows = static::createQuery()
            ->select('H.id', 'H.inventory_item_id', 'H.member_id', 'H.received_date', 'U.name')
            ->from('inventory_user H')
            ->join('user U', [['U.id', 'H.member_id']], 'LEFT')
            ->where([
                ['H.inventory_item_id', $ids],
                ['H.return_date', null]
            ])
            ->orderBy('H.id')
            ->execute(null, 'array')
            ->fetchAll();
        $result = [];
        foreach ($rows as $row) {
            // แถวล่าสุดชนะ (ข้อมูลเดิมที่เปิดซ้อนกันไว้)
            $result[(int) $row['inventory_item_id']] = $row;
        }

        return $result;
    }

    /**
     * ตั้งผู้ถือครองของหน่วยย่อย
     *
     * @param int    $itemId
     * @param int    $memberId     0 = ไม่มีผู้ถือครอง (คืนแล้ว)
     * @param string $receivedDate Y-m-d หรือว่าง (= วันนี้ เมื่อเป็นการมอบใหม่)
     */
    public static function assign($itemId, $memberId, $receivedDate = '')
    {
        $itemId = (int) $itemId;
        $memberId = (int) $memberId;
        if ($itemId <= 0) {
            return;
        }
        $db = static::createDB();
        $current = self::activeMap([$itemId]);
        $current = isset($current[$itemId]) ? $current[$itemId] : null;
        $receivedDate = preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', (string) $receivedDate) ? $receivedDate : '';
        if ($current && (int) $current['member_id'] === $memberId) {
            // คนเดิม — แก้ได้แค่วันที่รับ
            if ($receivedDate !== '' && $receivedDate !== (string) $current['received_date']) {
                $db->update('inventory_user', ['id', (int) $current['id']], ['received_date' => $receivedDate]);
            }

            return;
        }
        if ($current) {
            self::release($itemId);
        }
        if ($memberId > 0) {
            $db->insert('inventory_user', [
                'inventory_item_id' => $itemId,
                'member_id' => $memberId,
                'received_date' => $receivedDate === '' ? date('Y-m-d') : $receivedDate,
                'return_date' => null
            ]);
        }
    }

    /**
     * คืนครุภัณฑ์ (ปิดทุกแถวที่ยังเปิดอยู่ของหน่วยย่อยนี้)
     *
     * @param int $itemId
     */
    public static function release($itemId)
    {
        static::createDB()->update('inventory_user', [
            ['inventory_item_id', (int) $itemId],
            ['return_date', null]
        ], ['return_date' => date('Y-m-d H:i:s')]);
    }

    /**
     * ตัวเลือกสมาชิกที่ใช้งานอยู่ สำหรับช่องผู้ถือครอง
     *
     * @return array [['value' => '', 'text' => ...], ...]
     */
    public static function memberOptions()
    {
        $options = [['value' => '0', 'text' => '-']];
        $rows = static::createQuery()
            ->select('id', 'name')
            ->from('user')
            ->where(['active', 1])
            ->orderBy('name')
            ->execute(null, 'array')
            ->fetchAll();
        foreach ($rows as $row) {
            $options[] = ['value' => (string) $row['id'], 'text' => (string) $row['name']];
        }

        return $options;
    }

    /**
     * อุปกรณ์ที่สมาชิกถือครองอยู่ (หน้า "อุปกรณ์ของฉัน")
     *
     * @param array $params member_id · search · category_id/type_id/model_id
     *
     * @return \Kotchasan\QueryBuilder\QueryBuilder
     */
    public static function myDevices(array $params)
    {
        $where = [
            ['H.member_id', (int) $params['member_id']],
            ['H.return_date', null]
        ];
        foreach (['category_id', 'type_id', 'model_id'] as $key) {
            if (!empty($params[$key])) {
                $where[] = ['V.'.$key, $params[$key]];
            }
        }
        $query = static::createQuery()
            ->select(
                'I.id', 'I.inventory_id', 'I.product_no', 'V.topic', 'V.category_id', 'V.type_id', 'V.model_id',
                'V.is_active', 'H.received_date'
            )
            ->from('inventory_user H')
            ->join('inventory_items I', [['I.id', 'H.inventory_item_id']], 'INNER')
            ->join('inventory V', [['V.id', 'I.inventory_id']], 'INNER')
            ->where($where);
        if (!empty($params['search'])) {
            $search = '%'.$params['search'].'%';
            $query->where([
                ['V.topic', 'LIKE', $search],
                ['I.product_no', 'LIKE', $search]
            ], 'OR');
        }

        return $query;
    }

    /**
     * หน่วยย่อยที่สมาชิกแจ้งซ่อมได้ — ของที่ตัวเองถือครอง หรือของที่ไม่มีใครถือครอง
     * (เงื่อนไขเดียวกับ autocomplete ของระบบเดิม)
     *
     * @param int $memberId
     *
     * @return string SQL เงื่อนไขของ I.id (ใช้กับ whereRaw)
     */
    public static function repairableSql($memberId)
    {
        $table = \Kotchasan\DB::create()->getTableName('inventory_user');

        return '(NOT EXISTS (SELECT 1 FROM `'.$table.'` HX WHERE HX.`inventory_item_id` = `I`.`id` AND HX.`return_date` IS NULL)'
            .' OR EXISTS (SELECT 1 FROM `'.$table.'` HM WHERE HM.`inventory_item_id` = `I`.`id` AND HM.`return_date` IS NULL'
            .' AND HM.`member_id` = '.(int) $memberId.'))';
    }
}
