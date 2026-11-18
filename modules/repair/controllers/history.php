<?php
/**
 * @filesource modules/repair/controllers/history.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Repair\History;

use Kotchasan\Http\Request;

/**
 * API ตารางประวัติการแจ้งซ่อมของสมาชิก
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
    protected $allowedSortColumns = ['id', 'job_id', 'created_at', 'status'];

    /**
     * สมาชิกทุกคนที่เข้าระบบดูประวัติของตัวเองได้
     *
     * @param Request $request
     * @param object $login
     *
     * @return mixed
     */
    protected function checkAuthorization(Request $request, $login)
    {
        return $login ? true : $this->errorResponse('Forbidden', 403);
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
        return [
            'customer_id' => (int) $login->id,
            'status' => $request->get('status')->number()
        ];
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
        return [
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
            $item->comment = \Kotchasan\Text::untextarea($item->comment);
        }

        return $datas;
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
}
