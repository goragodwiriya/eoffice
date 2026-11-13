<?php
/**
 * @filesource modules/car/controllers/approvals.php
 */

namespace Car\Approvals;

use Car\Helper\Controller as Helper;
use Gcms\Api as ApiController;
use Kotchasan\Http\Request;

class Controller extends \Gcms\Table
{
    /**
     * Allowed sort columns.
     *
     * @var array
     */
    protected $allowedSortColumns = ['member_id', 'vehicle_id', 'begin', 'end', 'created_at', 'status'];

    /**
     * Ensure approver access.
     *
     * @param Request $request
     * @param object $login
     *
     * @return true|\Kotchasan\Http\Response
     */
    protected function checkAuthorization(Request $request, $login)
    {
        if (!Helper::canApproveRequests($login)) {
            return $this->errorResponse('Forbidden', 403);
        }

        return true;
    }

    /**
     * Custom table parameters.
     *
     * @param Request $request
     * @param object $login
     *
     * @return array
     */
    protected function getCustomParams(Request $request, $login): array
    {
        return [
            'member_id' => $request->get('member_id')->filter('0-9'),
            'vehicle_id' => $request->get('vehicle_id')->filter('0-9'),
            'status' => $request->get('status')->filter('0-9'),
            'chauffeur' => $request->get('chauffeur')->filter('\-0-9'),
            'from' => $request->get('from')->date(),
            'to' => $request->get('to')->date(),
            'department' => $request->get('department')->topic()
        ];
    }

    /**
     * Query
     *
     * @param array $params
     * @param object|null $login
     *
     * @return \Kotchasan\QueryBuilder\QueryBuilderInterface
     */
    protected function toDataTable($params, $login = null)
    {
        return Model::toDataTable($params, $login);
    }

    /**
     * Filters
     *
     * @param array $params
     * @param object|null $login
     *
     * @return array
     */
    protected function getFilters($params, $login = null)
    {
        return [
            'status' => Helper::getStatusOptions(),
            'chauffeur' => Helper::getDriverOptions(),
            'department' => \Gcms\Category::init()->toOptions('department'),
            'vehicle_id' => Helper::getVehicleOptions(false),
            // ผู้ยื่นที่ผู้ดูเห็นตามขั้นที่อนุมัติได้ ไม่ใช่ทุกคนในระบบ
            'member_id' => \Index\Users\Model::toOptions(Model::memberIds($login))
        ];
    }

    /**
     * Format rows for display.
     *
     * @param array $datas
     * @param object|null $login
     *
     * @return array
     */
    protected function formatDatas(array $datas, $login = null): array
    {
        foreach ($datas as $item) {
            $item->first_image_url = Helper::getVehicleFirstImageUrl((int) ($item->vehicle_id ?? 0));
        }

        return $datas;
    }

    /**
     * Runtime table options.
     *
     * @param array $params
     * @param object|null $login
     *
     * @return array
     */
    protected function getOptions(array $params, $login)
    {
        $isSuperAdmin = ApiController::isSuperAdmin($login);

        return [
            '_table' => [
                'showCheckbox' => $isSuperAdmin,
                'actions' => $isSuperAdmin ? [
                    'delete' => 'Delete'
                ] : [],
                'actionButton' => $isSuperAdmin ? 'Process|btn-success' : null
            ]
        ];
    }

    /**
     * Review action.
     *
     * @param Request $request
     * @param object $login
     *
     * @return \Kotchasan\Http\Response
     */
    protected function handleReviewAction(Request $request, $login)
    {
        $id = $request->post('id')->toInt();
        if ($id <= 0 || !Helper::canApproveRequests($login)) {
            return $this->errorResponse('Permission required', 403);
        }

        return $this->redirectResponse('/car-review?id='.$id);
    }

    /**
     * Review statistics.
     *
     * @param Request $request
     * @param object $login
     *
     * @return \Kotchasan\Http\Response
     */
    protected function handleStatisticsAction(Request $request, $login)
    {
        $id = $request->post('id')->toInt();
        $memberId = $request->post('member_id')->toInt();
        if ($memberId <= 0 && $id > 0) {
            $record = \Kotchasan\Model::createQuery()
                ->select('member_id')
                ->from('leave_items')
                ->where(['id', $id])
                ->first();
            $memberId = $record ? (int) $record->member_id : 0;
        }

        if ($memberId <= 0 || !Helper::canApproveRequests($login)) {
            return $this->errorResponse('Permission required', 403);
        }

        return $this->redirectResponse('/leave-statistics?member_id='.$memberId);
    }

    /**
     * Deleted by admin. All items can be deleted.
     *
     * @param Request $request
     * @param object $login
     *
     * @return \Kotchasan\Http\Response
     */
    protected function handleDeleteAction(Request $request, $login)
    {
        if (!ApiController::isSuperAdmin($login)) {
            return $this->errorResponse('Permission required', 403);
        }

        $ids = $request->request('ids', [])->toInt();
        if (empty($ids)) {
            return $this->errorResponse('No data to delete', 400);
        }

        Model::remove($ids);

        // Log deletion
        \Index\Log\Model::add(0, 'car', 'Delete', 'Deleted car reservation ID(s) : '.implode(', ', $ids), $login->id);

        // Return success response
        return $this->redirectResponse('reload', 'Deleted successfully', 200, 0, 'table');
    }
}
