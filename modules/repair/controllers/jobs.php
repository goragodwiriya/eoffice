<?php
/**
 * @filesource modules/repair/controllers/jobs.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Repair\Jobs;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;

/**
 * API ตารางรายการงานซ่อม (เจ้าหน้าที่และช่างซ่อม)
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Table
{
    /**
     * คอลัมน์ที่เรียงลำดับได้ (ป้องกัน SQL injection)
     *
     * @var array
     */
    protected $allowedSortColumns = ['id', 'job_id', 'name', 'created_at', 'status'];

    /**
     * ตรวจสอบสิทธิ์
     *
     * @param Request $request
     * @param object $login
     *
     * @return mixed
     */
    protected function checkAuthorization(Request $request, $login)
    {
        if (!ApiController::hasPermission($login, ['can_manage_repair', 'can_repair'])) {
            return $this->errorResponse('Permission required', 403);
        }

        return true;
    }

    /**
     * ตัวเลือกสำหรับกรองข้อมูล
     *
     * @param Request $request
     * @param object $login
     *
     * @return array
     */
    protected function getCustomParams(Request $request, $login): array
    {
        $params = [
            'from' => $request->get('from')->date(),
            'to' => $request->get('to')->date(),
            'operator_id' => $request->get('operator_id')->number(),
            'status' => $request->get('status')->number()
        ];

        // ช่างซ่อมที่ไม่ใช่ผู้จัดการ เห็นงานของตัวเอง และงานที่ยังไม่มอบหมายช่าง (แบบ E-Office เดิม)
        if (!ApiController::hasPermission($login, 'can_manage_repair')) {
            $params['operator_id'] = [0, (int) $login->id];
        }

        return $params;
    }

    /**
     * Query ข้อมูลสำหรับส่งให้กับ DataTable
     *
     * @param array $params
     * @param object|null $login
     *
     * @return \Kotchasan\QueryBuilder\QueryBuilderInterface
     */
    protected function toDataTable($params, $login = null)
    {
        return Model::toDataTable($params);
    }

    /**
     * ตัวเลือกของ filter
     *
     * @param array $params
     * @param object|null $login
     *
     * @return array
     */
    protected function getFilters($params, $login = null)
    {
        $operators = \Repair\Operator\Model::toOptions();

        // ช่างซ่อมที่ไม่ใช่ผู้จัดการ เลือกได้เฉพาะตัวเอง
        if (!ApiController::hasPermission($login, 'can_manage_repair')) {
            $operators = array_values(array_filter($operators, function ($item) use ($login) {
                return (int) $item['value'] === (int) $login->id;
            }));
        }

        return [
            'operator_id' => $operators,
            'status' => \Repair\Status\Model::toOptions()
        ];
    }

    /**
     * จัดรูปแบบข้อมูลก่อนส่งให้ตาราง
     *
     * @param array $datas
     * @param object|null $login
     *
     * @return array
     */
    protected function formatDatas(array $datas, $login = null): array
    {
        $statuses = \Repair\Status\Model::map(false);
        $operators = \Repair\Operator\Model::map();

        foreach ($datas as $item) {
            $item->status_text = isset($statuses[$item->status]) ? $statuses[$item->status]['topic'] : '';
            $item->status_color = isset($statuses[$item->status]) ? $statuses[$item->status]['color'] : '';
            $item->operator_name = isset($operators[$item->operator_id]) ? $operators[$item->operator_id] : '';
        }

        return $datas;
    }

    /**
     * ลบงานซ่อมที่เลือก
     *
     * @param Request $request
     * @param object $login
     *
     * @return \Kotchasan\Http\Response
     */
    protected function handleDeleteAction(Request $request, $login)
    {
        if (!ApiController::canModify($login, ['can_manage_repair'])) {
            return $this->errorResponse('Failed to process request', 403);
        }

        $ids = $request->request('ids', [])->toInt();
        if (empty($ids)) {
            return $this->errorResponse('No items selected', 400);
        }

        $removeCount = Model::remove($ids);
        if (empty($removeCount)) {
            return $this->errorResponse('Delete action failed', 400);
        }

        \Index\Log\Model::add(0, 'repair', 'Delete', 'Delete Repair ID(s) : '.implode(', ', $ids), $login->id);

        return $this->redirectResponse('reload', 'Deleted '.$removeCount.' item(s) successfully', 200, 0, 'table');
    }

    /**
     * ดูรายละเอียดงานซ่อม
     *
     * @param Request $request
     * @param object $login
     *
     * @return \Kotchasan\Http\Response
     */
    protected function handleViewAction(Request $request, $login)
    {
        return $this->redirectResponse('/repair-detail?id='.$request->post('id')->toInt());
    }

    /**
     * เปิดฟอร์มปรับปรุงสถานะการซ่อม
     *
     * @param Request $request
     * @param object $login
     *
     * @return \Kotchasan\Http\Response
     */
    protected function handleStatusAction(Request $request, $login)
    {
        if (!ApiController::canModify($login, ['can_manage_repair', 'can_repair'])) {
            return $this->errorResponse('Failed to process request', 403);
        }

        $payload = \Repair\Action\Model::modalPayload($request->post('id')->toInt(), $login);
        if ($payload === null) {
            return $this->errorResponse('No data available', 404);
        }

        return $this->successResponse($payload);
    }
}
