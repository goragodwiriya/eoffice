<?php
/**
 * @filesource modules/car/models/vehicle.php
 */

namespace Car\Vehicle;

class Model extends \Kotchasan\Model
{
    /**
     * Get a vehicle for editing.
     *
     * @param int $id
     *
     * @return object|null
     */
    public static function get(int $id)
    {
        $record = (object) [
            'id' => 0,
            'number' => '',
            'color' => '#304FFE',
            'detail' => '',
            'seats' => 4,
            'is_active' => 1,
            'car_brand' => '',
            'car_type' => '',
            'car' => []
        ];

        if ($id > 0) {
            $vehicle = static::createQuery()
                ->select('id', 'number', 'color', 'detail', 'seats', 'is_active')
                ->from('vehicles')
                ->where(['id', $id])
                ->first();

            if (!$vehicle) {
                return null;
            }

            $record = (object) array_merge((array) $record, (array) $vehicle, self::getMetaValues($id));
            $record->car = \Download\Index\Controller::getAttachments($id, 'car', self::$cfg->car_file_types ?? self::$cfg->img_typies);
        }

        return $record;
    }

    /**
     * Save vehicle data.
     *
     * @param int $id
     * @param array $save
     * @param array $meta
     *
     * @return int
     */
    public static function save(int $id, array $save, array $meta): int
    {
        $db = \Kotchasan\DB::create();

        if ($id === 0) {
            $id = (int) $db->insert('vehicles', $save);
        } else {
            $db->update('vehicles', ['id', $id], $save);
        }

        self::saveMeta($id, $meta);

        return $id;
    }

    /**
     * Delete vehicle records and uploaded images.
     *
     * @param array $ids
     *
     * @return int
     */
    public static function remove(array $ids): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (empty($ids)) {
            return 0;
        }

        $db = \Kotchasan\DB::create();
        $db->delete('vehicles_meta', ['vehicle_id', $ids], 0);
        $removed = $db->delete('vehicles', ['id', $ids], 0);

        foreach ($ids as $id) {
            \Kotchasan\File::removeDirectory(ROOT_PATH.DATA_FOLDER.'car/'.$id.'/');
        }

        return (int) $removed;
    }

    /**
     * Toggle active state.
     *
     * @param int $id
     *
     * @return object|null
     */
    public static function toggleActive(int $id)
    {
        $db = \Kotchasan\DB::create();
        $vehicle = $db->first('vehicles', ['id', $id]);
        if (!$vehicle) {
            return null;
        }

        $active = (int) $vehicle->is_active === 1 ? 0 : 1;
        $db->update('vehicles', ['id', $id], ['is_active' => $active]);
        $vehicle->is_active = $active;

        return $vehicle;
    }

    /**
     * Get raw record by ID.
     *
     * @param int $id
     *
     * @return object|null
     */
    public static function getRecord(int $id)
    {
        return static::createQuery()
            ->select('id', 'number', 'is_active')
            ->from('vehicles')
            ->where(['id', $id])
            ->first();
    }

    /**
     * Load vehicle meta into a flat array.
     *
     * @param int $id
     *
     * @return array
     */
    public static function getMetaValues(int $id): array
    {
        $rows = static::createQuery()
            ->select('name', 'value')
            ->from('vehicles_meta')
            ->where(['vehicle_id', $id])
            ->fetchAll();

        $meta = [];
        foreach ($rows as $row) {
            if ($row->name === 'image') {
                if (!isset($meta['image'])) {
                    $meta['image'] = [];
                }
                $meta['image'][] = $row->value;
            } else {
                $meta[$row->name] = $row->value;
            }
        }

        return $meta;
    }

    /**
     * Save vehicle meta fields.
     *
     * @param int $id
     * @param array $meta
     *
     * @return void
     */
    public static function saveMeta(int $id, array $meta): void
    {
        $db = \Kotchasan\DB::create();
        $db->delete('vehicles_meta', ['vehicle_id', $id], 0);

        foreach ($meta as $name => $value) {
            if ($value === '' || $value === null) {
                continue;
            }

            $db->insert('vehicles_meta', [
                'vehicle_id' => $id,
                'name' => $name,
                'value' => $value
            ]);
        }
    }
}