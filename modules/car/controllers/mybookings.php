<?php
/**
 * @filesource modules/car/controllers/mybookings.php
 */

namespace Car\Mybookings;

use Car\Booking\Model as BookingModel;
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
    protected $allowedSortColumns = [
        'vehicle_number',
        'reason',
        'begin',
        'end',
        'created_at',
        'status'
    ];

    /**
     * Custom query params.
     *
     * @param Request $request
     * @param object $login
     *
     * @return array
     */
    protected function getCustomParams(Request $request, $login): array
    {
        return [
            'status' => $request->get('status')->filter('0-9')
        ];
    }

    /**
     * Build query.
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
     * Filter options.
     *
     * @param array $params
     * @param object $login
     *
     * @return array
     */
    protected function getFilters(array $params, $login)
    {
        return [
            'status' => Helper::getStatusOptions(),
            'chauffeur' => Helper::getDriverOptions()
        ];
    }

    /**
     * Format row data.
     *
     * @param array $datas
     * @param object|null $login
     *
     * @return array
     */
    protected function formatDatas(array $datas, $login = null): array
    {
        foreach ($datas as $item) {
            $item->can_edit = Helper::canEditBooking($item);
            $item->can_cancel = Helper::canCancelBookingByRequester($item);
            $item->first_image_url = Helper::getVehicleFirstImageUrl((int) ($item->vehicle_id ?? 0));
            $item->can_delete = Helper::canDeleteBookingByRequester($item);
        }

        return $datas;
    }

    /**
     * Row edit action.
     *
     * @param Request $request
     * @param object $login
     *
     * @return \Kotchasan\Http\Response
     */
    protected function handleEditAction(Request $request, $login)
    {
        $id = $request->post('id')->toInt();
        $row = BookingModel::getRecord((int) $login->id, $id);
        if ($row === null) {
            return $this->errorResponse('No data available', 404);
        }
        if (!Helper::canEditBooking($row)) {
            return $this->errorResponse('This booking can no longer be edited', 403);
        }

        return $this->redirectResponse('/car-booking?id='.$id);
    }

    /**
     * Row view action.
     *
     * @param Request $request
     * @param object $login
     *
     * @return \Kotchasan\Http\Response
     */
    protected function handleViewAction(Request $request, $login)
    {
        $id = $request->post('id')->toInt();
        $row = BookingModel::getRecord((int) $login->id, $id);
        if ($row === null) {
            return $this->errorResponse('No data available', 404);
        }

        $data = BookingModel::getDetailData($id);
        if ($data === null) {
            return $this->errorResponse('No data available', 404);
        }
        if (!BookingModel::canView($login, $data)) {
            return $this->errorResponse('Permission required', 403);
        }

        return $this->successResponse([
            'data' => BookingModel::viewData($data),
            'actions' => [
                \Car\View\View::buildModalAction($data)
            ]
        ]);
    }

    /**
     * Row cancel action.
     *
     * @param Request $request
     * @param object $login
     *
     * @return \Kotchasan\Http\Response
     */
    protected function handleCancelAction(Request $request, $login)
    {
        ApiController::validateMethod($request, 'POST');

        $id = $request->post('id')->toInt();
        $row = BookingModel::getRecord((int) $login->id, $id);
        if ($row === null) {
            return $this->errorResponse('No data available', 404);
        }
        if (!Helper::canCancelBookingByRequester($row)) {
            return $this->errorResponse('This booking can no longer be cancelled', 403);
        }

        BookingModel::updateStatus($id, Helper::STATUS_CANCELLED_BY_REQUESTER);
        \Index\Log\Model::add($id, 'car', 'Cancel', 'Cancelled car booking: '.$id, $login->id);

        $message = \Car\Email\Controller::sendByBookingId($id);

        return $this->redirectResponse('reload', $message, 200, 0, 'table');
    }

    /**
     * Bulk delete action for the requester.
     *
     * @param Request $request
     * @param object $login
     *
     * @return \Kotchasan\Http\Response
     */
    protected function handleDeleteAction(Request $request, $login)
    {
        ApiController::validateMethod($request, 'POST');

        $id = $request->post('id')->toInt();
        $removed = BookingModel::removeOwned((int) $login->id, $id);
        if ($removed === 0) {
            return $this->errorResponse('No data to delete', 400);
        }

        \Index\Log\Model::add(0, 'car', 'Delete', 'Deleted car booking ID(s): '.$id, $login->id);

        return $this->redirectResponse('reload', 'Deleted successfully', 200, 0, 'table');
    }
}
