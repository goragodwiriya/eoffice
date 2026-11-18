<?php
/**
 * @filesource modules/repair/models/email.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Repair\Email;

use Kotchasan\Database\Sql;
use Kotchasan\Date;
use Kotchasan\Language;

/**
 * แจ้งเตือนการทำรายการซ่อมทางอีเมล LINE และ Telegram
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * ส่งข้อความแจ้งเตือนไปยังผู้ที่เกี่ยวข้อง
     *
     * @param int $repair_id
     *
     * @return string ข้อความผลลัพธ์
     */
    public static function send($repair_id)
    {
        $order = static::createQuery()
            ->select(
                'R.job_id',
                'R.product_no',
                'V.topic',
                'R.job_description',
                'R.created_at',
                'R.customer_id',
                'C.topic status_text',
                'S.comment',
                'S.operator_id',
                'S.status'
            )
            ->from('repair R')
            ->join([\Repair\Jobs\Model::latestStatusQuery(), 'T'], [['T.repair_id', 'R.id']], 'LEFT')
            ->join('repair_status S', [['S.id', 'T.max_id']], 'LEFT')
            ->join('inventory_items I', [['I.product_no', 'R.product_no']], 'LEFT')
            ->join('inventory V', [['V.id', 'I.inventory_id']], 'LEFT')
            ->join('category C', [['C.category_id', 'S.status'], ['C.type', \Repair\Status\Model::TYPE]], 'LEFT')
            ->where([['R.id', (int) $repair_id]])
            ->first();

        if (!$order) {
            return Language::get('No data available');
        }

        $lines = [];
        $emails = [];
        $telegrams = [];
        if (!empty(self::$cfg->telegram_chat_id)) {
            $telegrams[self::$cfg->telegram_chat_id] = self::$cfg->telegram_chat_id;
        }
        $name = '';
        $mailto = '';
        $line_uid = '';
        $telegram_id = '';

        // รายชื่อผู้รับ
        if (self::$cfg->demo_mode) {
            // โหมดตัวอย่าง ส่งหาแอดมินเท่านั้น
            $where = [
                ['id', 1]
            ];
        } elseif ($order->status == self::$cfg->repair_first_status) {
            // งานใหม่ ส่งหาผู้แจ้ง ผู้ดูแล และผู้จัดการงานซ่อมทุกคน
            $where = [
                ['id', (int) $order->customer_id],
                ['status', 1],
                ['permission', 'LIKE', '%,can_manage_repair,%']
            ];
        } else {
            // อัปเดตสถานะ ส่งหาผู้แจ้ง ช่างผู้รับผิดชอบ และผู้จัดการงานซ่อม
            $where = [
                ['id', [(int) $order->customer_id, (int) $order->operator_id]],
                ['permission', 'LIKE', '%,can_manage_repair,%']
            ];
        }

        $query = static::createQuery()
            ->select('id', 'username', 'name', 'line_uid', 'telegram_id')
            ->from('user')
            ->where([['active', 1]])
            ->where($where, 'OR');

        if (self::$cfg->demo_mode) {
            $query->where([['social', 'user']]);
        }

        foreach ($query->fetchAll() as $item) {
            if ($item->id == $order->customer_id) {
                // ผู้แจ้งซ่อม
                $name = $item->name;
                $mailto = $item->username;
                $line_uid = $item->line_uid;
                $telegram_id = $item->telegram_id;
            } else {
                // เจ้าหน้าที่
                $emails[] = $item->name.'<'.$item->username.'>';
                if (!empty($item->line_uid)) {
                    $lines[] = $item->line_uid;
                }
                if (!empty($item->telegram_id)) {
                    $telegrams[$item->telegram_id] = $item->telegram_id;
                }
            }
        }

        // ข้อความ
        $msg = [
            '{LNG_Repair jobs} '.$order->job_id,
            '{LNG_Informer} : '.$name,
            '{LNG_Equipment} : '.$order->topic,
            '{LNG_Serial/Registration No.} : '.$order->product_no,
            '{LNG_Date} : '.Date::format($order->created_at),
            '{LNG_Problems and repairs details} : '.\Kotchasan\Text::untextarea($order->job_description)
        ];
        if ($order->status != self::$cfg->repair_first_status) {
            $msg[] = '{LNG_Status} : '.$order->status_text;
        }

        // ข้อความของผู้แจ้ง
        $msg = Language::trans(implode("\n", $msg));
        // ข้อความของเจ้าหน้าที่ (มีลิงก์ไปยังรายการซ่อม)
        $admin_msg = $msg."\nURL : ".WEB_URL.'repair-jobs';

        $ret = [];

        // กลุ่ม Telegram ของโมดูล/แผนก (แทนกลุ่ม LINE Notify ของ E-Office เดิม)
        if (class_exists('\\Telegramgroup\\Group\\Model')) {
            $err = \Telegramgroup\Group\Model::send('repair', \Kotchasan\DB::create()->pluck('user_meta', 'value', [['member_id', (int) $order->customer_id], ['name', 'department']]), $admin_msg);
            if ($err != '') {
                $ret[] = $err;
            }
        }

        if (!empty(self::$cfg->telegram_bot_token)) {
            $err = \Gcms\Telegram::sendTo($telegrams, $admin_msg);
            if ($err != '') {
                $ret[] = $err;
            }
            if (!empty($telegram_id)) {
                $err = \Gcms\Telegram::sendTo($telegram_id, $msg);
                if ($err != '') {
                    $ret[] = $err;
                }
            }
        }

        if (!empty(self::$cfg->line_channel_access_token)) {
            if (!empty($lines)) {
                $err = \Gcms\Line::sendTo($lines, $admin_msg);
                if ($err != '') {
                    $ret[] = $err;
                }
            }
            if (!empty($line_uid)) {
                $err = \Gcms\Line::sendTo($line_uid, $msg);
                if ($err != '') {
                    $ret[] = $err;
                }
            }
        }

        $noreply = self::$cfg->get('noreply_email', '');
        if ($noreply !== '') {
            $subject = '['.\Kotchasan\Text::plainText(self::$cfg->get('web_title', '')).'] '.Language::get('Repair jobs').' '.$order->status_text;

            // ผู้แจ้งซ่อม
            if ($mailto !== '') {
                $err = \Kotchasan\Email::send($name.'<'.$mailto.'>', $noreply, $subject, nl2br($msg));
                if ($err->error()) {
                    $ret[] = strip_tags($err->getErrorMessage());
                }
            }

            // เจ้าหน้าที่
            $admin_html = nl2br($admin_msg);
            foreach (empty(self::$cfg->repair_send_mail ?? 1) ? [] : $emails as $item) {
                $err = \Kotchasan\Email::send($item, $noreply, $subject, $admin_html);
                if ($err->error()) {
                    $ret[] = strip_tags($err->getErrorMessage());
                }
            }
        }

        return empty($ret) ? Language::get('Your message was sent successfully') : implode("\n", array_unique($ret));
    }
}
