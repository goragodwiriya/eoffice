<?php
/**
 * @filesource modules/car/models/review.php
 */

namespace Car\Review;

use Car\Booking\Model as BookingModel;
use Car\Helper\Controller as Helper;

class Model extends \Kotchasan\Model
{
    /**
     * Get a reservation for approver review.
     *
     * @param int $id
     *
     * @return object|null
     */
    public static function get(int $id)
    {
        if ($id <= 0) {
            return null;
        }

        $row = static::createQuery()
            ->select(
                'R.id',
                'R.member_id',
                'R.vehicle_id',
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
                'U.username member_username',
                'V.number vehicle_number',
                'Driver.name chauffeur_name',
                'C.topic department_name'
            )
            ->from('car_reservation R')
            ->join('user U', ['U.id', 'R.member_id'], 'LEFT')
            ->join('vehicles V', ['V.id', 'R.vehicle_id'], 'LEFT')
            ->join('user Driver', ['Driver.id', 'R.chauffeur'], 'LEFT')
            ->join('category C', [['C.category_id', 'R.department'], ['C.type', 'department']], 'LEFT')
            ->where(['R.id', $id])
            ->first();

        if (!$row) {
            return null;
        }

        $meta = BookingModel::getMetaValues($id);
        $driverMode = Helper::getDriverModeFromChauffeur((int) ($row->chauffeur ?? 0));
        $driverName = !empty($row->chauffeur_name) ? (string) $row->chauffeur_name : '';

        $row->driver_mode = $driverMode;
        $row->driver_option_text = Helper::getDriverOptionText($driverMode, $driverName, (string) $row->member_name);
        $row->car_accessories = BookingModel::csvToArray($meta['car_accessories'] ?? '');
        $row->accessories_text = Helper::formatAccessoryNames($meta['car_accessories'] ?? '');
        $row->review_note = (string) ($meta['review_note'] ?? '');

        return $row;
    }

    /**
     * Can this reservation still be processed?
     *
     * @param object|null $row
     *
     * @return bool
     */
    public static function canProcess($row): bool
    {
        if (!$row) {
            return false;
        }

        return Helper::canProcessBooking($row);
    }
}