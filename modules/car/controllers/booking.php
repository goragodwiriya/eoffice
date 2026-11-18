<?php
/**
 * @filesource modules/car/controllers/booking.php
 */

namespace Car\Booking;

use Car\Helper\Controller as Helper;
use Gcms\Api as ApiController;
use Kotchasan\Http\Request;

class Controller extends ApiController
{
    /**
     * Get booking details.
     *
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function get(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'GET');

            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->errorResponse('Unauthorized', 401);
            }

            // Check if user is affiliated with a department
            $department = isset($login->metas['department'][0]) ? trim((string) $login->metas['department'][0]) : '';
            if ($department === '') {
                return $this->errorResponse('You are not affiliated with a department. Please contact the administrator.', 403);
            }

            $data = Model::get(
                $login,
                $request->get('id')->toInt(),
                $request->get('vehicle_id')->toInt()
            );
            if ($data === null) {
                return $this->redirectResponse('/404', 'No data available', 404);
            }

            $includeVehicleId = !empty($data->vehicle_id) ? (int) $data->vehicle_id : null;
            $includeChauffeurId = isset($data->chauffeur) && (int) $data->chauffeur > 0 ? (int) $data->chauffeur : null;

            $data->options = [
                'vehicle_id' => Helper::getVehicleOptions(true, $includeVehicleId),
                'car_accessories' => Helper::getAccessoryOptions(),
                'chauffeur' => Helper::getDriverOptions($includeChauffeurId)
            ];

            return $this->successResponse($data, 'Booking details retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse(self::exceptionMessage($e), $e->getCode() ?: 500, $e);
        }
    }

/**
 * Save booking.
 *
 * @param Request $request
 *
 * @return \Kotchasan\Http\Response
 */
    public function save(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'POST');
            $this->validateCsrfToken($request);

            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->redirectResponse('/login', 'Unauthorized', 401);
            }

            // Check if user is affiliated with a department
            $department = isset($login->metas['department'][0]) ? trim((string) $login->metas['department'][0]) : '';
            if ($department === '') {
                return $this->errorResponse('You are not affiliated with a department. Please contact the administrator.', 403);
            }

            // Parse input data
            $save = $this->parseInput($request);

            // Check if booking exists and user has permission to edit
            $booking = Model::get($login, $request->post('id')->toInt());
            if (!$booking) {
                return $this->errorResponse('No data available', 404);
            }

            // If booking exists, check if it can be edited
            if (!$booking->canEdit) {
                return $this->errorResponse('This booking can no longer be edited', 403);
            }
            // Validate input data and prepare save data
            $errors = $this->validateAndPrepareSaveData($save, $booking);
            if (!empty($errors)) {
                // Error response
                return $this->formErrorResponse($errors, 400);
            }

            $meta = [
                'car_accessories' => array_values(array_filter(array_map('intval', $save['car_accessories'])))
            ];
            unset($save['car_accessories']);

            // Save booking
            $id = Model::saveReservation($booking->id, $save, $meta);
            \Index\Log\Model::add($id, 'car', 'Save', 'Saved car booking: '.$id, $login->id);

            if ($booking->id === 0 || $booking->status !== $save['status']) {
                $message = \Car\Email\Controller::sendByBookingId($id);
            } else {
                $message = 'Saved successfully';
            }

            return $this->redirectResponse('/car-my-bookings', $message);
        } catch (\Exception $e) {
            return $this->errorResponse(self::exceptionMessage($e), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * Parse user input from request
     *
     * @param Request $request
     *
     * @return array
     */
    protected function parseInput(Request $request): array
    {
        return [
            'begin_date' => $request->post('begin_date')->date(),
            'begin_time' => $request->post('begin_time')->time(),
            'end_date' => $request->post('end_date')->date(),
            'end_time' => $request->post('end_time')->time(),
            'car_accessories' => $request->post('car_accessories', [])->toInt(),
            'chauffeur' => $request->post('chauffeur')->toInt(),
            'comment' => $request->post('comment')->textarea(),
            'detail' => $request->post('detail')->textarea(),
            'travelers' => $request->post('travelers')->toInt(),
            'vehicle_id' => $request->post('vehicle_id')->toInt(),
        ];
    }

    /**
     * Validate booking input and prepare the save payload.
     *
     * @param array &$save Save data (modified by reference)
     * @param object $booking Existing booking
     *
     * @return array Validation errors, empty if valid
     */
    protected function validateAndPrepareSaveData(&$save, $booking)
    {
        $errors = [];
        if ($save['vehicle_id'] <= 0) {
            $errors['vehicle_id'] = 'Please select';
        }
        if ($save['travelers'] <= 0) {
            $errors['travelers'] = 'Please fill in';
        }
        if ($save['detail'] === '') {
            $errors['detail'] = 'Please fill in';
        }
        if ($save['begin_date'] === null) {
            $errors['begin_date'] = 'Please fill in';
        }
        if ($save['begin_time'] === null) {
            $errors['begin_time'] = 'Please fill in';
        }
        if ($save['end_date'] === null) {
            $errors['end_date'] = 'Please fill in';
        }
        if ($save['end_time'] === null) {
            $errors['end_time'] = 'Please fill in';
        }

        if (empty($errors)) {
            if ($save['end_date'].$save['end_time'] > $save['begin_date'].$save['begin_time']) {
                $save['begin'] = $save['begin_date'].' '.$save['begin_time'].':01';
                $save['end'] = $save['end_date'].' '.$save['end_time'].':00';
                $save['id'] = $booking->id;
                $save['member_id'] = $booking->member_id;
                $save['department'] = $booking->department;

                if (!Model::availability($save)) {
                    $errors['begin_date'] = 'Booking are not available at select time';
                }
                if (!Model::driverAvailability($save)) {
                    $errors['chauffeur'] = 'The driver already has another booking at this time';
                }

                unset($save['begin_date']);
                unset($save['begin_time']);
                unset($save['end_date']);
                unset($save['end_time']);

                if ($booking->id === 0) {
                    $save['created_at'] = date('Y-m-d H:i:s');
                }
            } else {
                $errors['end_date'] = 'End date must be greater than begin date';
            }

            if ($booking->id > 0 && $booking->status === Helper::STATUS_RETURNED_FOR_EDIT) {
                // comes from editing return to pending approval status again.
                $save['status'] = Helper::STATUS_PENDING_REVIEW;
            } else {
                $save['status'] = $booking->status;
                $save['closed'] = $booking->closed;
                $save['approve'] = $booking->approve;
            }
        }

        return $errors;
    }

    /**
     * Cancel booking.
     *
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function cancel(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'POST');
            $this->validateCsrfToken($request);

            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->redirectResponse('/login', 'Unauthorized', 401);
            }

            $id = $request->post('id')->toInt();
            $row = Model::getRecord((int) $login->id, $id);
            if ($row === null) {
                return $this->errorResponse('No data available', 404);
            }
            if (!Helper::canCancelBookingByRequester($row)) {
                return $this->errorResponse('This booking can no longer be cancelled', 403);
            }

            Model::updateStatus($id, Helper::STATUS_CANCELLED_BY_REQUESTER);

            // Log cancellation
            \Index\Log\Model::add($id, 'car', 'Cancel', 'Cancelled car booking: '.$id, $login->id);

            // Send notification
            $message = \Car\Email\Controller::sendByBookingId($id);

            return $this->redirectResponse('/car-my-bookings', $message);
        } catch (\Exception $e) {
            return $this->errorResponse(self::exceptionMessage($e), $e->getCode() ?: 500, $e);
        }
    }
}
