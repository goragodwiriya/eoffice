<?php
/**
 * @filesource modules/repair/controllers/export.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Repair\Export;

use Gcms\Login;
use Kotchasan\Http\Request;

/**
 * Controller สำหรับแสดงหน้าเว็บ
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Controller
{
    /**
     * หน้าสำหรับพิมพ์ (print.html).
     *
     * @param Request $request
     */
    public function index(Request $request)
    {
        // session, member
        if ($request->initSession() && $login = Login::isMember()) {
            // อ่านข้อมูลการทำรายการ
            $index = \Repair\Export\Model::get($request->get('id')->toInt());
            if ($index && ($login['id'] == $index->customer_id || Login::checkPermission($login, array('can_manage_repair', 'can_repair')))) {
                $detail = \Repair\Export\View::create()->render($index);
            }
        }
        if (empty($detail)) {
            // ไม่พบโมดูลหรือไม่มีสิทธิ
            new \Kotchasan\Http\NotFound();
        } else {
            // แสดงผล
            echo $detail;
        }
    }
}
