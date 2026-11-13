<?php
/**
 * @filesource modules/telegramgroup/controllers/init.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Telegramgroup\Init;

use Gcms\Api as ApiController;

/**
 * เมนูของโมดูลกลุ่ม Telegram — อยู่ใต้เมนูตั้งค่า เฉพาะผู้ตั้งค่าระบบได้
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Controller
{
    /**
     * @param array $menus
     * @param mixed $params
     * @param object|null $login
     *
     * @return array
     */
    public static function initMenus($menus, $params = null, $login = null)
    {
        if (!$login || !ApiController::hasPermission($login, 'can_config') || !isset($menus['settings'])) {
            return $menus;
        }

        return parent::insertMenuChildren($menus, [
            [
                'title' => '{LNG_Telegram groups}',
                'url' => '/telegram-groups',
                'icon' => 'icon-telegram'
            ]
        ], 'settings', null, 1);
    }
}
