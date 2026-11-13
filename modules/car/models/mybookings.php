<?php
/**
 * @filesource modules/car/models/mybookings.php
 */

namespace Car\Mybookings;

class Model extends \Kotchasan\Model
{
    /**
     * Query bookings for current member.
     *
     * @param array $params
     * @param object $login
     *
     * @return \Kotchasan\QueryBuilder\QueryBuilderInterface
     */
    public static function toDataTable(array $params, $login)
    {
        $where = [
            ['R.member_id', (int) $login->id]
        ];
        if ($params['status'] !== '') {
            $where[] = ['R.status', (int) $params['status']];
        }
        $query = static::createQuery()
            ->select(
                'R.id',
                'R.vehicle_id',
                'R.reason',
                'R.detail',
                'R.travelers',
                'R.begin',
                'R.end',
                'R.status',
                'R.chauffeur',
                'R.created_at',
                'V.number vehicle_number',
                'V.color vehicle_color',
                'U.name member_name'
            )
            ->from('car_reservation R')
            ->join('vehicles V', ['V.id', 'R.vehicle_id'], 'LEFT')
            ->join('user U', ['U.id', 'R.member_id'], 'LEFT')
            ->where($where);

        if (!empty($params['search'])) {
            $search = '%'.$params['search'].'%';
            $query->where([
                ['V.number', 'LIKE', $search],
                ['R.detail', 'LIKE', $search]
            ], 'OR');
        }

        return $query;
    }
}