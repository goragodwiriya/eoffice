<?php
/**
 * @filesource modules/car/controllers/helper.php
 */

namespace Car\Helper;

use Car\Category\Controller as CarCategory;
use Gcms\Api as ApiController;
use Kotchasan\Date;
use Kotchasan\Language;
use Kotchasan\Text;

class Controller extends \Gcms\Controller
{
    public const STATUS_PENDING_REVIEW = 0;
    public const STATUS_APPROVED = 1;
    public const STATUS_REJECTED = 2;
    public const STATUS_CANCELLED_BY_REQUESTER = 3;
    public const STATUS_CANCELLED_BY_OFFICER = 4;
    public const STATUS_RETURNED_FOR_EDIT = 5;

    public const APPROVAL_BEFORE_END = 0;
    public const APPROVAL_BEFORE_START = 1;
    public const APPROVAL_ALWAYS = 2;

    public const CANCELLATION_PENDING_ONLY = 0;
    public const CANCELLATION_BEFORE_DATE = 1;
    public const CANCELLATION_BEFORE_END = 2;
    public const CANCELLATION_BEFORE_START = 3;
    public const CANCELLATION_ALWAYS = 4;

    /**
     * Cached vehicle galleries for the current request.
     *
     * @var array
     */
    protected static $vehicleGalleryCache = [];

    /**
     * Statuses that hold the booked time.
     * Pending and returned-for-edit bookings hold it too, so two people cannot
     * submit the same slot and leave the approver to pick one.
     *
     * @return int[]
     */
    public static function holdingStatuses(): array
    {
        return [self::STATUS_PENDING_REVIEW, self::STATUS_APPROVED, self::STATUS_RETURNED_FOR_EDIT];
    }

    /**
     * Get active approval steps keyed by step number.
     * `approve_level` is the source of truth for how many steps are enabled.
     *
     * @return array<int, array{status:int, department:string}>
     */
    public static function getApprovalSteps(): array
    {
        $levelCount = max(0, (int) (self::$cfg->car_approve_level ?? 0));
        if ($levelCount === 0) {
            return [];
        }

        $statuses = (array) (self::$cfg->car_approve_status ?? []);
        $departments = (array) (self::$cfg->car_approve_department ?? []);
        $steps = [];
        for ($level = 1; $level <= $levelCount; $level++) {
            if (!array_key_exists($level, $statuses)) {
                break;
            }
            $steps[$level] = [
                'status' => (int) $statuses[$level],
                'department' => isset($departments[$level]) ? (string) $departments[$level] : ''
            ];
        }

        return $steps;
    }

    /**
     * Get the number of active approval steps.
     *
     * @return int
     */
    public static function getApprovalLevelCount(): int
    {
        return count(self::getApprovalSteps());
    }

    /**
     * Get configuration for a single approval step.
     *
     * @param int $step
     *
     * @return array{status:int, department:string}|null
     */
    public static function getApprovalStepConfig(int $step): ?array
    {
        $steps = self::getApprovalSteps();

        return $steps[$step] ?? null;
    }

    /**
     * Get the next configured approval step after the current one.
     *
     * @param int $currentStep
     *
     * @return int
     */
    public static function getNextApprovalStep(int $currentStep): int
    {
        $steps = array_keys(self::getApprovalSteps());
        $index = array_search($currentStep, $steps, true);

        if ($index === false || !isset($steps[$index + 1])) {
            return 0;
        }

        return (int) $steps[$index + 1];
    }

    /**
     * Determine the approver level available to this login.
     * -1 means admin approval access, 0 means no approval access.
     *
     * @param object|null $login
     *
     * @return int
     */
    public static function getApproveLevel($login): int
    {
        if (!$login) {
            return 0;
        }
        $steps = self::getApprovalSteps();
        if (empty($steps)) {
            return 0;
        }
        if (ApiController::isAdmin($login)) {
            return -1;
        }

        $loginDepartment = isset($login->metas['department'][0]) ? trim((string) $login->metas['department'][0]) : '';

        foreach ($steps as $level => $step) {
            if ((int) $step['status'] !== (int) $login->status) {
                continue;
            }
            $department = $step['department'];
            if ($department === '' || $department === $loginDepartment) {
                return (int) $level;
            }
        }

        return 0;
    }

    /**
     * Check if user can approve the current step of a request.
     *
     * @param object|null $login
     * @param object|array $request
     *
     * @return bool
     */
    public static function canApproveStep($login, $request): bool
    {
        if (!$login) {
            return false;
        }
        $steps = self::getApprovalSteps();
        if (empty($steps)) {
            return false;
        }

        if (ApiController::isAdmin($login)) {
            return true;
        }

        $approve = is_array($request) ? ($request['approve'] ?? 1) : ($request->approve ?? 1);
        $department = is_array($request) ? ($request['department'] ?? '') : ($request->department ?? '');
        $step = $steps[$approve] ?? null;
        $loginDepartment = isset($login->metas['department'][0]) ? trim((string) $login->metas['department'][0]) : '';
        if ($step !== null && (int) $login->status === (int) $step['status']) {
            if ($step['department'] === '') {
                return (string) $department === $loginDepartment;
            }

            return $step['department'] === $loginDepartment;
        }

        return false;
    }

    /**
     * Permission helper for approval workflow.
     *
     * @param object|null $login
     *
     * @return bool
     */
    public static function canApproveRequests($login): bool
    {
        return self::getApproveLevel($login) !== 0;
    }

    /**
     * Check whether the login should be allowed into approval pages.
     *
     * Approval-area access should follow the configured step rules: the login
     * must match a configured approver status and, when configured, department.
     *
     * @param object|null $login
     *
     * @return bool
     */
    public static function canAccessApprovalArea($login): bool
    {
        if (!$login) {
            return false;
        }

        $steps = self::getApprovalSteps();
        if (empty($steps)) {
            return false;
        }

        if (ApiController::isAdmin($login)) {
            return true;
        }

        return self::canApproveRequests($login);
    }

    /**
     * Get vehicle gallery images.
     *
     * @param int $vehicleId
     *
     * @return array
     */
    public static function getVehicleGallery(int $vehicleId): array
    {
        if ($vehicleId <= 0) {
            return [];
        }

        if (!array_key_exists($vehicleId, self::$vehicleGalleryCache)) {
            $files = \Download\Index\Controller::getAttachments($vehicleId, 'car', self::$cfg->img_typies);
            self::$vehicleGalleryCache[$vehicleId] = array_values(array_filter($files, static function ($file) {
                return !empty($file['is_image']);
            }));
        }

        return self::$vehicleGalleryCache[$vehicleId];
    }

    /**
     * Get the first vehicle image URL.
     *
     * @param int $vehicleId
     *
     * @return string|null
     */
    public static function getVehicleFirstImageUrl(int $vehicleId): ?string
    {
        $gallery = self::getVehicleGallery($vehicleId);

        return $gallery[0]['url'] ?? null;
    }

    /**
     * Check if the vehicle exists.
     *
     * @param int $vehicleId
     *
     * @return bool
     */
    public static function vehicleExists(int $vehicleId): bool
    {
        if ($vehicleId <= 0) {
            return false;
        }

        return \Kotchasan\Model::createQuery()
            ->select('id')
            ->from('vehicles')
            ->where(['id', $vehicleId])
            ->first() !== null;
    }

    /**
     * Vehicle options.
     *
     * @param bool $activeOnly
     * @param int|null $includeId
     *
     * @return array
     */
    public static function getVehicleOptions(bool $activeOnly = true, ?int $includeId = null): array
    {
        $query = \Kotchasan\Model::createQuery()
            ->select('id', 'number', 'is_active')
            ->from('vehicles')
            ->orderBy('number');

        if ($activeOnly) {
            if ($includeId !== null && $includeId > 0) {
                $query->whereRaw('((is_active = 1) OR (id = :include_id))', 'AND', [
                    'include_id' => $includeId
                ]);
            } else {
                $query->where(['is_active', 1]);
            }
        }

        $options = [];
        foreach ($query->fetchAll() as $item) {
            $label = (string) $item->number;
            if ((int) $item->is_active !== 1) {
                $label .= ' (inactive)';
            }
            $options[] = [
                'value' => (string) $item->id,
                'text' => $label
            ];
        }

        return $options;
    }

    /**
     * Driver options from members that have can_drive_car permission.
     *
     * @param int|null $includeId
     *
     * @return array
     */
    public static function getDriverOptions(?int $includeId = null): array
    {
        $query = \Kotchasan\Model::createQuery()
            ->select('id', 'name')
            ->from('user')
            ->orderBy('name');

        if ($includeId !== null && $includeId > 0) {
            $query->whereRaw('((active = 1 AND permission LIKE :driver_permission) OR id = :include_id)', 'AND', [
                'driver_permission' => '%can_drive_car%',
                'include_id' => $includeId
            ]);
        } else {
            $query->where(['active', 1]);
            $query->whereRaw('(permission LIKE :driver_permission)', 'AND', [
                'driver_permission' => '%can_drive_car%'
            ]);
        }

        $options = [
            ['value' => '-1', 'text' => '{LNG_No request} ({LNG_Self drive})'],
            ['value' => '0', 'text' => '{LNG_Not specified} ({LNG_anyone})']
        ];
        foreach ($query->fetchAll() as $item) {
            $options[] = [
                'value' => (string) $item->id,
                'text' => (string) $item->name
            ];
        }

        return $options;
    }

    /**
     * Driver options for actual assignment (no placeholder choices).
     *
     * @param int|null $includeId
     *
     * @return array
     */
    public static function getAssignableDriverOptions(?int $includeId = null): array
    {
        $query = \Kotchasan\Model::createQuery()
            ->select('id', 'name')
            ->from('user')
            ->orderBy('name');

        if ($includeId !== null && $includeId > 0) {
            $query->whereRaw('((active = 1 AND permission LIKE :driver_permission) OR id = :include_id)', 'AND', [
                'driver_permission' => '%can_drive_car%',
                'include_id' => $includeId
            ]);
        } else {
            $query->where(['active', 1]);
            $query->whereRaw('(permission LIKE :driver_permission)', 'AND', [
                'driver_permission' => '%can_drive_car%'
            ]);
        }

        $options = [
            ['value' => '-1', 'text' => '{LNG_No request} ({LNG_Self drive})']
        ];
        foreach ($query->fetchAll() as $item) {
            $options[] = [
                'value' => (string) $item->id,
                'text' => (string) $item->name
            ];
        }

        return $options;
    }

    /**
     * Check whether a user can be assigned as driver.
     *
     * @param int $userId
     *
     * @return bool
     */
    public static function canBeDriver(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $driver = \Kotchasan\Model::createQuery()
            ->select('id')
            ->from('user')
            ->where([
                ['id', $userId],
                ['active', 1],
                ['permission', 'LIKE', '%can_drive_car%']
            ])
            ->first();

        return $driver !== null;
    }

    /**
     * Accessory options.
     *
     * @return array
     */
    public static function getAccessoryOptions(): array
    {
        return CarCategory::init()->toOptions('car_accessory');
    }

    /**
     * Status options.
     *
     * @return array
     */
    public static function getStatusOptions(): array
    {
        return \Gcms\Controller::arrayToOptions(Language::get('BOOKING_STATUS'));
    }

    /**
     * Return normalized status key.
     *
     * @param object|null $row
     *
     * @return int
     */
    public static function getStatusValue(?object $row): int
    {
        if ($row === null) {
            return self::STATUS_PENDING_REVIEW;
        }

        $status = (int) self::readField($row, 'status');

        return self::normalizeStatusId($status);
    }

    /**
     * Human readable status text.
     *
     * @param object|null $row
     *
     * @return string
     */
    public static function getStatusText(?object $row): string
    {
        return self::getStatusLabel($row->status);
    }

    /**
     * Human readable status label.
     *
     * @param int $status
     *
     * @return string
     */
    public static function getStatusLabel(int $status): string
    {
        return Language::get('BOOKING_STATUS', '-', $status);
    }

    /**
     * Normalize a status value to a supported booking status.
     *
     * @param int $status
     * @param int|null $default
     *
     * @return int
     */
    public static function normalizeStatusId(int $status, ?int $default = null): int
    {
        $booking_status = Language::get('BOOKING_STATUS');
        if (isset($booking_status[$status])) {
            return $status;
        }

        return $default ?? self::STATUS_PENDING_REVIEW;
    }

    /**
     * Configured booking statuses that bookers may permanently delete.
     *
     * @return array
     */
    public static function getBookerDeleteStatuses(): array
    {
        $statuses = self::$cfg->car_delete ?? [];
        if (!is_array($statuses)) {
            $statuses = [$statuses];
        }

        $statuses = array_values(array_unique(array_filter(array_map(static function ($status) {
            return Controller::normalizeStatusId((int) $status, -1);
        }, $statuses), static function ($status) {
            return $status >= 0;
        })));

        // ไม่ได้เลือกสถานะไหนเลย = ผู้จองลบเองไม่ได้ ลบได้เฉพาะผู้อนุมัติ (ตามข้อความในหน้าตั้งค่า)
        return $statuses;
    }

    /**
     * Can the requester edit this booking?
     *
     * @param object|null $row
     *
     * @return bool
     */
    public static function canEditBooking(?object $row): bool
    {
        if ($row === null) {
            return false;
        }

        return in_array(self::getStatusValue($row), [self::STATUS_PENDING_REVIEW, self::STATUS_RETURNED_FOR_EDIT], true);
    }

    /**
     * Can staff still process this booking?
     *
     * @param object|null $row
     *
     * @return bool
     */
    public static function canProcessBooking(?object $row): bool
    {
        if ($row === null) {
            return false;
        }

        return self::getStatusValue($row) === self::STATUS_PENDING_REVIEW
        && self::isWithinApprovalWindow($row);
    }

    /**
     * Can the requester cancel this booking?
     *
     * @param object|null $row
     *
     * @return bool
     */
    public static function canCancelBookingByRequester(?object $row): bool
    {
        if ($row === null) {
            return false;
        }

        if (!in_array($row->status, [
            self::STATUS_PENDING_REVIEW,
            self::STATUS_APPROVED,
            self::STATUS_RETURNED_FOR_EDIT
        ], true)) {
            return false;
        }

        switch ((int) (self::$cfg->car_cancellation ?? self::CANCELLATION_PENDING_ONLY)) {
        case self::CANCELLATION_BEFORE_DATE:
            // ก่อนวันจอง
            return self::isBeforeBoundaryDate($row, 'begin');
        case self::CANCELLATION_BEFORE_END:
            // ก่อนสิ้นสุดเวลาจอง
            return self::isBeforeBoundary($row, 'end');
        case self::CANCELLATION_BEFORE_START:
            // ก่อนถึงเวลาจอง
            return self::isBeforeBoundary($row, 'begin');
        case self::CANCELLATION_ALWAYS:
            // ยกเลิกย้อนหลังได้
            return true;
        }

        // สถานะรอตรวจสอบ หรือ กลับไปแก้ไข
        return in_array($row->status, [self::STATUS_PENDING_REVIEW, self::STATUS_RETURNED_FOR_EDIT], true);
    }

    /**
     * Can the requester delete this booking permanently?
     *
     * @param object|null $row
     *
     * @return bool
     */
    public static function canDeleteBookingByRequester(?object $row): bool
    {
        if ($row === null) {
            return false;
        }

        return in_array($row->status, self::getBookerDeleteStatuses(), true);
    }

    /**
     * Can an officer cancel this booking?
     *
     * @param object|null $row
     *
     * @return bool
     */
    public static function canCancelBookingByOfficer(?object $row): bool
    {
        if ($row === null) {
            return false;
        }

        $status = self::getStatusValue($row);
        if (!in_array($status, [
            self::STATUS_PENDING_REVIEW,
            self::STATUS_APPROVED,
            self::STATUS_RETURNED_FOR_EDIT
        ], true)) {
            return false;
        }

        return self::isWithinApprovalWindow($row);
    }

    /**
     * Check approval/edit timing policy.
     *
     * @param object|null $row
     *
     * @return bool
     */
    public static function isWithinApprovalWindow(?object $row): bool
    {
        if ($row === null) {
            return false;
        }

        switch ((int) (self::$cfg->car_approving ?? self::APPROVAL_BEFORE_END)) {
        case self::APPROVAL_BEFORE_START:
            return self::isBeforeBoundary($row, 'begin');
        case self::APPROVAL_ALWAYS:
            return true;
        default:
            return self::isBeforeBoundary($row, 'end');
        }
    }

    /**
     * Format accessory CSV into labels.
     *
     * @param string|null $csv
     *
     * @return string
     */
    public static function formatAccessoryNames(?string $csv): string
    {
        if ($csv === null || trim($csv) === '') {
            return '';
        }

        $category = CarCategory::init();
        $ids = array_values(array_filter(array_map('trim', explode(',', $csv)), static function ($value) {
            return $value !== '';
        }));
        $labels = [];
        foreach ($ids as $id) {
            $label = $category->get('car_accessory', $id);
            if ($label !== '') {
                $labels[] = $label;
            }
        }

        return implode(', ', $labels);
    }

    /**
     * Department name helper.
     *
     * @param string|null $departmentId
     *
     * @return string
     */
    public static function getDepartmentName(?string $departmentId): string
    {
        if ($departmentId === null || $departmentId === '') {
            return '';
        }

        return (string) \Gcms\Category::init()->get('department', $departmentId);
    }

    /**
     * Format database datetime to datetime-local value.
     *
     * @param string|null $value
     *
     * @return string
     */
    public static function toDateTimeLocal(?string $value): string
    {
        if (empty($value)) {
            return '';
        }

        $timestamp = strtotime($value);
        return $timestamp ? date('Y-m-d\TH:i', $timestamp) : '';
    }

    /**
     * Normalize datetime-local input to database datetime.
     *
     * @param string|null $value
     *
     * @return string
     */
    public static function fromDateTimeLocal(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $timestamp = strtotime($value);
        return $timestamp ? date('Y-m-d H:i:s', $timestamp) : '';
    }

    /**
     * Check that the current time is before a booking boundary.
     *
     * @param object|null $row
     * @param string $field
     *
     * @return bool
     */
    public static function isBeforeBoundary(?object $row, string $field): bool
    {
        if ($row === null) {
            return false;
        }

        $value = self::readField($row, $field);
        if (empty($value)) {
            return true;
        }

        $timestamp = strtotime((string) $value);
        if ($timestamp === false) {
            return true;
        }

        return time() < $timestamp;
    }

    /**
     * Check that the current date is before a booking boundary date.
     *
     * @param object|null $row
     * @param string $field
     *
     * @return bool
     */
    public static function isBeforeBoundaryDate(?object $row, string $field): bool
    {
        if ($row === null) {
            return false;
        }

        $value = self::readField($row, $field);
        if (empty($value)) {
            return true;
        }

        $timestamp = strtotime((string) $value);
        if ($timestamp === false) {
            return true;
        }

        return date('Y-m-d') < date('Y-m-d', $timestamp);
    }

    /**
     * Format the effective driver option text derived from chauffeur.
     *
     * @param string $driverMode
     * @param string $driverName
     * @param string $memberName
     *
     * @return string
     */
    public static function getDriverOptionText(string $driverMode, string $driverName = '', string $memberName = ''): string
    {
        if ($driverMode === 'self') {
            return $memberName !== '' ? '{LNG_Self drive} ('.$memberName.')' : '{LNG_Self drive}';
        }
        if ($driverName !== '') {
            return $driverName;
        }
        if ($driverMode === 'assigned') {
            return '{LNG_Assigned driver}';
        }

        return '{LNG_Not specified} ({LNG_anyone})';
    }

    /**
     * Derive driver mode from the chauffeur value stored on the reservation.
     *
     * @param int $chauffeur
     *
     * @return string
     */
    public static function getDriverModeFromChauffeur(int $chauffeur): string
    {
        if ($chauffeur === -1) {
            return 'self';
        }
        if ($chauffeur > 0) {
            return 'assigned';
        }

        return 'none';
    }

    /**
     * Read object field.
     *
     * @param object|null $row
     * @param string $field
     *
     * @return mixed|null
     */
    protected static function readField(?object $row, string $field)
    {
        if ($row === null) {
            return null;
        }

        return $row->$field ?? null;
    }

    /**
     * คืนค่าเวลาจอง
     *
     * @param object $item
     * @param bool $omitDateOnSameDay
     *
     * @return string
     */
    public static function formatBookingTime(object $item, bool $omitDateOnSameDay = false): string
    {
        if (
            preg_match('/([0-9]{4,4}\-[0-9]{2,2}\-[0-9]{2,2})\s[0-9\:]+$/', $item->begin, $begin) &&
            preg_match('/([0-9]{4,4}\-[0-9]{2,2}\-[0-9]{2,2})\s[0-9\:]+$/', $item->end, $end)
        ) {
            if ($begin[1] == $end[1]) {
                if ($omitDateOnSameDay) {
                    $return = '{LNG_Time} '.Date::format($item->begin, 'H:i').' {LNG_To} '.Date::format($item->end, 'TIME_FORMAT');
                } else {
                    $return = '{LNG_Time} '.Date::format($item->begin, 'DATE_FORMAT').' {LNG_To} '.Date::format($item->end, 'TIME_FORMAT');
                }
            } else {
                $return = Date::format($item->begin).' {LNG_To} '.Date::format($item->end);
            }
            return Language::trans($return);
        }
        return '';
    }
}
