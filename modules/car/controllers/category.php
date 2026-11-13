<?php
/**
 * @filesource modules/car/controllers/category.php
 */

namespace Car\Category;

class Controller extends \Gcms\Category
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
}