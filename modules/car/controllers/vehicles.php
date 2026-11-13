<?php
/**
 * @filesource modules/car/controllers/vehicles.php
 */

namespace Car\Vehicles;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;

class Controller extends \Gcms\Table
{
    /**
     * Allowed sort columns.
     *
     * @var array
     */
    protected $allowedSortColumns = [
        'id',
        'number',
        'car_brand',
        'car_type',
        'seats',
        'is_active'
    ];

    /**
     * Authorization for vehicle management.
     *
     * @param Request $request
     * @param object $login
     *
     * @return mixed
     */
    protected function checkAuthorization(Request $request, $login)
    {
        if (!ApiController::hasPermission($login, ['can_manage_car', 'can_config'])) {
            return $this->errorResponse('Permission required', 403);
        }

        return true;
    }

    /**
     * Get custom parameters for users table
     *
     * @param Request $request
     * @param object $login
     *
     * @return array
     */
    protected function getCustomParams(Request $request, $login): array
    {
        return [
            'car_brand' => $request->get('car_brand')->number(),
            'car_type' => $request->get('car_type')->number()
        ];
    }

    /**
     * Query data to send to DataTable.
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
     * Append the first vehicle image to each row.
     *
     * @param array $datas
     * @param object|null $login
     *
     * @return array
     */
    protected function formatDatas(array $datas, $login = null): array
    {
        foreach ($datas as $item) {
            $item->first_image_url = \Car\Helper\Controller::getVehicleFirstImageUrl((int) $item->id);
        }

        return $datas;
    }

    /**
     * Get filters for table response
     *
     * @param array $params
     * @param object $login
     *
     * @return array
     */
    protected function getFilters($params, $login = null)
    {
        $categories = \Car\Category\Controller::init();

        return [
            'car_brand' => $categories->toOptions('car_brand'),
            'car_type' => $categories->toOptions('car_type')
        ];
    }

    /**
     * Handle edit action.
     *
     * @param Request $request
     * @param object $login
     *
     * @return array
     */
    protected function handleEditAction(Request $request, $login)
    {
        if (!ApiController::hasPermission($login, ['can_manage_car', 'can_config'])) {
            return $this->errorResponse('Failed to process request', 403);
        }

        $vehicle = \Car\Vehicle\Model::get($request->post('id')->toInt());
        if ($vehicle === null) {
            return $this->errorResponse('No data available', 404);
        }

        $categories = \Car\Category\Controller::init();

        return $this->successResponse([
            'data' => (array) $vehicle,
            'options' => [
                'car_brand' => $categories->toOptions('car_brand', true, null, ['' => '{LNG_Please select}']),
                'car_type' => $categories->toOptions('car_type', true, null, ['' => '{LNG_Please select}'])
            ],
            'actions' => [
                'type' => 'modal',
                'template' => 'car/vehicle.html',
                'title' => ($vehicle->id > 0 ? '{LNG_Edit} {LNG_Vehicle}' : '{LNG_Add} {LNG_Vehicle}'),
                'titleClass' => 'icon-car'
            ]
        ]);
    }

    /**
     * Handle delete action.
     *
     * @param Request $request
     * @param object $login
     *
     * @return \Kotchasan\Http\Response
     */
    protected function handleDeleteAction(Request $request, $login)
    {
        if (!ApiController::canModify($login, ['can_manage_car', 'can_config'])) {
            return $this->errorResponse('Failed to process request', 403);
        }

        $ids = $request->request('ids', [])->toInt();
        $removeCount = \Car\Vehicle\Model::remove($ids);
        if (empty($removeCount)) {
            return $this->errorResponse('Delete action failed', 400);
        }

        \Index\Log\Model::add(0, 'car', 'Delete', 'Deleted vehicle ID(s): '.implode(', ', $ids), $login->id);

        return $this->redirectResponse('reload', 'Deleted successfully');
    }

    /**
     * Handle active action.
     *
     * @param Request $request
     * @param object $login
     *
     * @return \Kotchasan\Http\Response
     */
    protected function handleActiveAction(Request $request, $login)
    {
        if (!ApiController::canModify($login, ['can_manage_car', 'can_config'])) {
            return $this->errorResponse('Failed to process request', 403);
        }

        $vehicle = \Car\Vehicle\Model::toggleActive($request->post('id')->toInt());
        if ($vehicle === null) {
            return $this->errorResponse('Vehicle not found', 404);
        }

        $msg = (int) $vehicle->is_active === 1 ? 'Activated vehicle: '.$vehicle->number : 'Deactivated vehicle: '.$vehicle->number;
        \Index\Log\Model::add($vehicle->id, 'car', 'Active', $msg, $login->id);

        return $this->redirectResponse('reload', $msg, 200, 0, 'table');
    }
}
