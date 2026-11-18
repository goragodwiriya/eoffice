<?php
/**
 * @filesource modules/car/controllers/init.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Car\Init;

use Car\Helper\Controller as Helper;
use Gcms\Api as ApiController;
use Kotchasan\Database\Sql;

class Controller extends \Gcms\Controller
{
    /**
     * Register car permissions.
     *
     * @param array $permissions
     * @param mixed $params
     * @param object|null $login
     *
     * @return array
     */
    public static function initPermission($permissions, $params = null, $login = null)
    {
        $permissions[] = [
            'value' => 'can_manage_car',
            'text' => '{LNG_Can manage} {LNG_Car booking}'
        ];
        $permissions[] = [
            'value' => 'can_drive_car',
            'text' => '{LNG_Can drive car}'
        ];

        return $permissions;
    }

    /**
     * Register car menus.
     *
     * @param array $menus
     * @param mixed $params
     * @param object|null $login
     *
     * @return array
     */
    public static function initMenus($menus, $params = null, $login = null)
    {
        if (!$login) {
            return $menus;
        }

        $memberMenu = [
            [
                'title' => '{LNG_My bookings}',
                'url' => '/car-my-bookings',
                'icon' => 'icon-list'
            ],
            [
                'title' => '{LNG_Book a car}',
                'url' => '/car-booking',
                'icon' => 'icon-edit'
            ],
            [
                'title' => '{LNG_All vehicles}',
                'url' => '/cars',
                'icon' => 'icon-car'
            ]
        ];

        if (Helper::canAccessApprovalArea($login)) {
            $memberMenu[] = [
                'title' => '{LNG_Car approvals}',
                'url' => '/car-approvals',
                'icon' => 'icon-verfied'
            ];
        }

        $menus = parent::insertMenuAfter($menus, $memberMenu, 0);

        if (!ApiController::hasPermission($login, ['can_manage_car', 'can_config'])) {
            return $menus;
        }

        $children = [
            [
                'title' => '{LNG_Settings}',
                'url' => '/car-settings',
                'icon' => 'icon-cog'
            ],
            [
                'title' => '{LNG_Vehicles}',
                'url' => '/vehicles',
                'icon' => 'icon-car'
            ]
        ];
        $categories = \Car\Category\Controller::items();
        foreach ($categories as $key => $menu) {
            $children[] = [
                'title' => $menu,
                'url' => '/car-categories?type='.$key,
                'icon' => 'icon-tags'
            ];
        }

        $settingsMenu = [
            [
                'title' => '{LNG_Car booking}',
                'icon' => 'icon-car',
                'children' => $children
            ]
        ];

        return parent::insertMenuChildren($menus, $settingsMenu, 'settings', null, 1);
    }

    /**
     * การ์ดหน้าแรก: ใบจองรถของฉันแยกตามสถานะ · คำขอที่รออนุมัติ (ผู้อนุมัติ) ·
     * เอกสารรถ/บำรุงรักษาที่ถึงกำหนด (ผู้ดูแลรถ)
     *
     * ผู้เยี่ยมชม (เปิด dashboard_guest) ไม่มีการ์ด · hint บอกชื่อโมดูลเพราะ booking
     * ส่งการ์ดสถานะชื่อเดียวกันขึ้นหน้าแรกเดียวกัน
     *
     * @param array $cards
     * @param mixed $params
     * @param object|null $login
     *
     * @return array
     */
    public static function initDashboardCards($cards, $params = null, $login = null)
    {
        if (!$login) {
            return $cards;
        }
        $module = \Kotchasan\Language::get('Book a car');
        $statuses = [
            Helper::STATUS_PENDING_REVIEW => ['Pending review', 'icon-loading'],
            Helper::STATUS_RETURNED_FOR_EDIT => ['Returned for edit', 'icon-edit'],
            Helper::STATUS_APPROVED => ['Approved', 'icon-valid']
        ];
        // My bookings counts
        $query = \Kotchasan\Model::createQuery()
            ->select(Sql::COUNT('id', 'count'), 'status')
            ->from('car_reservation')
            ->where(['member_id', (int) $login->id])
            ->groupBy('status')
            ->cacheOn();
        foreach ($query->fetchAll() as $row) {
            if (isset($statuses[$row->status])) {
                $cards[] = [
                    'title' => $statuses[$row->status][0],
                    'value' => (int) $row->count,
                    'icon' => $statuses[$row->status][1],
                    'url' => '/my-bookings?status='.$row->status,
                    'class' => '',
                    'unit' => '',
                    'hint' => [['text' => $module]]
                ];
            }
        }

        if (Helper::canAccessApprovalArea($login)) {
            $approveLevel = Helper::getApproveLevel($login);
            $q = \Kotchasan\Model::createQuery()
                ->select(Sql::COUNT('id', 'count'))
                ->from('car_reservation')
                ->where(['status', Helper::STATUS_PENDING_REVIEW]);

            if ($approveLevel !== -1) {
                $q->where(['approve', $approveLevel]);
            }

            $row = $q->first();

            $cards[] = [
                'title' => 'Requests to review',
                'value' => (int) ($row ? $row->count : 0),
                'icon' => 'icon-verfied',
                'url' => '/car-approvals?status=0',
                'class' => '',
                'unit' => '',
                'hint' => [['text' => $module]]
            ];
        }

        return $cards;
    }

    /**
     * แหล่งข้อมูลของปฏิทินรวม (api/index/calendar)
     *
     * แกนเพิ่มปฏิทินบนหน้าแรกให้เองเมื่อมีแหล่งข้อมูล — โปรเจ็คที่รวม
     * โมดูลจองห้องไว้ด้วย รายการของทั้งสองโมดูลอยู่ในปฏิทินเดียวกัน
     *
     * @param array $sources
     * @param mixed $params
     * @param object|null $login
     *
     * @return array
     */
    public static function initCalendarSources($sources, $params = null, $login = null)
    {
        $sources[] = 'car';

        return $sources;
    }

    /**
     * รายการการจองรถบนปฏิทินรวม — ผู้เยี่ยมชมเห็นด้วยเมื่อเปิด dashboard_guest (แกนตรวจให้แล้ว)
     *
     * @param array $events
     * @param array $params ['start' => Y-m-d, 'end' => Y-m-d]
     * @param object|null $login
     *
     * @return array
     */
    public static function initCalendarEvents($events, $params = null, $login = null)
    {
        return array_merge($events, \Car\Calendar\Model::events($params['start'], $params['end']));
    }
}
