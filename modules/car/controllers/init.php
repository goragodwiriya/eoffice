<?php
/**
 * @filesource modules/car/controllers/init.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Car\Init;

/**
 * Init Module
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller
{
    /**
     * รายการ permission ของโมดูล
     *
     * @param array $permissions
     *
     * @return array
     */
    public static function updatePermissions($permissions)
    {
        $permissions['can_manage_car'] = '{LNG_Can manage the} {LNG_Vehicle}';
        return $permissions;
    }
}
