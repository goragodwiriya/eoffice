<?php
/**
 * @filesource modules/repair/models/action.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Repair\Action;

use Gcms\Api as ApiController;

/**
 * ฟอร์มปรับปรุงสถานะการซ่อม (Modal)
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * ข้อมูลสำหรับเปิด Modal ปรับปรุงสถานะการซ่อม
     * ไม่พบงานซ่อมคืนค่า null
     *
     * @param int $id ID ของงานซ่อม
     * @param object $login
     *
     * @return array|null
     */
    public static function modalPayload($id, $login)
    {
        $index = \Repair\Detail\Model::get($id);
        if (!$index) {
            return null;
        }

        $isManager = ApiController::hasPermission($login, 'can_manage_repair');

        $options = [
            'status' => \Repair\Status\Model::toOptions()
        ];
        if ($isManager) {
            $options['operator_id'] = array_merge(
                [['value' => 0, 'text' => '{LNG_Please select}']],
                \Repair\Operator\Model::toOptions()
            );
        }

        return [
            'data' => (object) [
                'repair_id' => $index->id,
                'job_id' => $index->job_id,
                'topic' => $index->topic,
                'status' => $index->status,
                'operator_id' => $index->operator_id,
                'comment' => '',
                'is_manager' => $isManager ? 1 : 0
            ],
            'options' => $options,
            'actions' => [
                [
                    'type' => 'modal',
                    'action' => 'show',
                    'template' => '/repair/action.html',
                    'title' => '{LNG_Update repair status} '.$index->job_id,
                    'titleClass' => 'icon-tools'
                ]
            ]
        ];
    }
}
