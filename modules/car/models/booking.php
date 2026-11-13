<?php
/**
 * @filesource modules/car/models/booking.php
 */

namespace Car\Booking;

use Car\Helper\Controller as Helper;
use Kotchasan\Date;

class Model extends \Kotchasan\Model
{
    /**
     * Get booking form data.
     *
     * @param object $login
     * @param int $id
     *
     * @return object|null
     */
    public static function get($login, int $id = 0, int $prefillVehicleId = 0)
    {
        if ($id <= 0) {
            // New booking with optional pre-filled vehicle
            $department = isset($login->metas['department'][0]) ? trim((string) $login->metas['department'][0]) : '';
            $closedLevel = Helper::getApprovalLevelCount();
            $record = (object) [
                'id' => 0,
                'member_id' => (int) $login->id,
                'member_name' => (string) $login->name,
                'department' => $department,
                'department_name' => Helper::getDepartmentName($department),
                'vehicle_id' => $prefillVehicleId,
                'reason' => '',
                'detail' => '',
                'comment' => '',
                'travelers' => 1,
                'begin' => '',
                'end' => '',
                'begin_date' => '',
                'begin_time' => '',
                'end_date' => '',
                'end_time' => '',
                'chauffeur' => '0',
                'car_accessories' => [],
                'canEdit' => true,
                'approve' => 1,
                'closed' => $closedLevel > 0 ? $closedLevel : 1
            ];

            if ($closedLevel === 0) {
                // Immediate approval
                $record->status = Helper::STATUS_APPROVED;
            } else {
                // Approval level 1
                $record->status = Helper::STATUS_PENDING_REVIEW;
            }

            return $record;
        }
        // Existing booking
        $record = static::createQuery()
            ->select('R.*', 'U.name member_name')
            ->from('car_reservation R')
            ->join('user U', ['U.id', 'R.member_id'], 'LEFT')
            ->where([
                ['R.id', $id],
                ['R.member_id', $login->id]
            ])
            ->first();

        if (!$record) {
            return null;
        }

        $meta = self::getMetaValues($id);

        $record->begin_date = !empty($record->begin) ? date('Y-m-d', strtotime((string) $record->begin)) : '';
        $record->end_date = !empty($record->end) ? date('Y-m-d', strtotime((string) $record->end)) : '';
        $record->begin_time = !empty($record->begin) ? date('H:i', strtotime((string) $record->begin)) : '';
        $record->end_time = !empty($record->end) ? date('H:i', strtotime((string) $record->end)) : '';
        $record->car_accessories = self::csvToArray($meta['car_accessories'] ?? '');
        $record->canEdit = Helper::canEditBooking($record);
        $record->status_text = Helper::getStatusText($record);
        $record->department_name = Helper::getDepartmentName($record->department);

        return $record;
    }

    /**
     * Get an owned reservation record.
     *
     * @param int $memberId
     * @param int $id
     *
     * @return object|null
     */
    public static function getRecord(int $memberId, int $id)
    {
        return static::createQuery()
            ->select()
            ->from('car_reservation')
            ->where([
                ['id', $id],
                ['member_id', $memberId]
            ])
            ->first();
    }

    /**
     * Get any reservation record.
     *
     * @param int $id
     *
     * @return object|null
     */
    public static function getById(int $id)
    {
        return static::createQuery()
            ->select()
            ->from('car_reservation')
            ->where(['id', $id])
            ->first();
    }

    /**
     * Get a booking row with detail joins used by views and notifications.
     *
     * @param int $id
     *
     * @return object|null
     */
    public static function getDetailRow(int $id)
    {
        if ($id <= 0) {
            return null;
        }

        return static::createQuery()
            ->select(
                'R.id',
                'R.vehicle_id',
                'R.member_id',
                'R.department',
                'R.reason',
                'R.detail',
                'R.comment',
                'R.travelers',
                'R.begin',
                'R.end',
                'R.chauffeur',
                'R.status',
                'R.approve',
                'R.closed',
                'U.name member_name',
                'U.username member_email',
                'U.line_uid member_line_uid',
                'U.telegram_id member_telegram_id',
                'V.number vehicle_number',
                'Driver.id chauffeur_id',
                'Driver.name chauffeur_name',
                'Driver.username chauffeur_email',
                'Driver.line_uid chauffeur_line_uid',
                'Driver.telegram_id chauffeur_telegram_id',
                'Accessories.value car_accessories',
                'ReviewNote.value review_note'
            )
            ->from('car_reservation R')
            ->join('vehicles V', ['V.id', 'R.vehicle_id'], 'LEFT')
            ->join('user U', ['U.id', 'R.member_id'], 'LEFT')
            ->join('user Driver', ['Driver.id', 'R.chauffeur'], 'LEFT')
            ->join('car_reservation_data Accessories', [['Accessories.reservation_id', 'R.id'], ['Accessories.name', 'car_accessories']], 'LEFT')
            ->join('car_reservation_data ReviewNote', [['ReviewNote.reservation_id', 'R.id'], ['ReviewNote.name', 'review_note']], 'LEFT')
            ->where(['R.id', $id])
            ->first();
    }

    /**
     * Get normalized booking detail data.
     *
     * @param int $id
     *
     * @return array|null
     */
    public static function getDetailData(int $id): ?array
    {
        $row = self::getDetailRow($id);
        if (!$row) {
            return null;
        }

        $driverMode = Helper::getDriverModeFromChauffeur((int) ($row->chauffeur ?? 0));

        return [
            'id' => (int) $row->id,
            'member_id' => (int) $row->member_id,
            'member_name' => (string) $row->member_name,
            'member_email' => (string) $row->member_email,
            'member_line_uid' => (string) $row->member_line_uid,
            'member_telegram_id' => (string) $row->member_telegram_id,
            'department' => (string) $row->department,
            'department_name' => Helper::getDepartmentName($row->department),
            'vehicle_id' => (int) $row->vehicle_id,
            'vehicle_number' => (string) $row->vehicle_number,
            'reason' => (string) $row->reason,
            'detail' => (string) $row->detail,
            'comment' => (string) $row->comment,
            'travelers' => (int) $row->travelers,
            'begin' => (string) $row->begin,
            'end' => (string) $row->end,
            'begin_text' => Date::format($row->begin, 'd M Y H:i'),
            'end_text' => Date::format($row->end, 'd M Y H:i'),
            'driver_text' => Helper::getDriverOptionText(
                $driverMode,
                (string) ($row->chauffeur_name ?? ''),
                (string) $row->member_name
            ),
            'accessories_text' => Helper::formatAccessoryNames($row->car_accessories ?? ''),
            'status' => Helper::getStatusValue($row),
            'status_text' => Helper::getStatusText($row),
            'approve' => (int) ($row->approve ?? 1),
            'closed' => (int) ($row->closed ?? 1),
            'review_note' => (string) $row->review_note,
            'vehicle_image_url' => Helper::getVehicleFirstImageUrl((int) $row->vehicle_id),
            'chauffeur_id' => (int) ($row->chauffeur_id ?? 0),
            'chauffeur_name' => (string) ($row->chauffeur_name ?? ''),
            'chauffeur_email' => (string) ($row->chauffeur_email ?? ''),
            'chauffeur_line_uid' => (string) ($row->chauffeur_line_uid ?? ''),
            'chauffeur_telegram_id' => (string) ($row->chauffeur_telegram_id ?? '')
        ];
    }

    /**
     * ข้อมูลของหน้าต่างรายละเอียด (car/reservation-view.html) — ข้อมูลล้วน: begin · end ดิบ
     * ไม่มีข้อความที่ server จัดรูป และไม่มีช่องทางติดต่อผู้จอง/คนขับ (อีเมล · LINE · Telegram) ที่ getDetailData()
     * เก็บไว้ให้ตัวแจ้งเตือน — หน้าต่างนี้เปิดจากปฏิทินที่ผู้ไม่ได้เข้าระบบดูได้ (dashboard_guest)
     *
     * @param array $detail getDetailData()
     *
     * @return array
     */
    public static function viewData(array $detail): array
    {
        return array_diff_key($detail, array_flip([
            'member_email', 'member_line_uid', 'member_telegram_id',
            'chauffeur_email', 'chauffeur_line_uid', 'chauffeur_telegram_id', 'begin_text', 'end_text'
        ]));
    }

    /**
     * Check if a user may view a booking detail record.
     *
     * @param object $login
     * @param array|null $detail
     *
     * @return bool
     */
    public static function canView($login, ?array $detail): bool
    {
        if (!$detail) {
            return false;
        }

        $userId = (int) ($login->id ?? 0);
        if ($userId <= 0) {
            return false;
        }

        if (Helper::canApproveRequests($login)) {
            return true;
        }

        return in_array($userId, [
            (int) ($detail['member_id'] ?? 0),
            (int) ($detail['chauffeur_id'] ?? 0)
        ], true);
    }

    /**
     * Save reservation.
     *
     * @param int $id
     * @param array $save
     * @param array $meta
     *
     * @return int
     */
    public static function saveReservation(int $id, array $save, array $meta): int
    {
        $db = \Kotchasan\DB::create();
        if ($id > 0) {
            $db->update('car_reservation', ['id', $id], $save);
        } else {
            $id = (int) $db->insert('car_reservation', $save);
        }

        self::saveMeta($id, $meta);

        return $id;
    }

    /**
     * Update reservation status and optional meta fields.
     *
     * @param int $id
     * @param int $status
     * @param array $fields
     * @param array $metaUpdates
     *
     * @return void
     */
    public static function updateStatus(int $id, int $status, array $fields = [], array $metaUpdates = []): void
    {
        if ($id <= 0) {
            return;
        }

        $save = array_merge($fields, [
            'status' => Helper::normalizeStatusId($status)
        ]);

        \Kotchasan\DB::create()->update('car_reservation', ['id', $id], $save);

        if (!empty($metaUpdates)) {
            $meta = self::getMetaValues($id);
            foreach ($metaUpdates as $name => $value) {
                $value = is_array($value) ? implode(',', $value) : trim((string) $value);
                if ($value === '') {
                    unset($meta[$name]);
                } else {
                    $meta[$name] = $value;
                }
            }
            self::saveMeta($id, $meta);
        }
    }

    /**
     * Delete reservations owned by a member.
     *
     * @param int $memberId
     * @param int $id
     *
     * @return int
     */
    public static function removeOwned(int $memberId, int $id): int
    {
        if ($memberId <= 0 || $id <= 0) {
            return 0;
        }

        $rows = static::createQuery()
            ->select('id', 'status')
            ->from('car_reservation')
            ->where([
                ['id', $id],
                ['member_id', $memberId]
            ])
            ->fetchAll();

        $allowedIds = [];
        foreach ($rows as $row) {
            if (Helper::canDeleteBookingByRequester($row)) {
                $allowedIds[] = (int) $row->id;
            }
        }

        if (empty($allowedIds)) {
            return 0;
        }

        $db = \Kotchasan\DB::create();
        $db->delete('car_reservation_data', ['reservation_id', $allowedIds], 0);

        return (int) $db->delete('car_reservation', [
            ['id', $allowedIds],
            ['member_id', $memberId]
        ], 0);
    }

    /**
     * Save reservation meta.
     *
     * @param int $id
     * @param array $meta
     *
     * @return void
     */
    public static function saveMeta(int $id, array $meta): void
    {
        $db = \Kotchasan\DB::create();
        $db->delete('car_reservation_data', ['reservation_id', $id], 0);

        foreach ($meta as $name => $value) {
            if (is_array($value)) {
                $value = implode(',', array_values(array_filter(array_map('strval', $value), static function ($item) {
                    return $item !== '';
                })));
            }
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }

            $db->insert('car_reservation_data', [
                'reservation_id' => $id,
                'name' => $name,
                'value' => $value
            ]);
        }
    }

    /**
     * Get reservation meta as array.
     *
     * @param int $id
     *
     * @return array
     */
    public static function getMetaValues(int $id): array
    {
        $rows = static::createQuery()
            ->select('name', 'value')
            ->from('car_reservation_data')
            ->where(['reservation_id', $id])
            ->fetchAll();

        $meta = [];
        foreach ($rows as $row) {
            $meta[$row->name] = $row->value;
        }

        return $meta;
    }

    /**
     * Check for datetime overlap on the same vehicle.
     *
     * @param array $save
     *
     * @return bool
     */
    public static function availability($save): bool
    {
        $where = [
            ['vehicle_id', $save['vehicle_id']],
            ['status', Helper::holdingStatuses()],
            ['begin', '<', $save['end']],
            ['end', '>', $save['begin']]
        ];
        if ($save['id'] > 0) {
            $where[] = ['id', '!=', $save['id']];
        }
        $result = static::createQuery()
            ->select('id')
            ->from('car_reservation')
            ->where($where)
            ->first();

        return $result ? false : true;
    }

    /**
     * Check that a driver is not already assigned to another booking at the same time.
     *
     * @param array $save id, chauffeur, begin, end
     *
     * @return bool
     */
    public static function driverAvailability($save): bool
    {
        if ((int) ($save['chauffeur'] ?? 0) <= 0) {
            return true;
        }
        $where = [
            ['chauffeur', (int) $save['chauffeur']],
            ['status', Helper::holdingStatuses()],
            ['begin', '<', $save['end']],
            ['end', '>', $save['begin']]
        ];
        if ($save['id'] > 0) {
            $where[] = ['id', '!=', $save['id']];
        }
        $result = static::createQuery()
            ->select('id')
            ->from('car_reservation')
            ->where($where)
            ->first();

        return $result ? false : true;
    }

    /**
     * Convert CSV values to array of strings.
     *
     * @param string|null $csv
     *
     * @return array
     */
    public static function csvToArray(?string $csv): array
    {
        if ($csv === null || trim($csv) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $csv)), static function ($value) {
            return $value !== '';
        }));
    }
}
