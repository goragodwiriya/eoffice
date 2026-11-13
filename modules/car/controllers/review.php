<?php
/**
 * @filesource modules/car/controllers/review.php
 */

namespace Car\Review;

use Car\Booking\Model as BookingModel;
use Car\Helper\Controller as Helper;
use Gcms\Api as ApiController;
use Kotchasan\Http\Request;

class Controller extends ApiController
{
    /**
     * Get review details.
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
            if (!Helper::canApproveRequests($login)) {
                return $this->errorResponse('Forbidden', 403);
            }

            $row = Model::get($request->get('id')->toInt());
            if ($row === null) {
                return $this->redirectResponse('/404', 'No data available', 404);
            }

            $includeDriver = (int) ($row->chauffeur ?? 0) > 0 ? (int) $row->chauffeur : null;
            $canProcess = Model::canProcess($row);
            $canApproveAction = $canProcess && Helper::canApproveStep($login, $row);

            return $this->successResponse([
                'id' => (int) $row->id,
                'member_name' => (string) $row->member_name,
                'department_name' => (string) $row->department_name,
                'vehicle_number' => (string) $row->vehicle_number,
                'reason' => (string) $row->reason,
                'detail' => (string) $row->detail,
                'comment' => (string) $row->comment,
                'travelers' => (int) $row->travelers,
                'begin' => (string) $row->begin,
                'end' => (string) $row->end,
                'status_text' => Helper::getStatusText($row),
                'driver_option_text' => (string) $row->driver_option_text,
                'accessories_text' => (string) $row->accessories_text,
                'chauffeur' => (string) ((int) ($row->chauffeur ?? 0) === -1 ? -1 : ((int) ($row->chauffeur ?? 0) > 0 ? (int) $row->chauffeur : '')),
                'approval_reason' => (string) $row->review_note,
                'canProcess' => $canProcess,
                'canApproveAction' => $canApproveAction,
                'canCancelAction' => Helper::canCancelBookingByOfficer($row),
                'options' => [
                    'chauffeur' => Helper::getAssignableDriverOptions($includeDriver)
                ]
            ], 'Review data retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse(self::exceptionMessage($e), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * Approve reservation.
     *
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function approve(Request $request)
    {
        return $this->processDecision($request, Helper::STATUS_APPROVED, 'Approved car booking', false);
    }

    /**
     * Reject reservation.
     *
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function reject(Request $request)
    {
        return $this->processDecision($request, Helper::STATUS_REJECTED, 'Rejected car booking', true);
    }

    /**
     * Return reservation for correction.
     *
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function returnedit(Request $request)
    {
        return $this->processDecision($request, Helper::STATUS_RETURNED_FOR_EDIT, 'Returned car booking for correction', true);
    }

    /**
     * Cancel reservation by officer.
     *
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function cancelofficer(Request $request)
    {
        return $this->processDecision($request, Helper::STATUS_CANCELLED_BY_OFFICER, 'Cancelled car booking by officer', true, true);
    }

    /**
     * Shared decision handler.
     *
     * @param Request $request
     * @param int $status
     * @param string $logTopic
     * @param bool $requireReason
     * @param bool $allowOfficerCancel
     *
     * @return \Kotchasan\Http\Response
     */
    protected function processDecision(Request $request, int $status, string $logTopic, bool $requireReason, bool $allowOfficerCancel = false)
    {
        try {
            ApiController::validateMethod($request, 'POST');
            $this->validateCsrfToken($request);

            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->errorResponse('Unauthorized', 401);
            }
            if (!Helper::canApproveRequests($login)) {
                return $this->errorResponse('Permission required', 403);
            }

            $id = $request->post('id')->toInt();
            $approvalReason = trim($request->post('approval_reason')->textarea());
            $chauffeur = $request->post('chauffeur')->toInt();

            $row = Model::get($id);
            if ($row === null) {
                return $this->errorResponse('No data available', 404);
            }

            // ยกเลิกโดยเจ้าหน้าที่ใช้กติกาของตัวเอง (รอตรวจสอบ · อนุมัติแล้ว · ส่งกลับแก้ไข) ตามที่ get()
            // บอกหน้าเว็บผ่าน canCancelAction — canProcess() รับเฉพาะใบที่รอตรวจสอบ ใบที่อนุมัติแล้วจึงยกเลิกไม่ได้
            if ($allowOfficerCancel) {
                if (!Helper::canCancelBookingByOfficer($row)) {
                    return $this->errorResponse('This booking can no longer be cancelled', 400);
                }
            } elseif (!Model::canProcess($row)) {
                return $this->errorResponse('This booking has already been processed', 400);
            }
            if (!Helper::canApproveStep($login, $row)) {
                return $this->errorResponse('You are not allowed to approve this step', 403);
            }

            if ($requireReason && $approvalReason === '') {
                return $this->errorResponse('Decision note is required', 400);
            }

            $currentApprove = (int) ($row->approve ?? 1);
            $closedLevel = (int) ($row->closed ?? 1);

            $save = [];
            $finalStatus = $status;
            if ($status === Helper::STATUS_APPROVED) {
                if (!BookingModel::availability([
                    'id' => (int) $row->id,
                    'vehicle_id' => (int) $row->vehicle_id,
                    'begin' => (string) $row->begin,
                    'end' => (string) $row->end
                ])) {
                    return $this->errorResponse('Booking are not available at select time', 400);
                }

                $currentChauffeur = (int) ($row->chauffeur ?? 0);
                $effectiveChauffeur = $chauffeur !== 0 ? $chauffeur : $currentChauffeur;
                if ($effectiveChauffeur === 0) {
                    return $this->errorResponse('Approval requires self drive or an assigned driver', 400);
                }
                if ($effectiveChauffeur > 0 && !Helper::canBeDriver($effectiveChauffeur)) {
                    return $this->errorResponse('Selected driver is invalid', 400);
                }
                if (!BookingModel::driverAvailability([
                    'id' => (int) $row->id,
                    'chauffeur' => $effectiveChauffeur,
                    'begin' => (string) $row->begin,
                    'end' => (string) $row->end
                ])) {
                    return $this->errorResponse('The driver already has another booking at this time', 400);
                }

                if ($currentApprove >= $closedLevel || \Gcms\Api::isAdmin($login)) {
                    // Final approval
                    $save['approve'] = $closedLevel;
                    $save['chauffeur'] = $effectiveChauffeur;
                } else {
                    // Next step approval
                    $finalStatus = Helper::STATUS_PENDING_REVIEW;
                    $nextApprove = Helper::getNextApprovalStep($currentApprove);
                    $save['approve'] = $nextApprove > 0 ? $nextApprove : $closedLevel;
                    $save['chauffeur'] = $effectiveChauffeur;
                }
            } else {
                $save['approve'] = $currentApprove;
            }

            BookingModel::updateStatus($id, $finalStatus, $save, [
                'review_note' => $approvalReason
            ]);

            \Index\Log\Model::add($id, 'car', 'Status', $logTopic.': '.$id, $login->id, $approvalReason, [
                'status' => $finalStatus,
                'chauffeur' => $chauffeur
            ]);

            $message = \Car\Email\Controller::sendByBookingId($id);

            return $this->redirectResponse('/car-approvals', $message);
        } catch (\Exception $e) {
            return $this->errorResponse(self::exceptionMessage($e), $e->getCode() ?: 500, $e);
        }
    }
}
