<?php
/**
 * @filesource modules/telegramgroup/models/group.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Telegramgroup\Group;

use Kotchasan\Language;

/**
 * กลุ่ม Telegram ที่รับข้อความแจ้งเตือน
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * โมดูลที่ส่งแจ้งเตือนเข้ากลุ่มได้ [ชื่อโมดูล => ข้อความ] เฉพาะโมดูลที่ติดตั้งอยู่
     *
     * @return array
     */
    public static function modules()
    {
        $result = [];
        foreach ([
            'booking' => 'Book a room',
            'car' => 'Book a vehicle',
            'repair' => 'Repair jobs',
            'edocument' => 'E-Document'
        ] as $module => $label) {
            if (is_dir(ROOT_PATH.'modules/'.$module)) {
                $result[$module] = Language::get($label);
            }
        }

        return $result;
    }

    /**
     * @param int $id 0 = รายการใหม่
     *
     * @return object|null
     */
    public static function get($id)
    {
        if ($id <= 0) {
            return (object) [
                'id' => 0,
                'name' => '',
                'department' => '',
                'chat_id' => '',
                'modules' => array_keys(self::modules()),
                'is_active' => 1
            ];
        }
        $row = static::createQuery()
            ->from('telegram_group')
            ->where(['id', (int) $id])
            ->first();
        if (!$row) {
            return null;
        }
        $row->department = (string) $row->department;
        $row->modules = array_values(array_filter(explode(',', (string) $row->modules)));

        return $row;
    }

    /**
     * ส่งข้อความเข้ากลุ่ม Telegram ที่รับโมดูลนี้ และ (ไม่ระบุแผนก หรือ แผนกตรงกับรายการ)
     *
     * @param string       $module      booking | car | repair | edocument
     * @param string|array $departments แผนกของรายการ (ว่าง = ส่งเฉพาะกลุ่มที่รับทุกแผนก)
     * @param string       $message
     *
     * @return string ข้อความผิดพลาด หรือว่าง
     */
    public static function send($module, $departments, $message)
    {
        if (empty(self::$cfg->telegram_bot_token) || trim((string) $message) === '') {
            return '';
        }
        $chats = self::chatIds($module, $departments);

        return empty($chats) ? '' : \Gcms\Telegram::sendTo($chats, $message);
    }

    /**
     * รหัสแชทของกลุ่มที่ต้องได้รับข้อความของโมดูลนี้
     *
     * @param string       $module
     * @param string|array $departments
     *
     * @return array
     */
    public static function chatIds($module, $departments)
    {
        $departments = array_values(array_filter(array_map('strval', (array) $departments), function ($v) {
            return $v !== '' && $v !== '0';
        }));
        $chats = [];
        $rows = static::createQuery()
            ->select('chat_id', 'department')
            ->from('telegram_group')
            ->where([
                ['is_active', 1],
                ['chat_id', '!=', ''],
                ['modules', 'LIKE', '%,'.$module.',%']
            ])
            ->execute(null, 'array')
            ->fetchAll();
        foreach ($rows as $row) {
            if ((string) $row['department'] === '' || in_array((string) $row['department'], $departments, true)) {
                $chats[$row['chat_id']] = $row['chat_id'];
            }
        }

        return array_values($chats);
    }
}
