<?php
/**
 * @filesource modules/index/controllers/categories.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Car\Categories;

class Controller extends \Index\Categories\Controller
{
    /**
     * Supported category types and their labels.
     *
     * @var array
     */
    protected $categories = [
        'car_type' => '{LNG_Car type}',
        'car_brand' => '{LNG_Car brand}',
        'car_accessory' => '{LNG_Car accessory}'
    ];

    /**
     * สิทธิ์เดียวกับที่เห็นเมนูหมวดหมู่ (Car\Init\Controller::initMenus)
     *
     * @var array
     */
    protected $permission = ['can_manage_car', 'can_config'];
}
