<?php
/**
 * @filesource modules/car/models/calendar.php
 *
 * รายการจองรถบนปฏิทินรวมของแกน (api/index/calendar) — เรียกจาก hook initCalendarEvents
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 */

namespace Car\Calendar;

use Car\Helper\Controller as Helper;

/**
 * การจองรถที่สถานะอยู่ใน car_calendar_status
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * ไอคอนของการจองรถบนปฏิทินรวม
     */
    const ICON = 'icon-car';

    /**
     * รายการในช่วงวันที่ ตามรูปแบบ event ของ EventCalendar
     *
     * @param string $start Y-m-d
     * @param string $end   Y-m-d
     *
     * @return array
     */
    public static function events($start, $end)
    {
        $query = static::createQuery()
            ->select('R.id', 'R.begin', 'R.end', 'V.number', 'V.color')
            ->from('car_reservation R')
            ->join('vehicles V', ['V.id', 'R.vehicle_id'], 'LEFT')
            ->where([
                // ค่าตั้งต้นอยู่ที่โมดูล — โปรเจ็คที่รับโมดูลนี้ไปรวมอาจไม่ได้ประกาศไว้ใน Gcms\Config
                ['R.status', self::$cfg->car_calendar_status ?? [Helper::STATUS_APPROVED]],
                ['R.begin', '<=', $end.' 23:59:59'],
                ['R.end', '>=', $start.' 00:00:00']
            ])
            ->orderBy('R.begin')
            ->cacheOn();

        $events = [];
        foreach ($query->fetchAll() as $item) {
            // id ขึ้นต้นด้วยชื่อโมดูล — ปฏิทินรวมมีรายการของหลายโมดูล id ต้องไม่ชนกัน
            $events[] = [
                'id' => 'car-'.$item->id,
                'title' => $item->number.', '.Helper::formatBookingTime($item, true),
                'start' => $item->begin,
                'end' => $item->end,
                'scheduleType' => 'continuous',
                'allDay' => false,
                'color' => $item->color ?: '#4285F4',
                'icon' => self::ICON,
                'clickApi' => 'api/car/view?id='.$item->id
            ];
        }

        return $events;
    }
}
