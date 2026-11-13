<?php
/**
 * @filesource modules/car/models/catalog.php
 */

namespace Car\Catalog;

use Car\Helper\Controller as Helper;

class Model extends \Kotchasan\Model
{
    /**
     * Return a single vehicle for the member-facing catalog.
     *
     * @param int $id
     * @param bool $activeOnly
     *
     * @return object|null
     */
    public static function get(int $id, bool $activeOnly = true)
    {
        if ($id <= 0) {
            return null;
        }

        $query = self::createCatalogQuery($activeOnly)
            ->where(['V.id', $id]);

        $row = $query->first();

        return $row ? self::hydrateItem($row) : null;
    }

    /**
     * Return vehicles for the member-facing catalog.
     *
     * @param bool $activeOnly
     *
     * @return array
     */
    public static function getItems(bool $activeOnly = true): array
    {
        $query = self::createCatalogQuery($activeOnly);

        $items = [];
        foreach ($query->fetchAll() as $row) {
            $items[] = self::hydrateItem($row);
        }

        return $items;
    }

    /**
     * Base query for catalog rows.
     *
     * @param bool $activeOnly
     *
     * @return \Kotchasan\QueryBuilder\QueryBuilderInterface
     */
    protected static function createCatalogQuery(bool $activeOnly = true)
    {
        $query = static::createQuery()
            ->select(
                'V.id',
                'V.number',
                'V.color',
                'V.seats',
                'V.detail',
                'V.is_active',
                'Brand.topic brand_name',
                'Type.topic type_name'
            )
            ->from('vehicles V')
            ->join('vehicles_meta BrandMeta', [['BrandMeta.vehicle_id', 'V.id'], ['BrandMeta.name', 'car_brand']], 'LEFT')
            ->join('category Brand', [['Brand.category_id', 'BrandMeta.value'], ['Brand.type', 'car_brand']], 'LEFT')
            ->join('vehicles_meta TypeMeta', [['TypeMeta.vehicle_id', 'V.id'], ['TypeMeta.name', 'car_type']], 'LEFT')
            ->join('category Type', [['Type.category_id', 'TypeMeta.value'], ['Type.type', 'car_type']], 'LEFT')
            ->orderBy('V.number');

        if ($activeOnly) {
            $query->where(['V.is_active', 1]);
        }

        return $query;
    }

    /**
     * Add gallery and derived fields to a catalog item.
     *
     * @param object $row
     *
     * @return object
     */
    protected static function hydrateItem($row)
    {
        $gallery = Helper::getVehicleGallery((int) $row->id);
        $row->brand_name = (string) ($row->brand_name ?? '');
        $row->type_name = (string) ($row->type_name ?? '');
        $row->detail = (string) ($row->detail ?? '');
        $row->gallery = $gallery;
        $row->gallery_count = count($gallery);
        $row->first_image_url = $gallery[0]['url'] ?? null;
        $row->booking_url = '/car-booking?vehicle_id='.(int) $row->id;

        return $row;
    }
}