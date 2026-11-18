<?php
/**
 * @filesource modules/car/models/vehicles.php
 */

namespace Car\Vehicles;

class Model extends \Kotchasan\Model
{
    /**
     * Query data to send to DataTable.
     *
     * @param array $params
     *
     * @return \Kotchasan\QueryBuilder\QueryBuilderInterface
     */
    public static function toDataTable($params)
    {
        $where = [];
        if ($params['car_brand'] !== '') {
            $where[] = ['B.value', $params['car_brand']];
        }
        if ($params['car_type'] !== '') {
            $where[] = ['T.value', $params['car_type']];
        }

        return static::createQuery()
            ->select(
                'V.id',
                'V.number',
                'V.color',
                'V.seats',
                'V.detail',
                'V.is_active',
                'B.value car_brand',
                'T.value car_type'
            )
            ->from('vehicles V')
            ->join('vehicles_meta B', [['B.vehicle_id', 'V.id'], ['B.name', 'car_brand']], 'LEFT')
            ->join('vehicles_meta T', [['T.vehicle_id', 'V.id'], ['T.name', 'car_type']], 'LEFT')
            ->where($where);
    }
}