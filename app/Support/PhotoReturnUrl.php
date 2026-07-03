<?php

namespace App\Support;

use App\Models\InspectionArea;
use App\Models\InspectionPhoto;
use App\Models\PropertyHouse;

final class PhotoReturnUrl
{
    public static function forHousePhoto(
        PropertyHouse $house,
        InspectionArea $area,
        InspectionPhoto $photo,
        int $page = 1,
        int $perPage = 40
    ): string {
        $pageName = 'area_'.$area->id.'_page';

        return route('admin.houses.show', $house).'?'.http_build_query([
            $pageName => max(1, $page),
            'photos_per_page' => $perPage,
        ]).'#photo-'.$photo->id;
    }

    /**
     * تحويل رابط photos-panel الخاطئ إلى صفحة المنزل الكاملة.
     */
    public static function normalize(mixed $url, PropertyHouse $house, InspectionPhoto $photo): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        $parsed = parse_url($url);
        if ($parsed === false) {
            return null;
        }

        $path = $parsed['path'] ?? '';
        $housePath = '/admin/houses/'.$house->id;

        if ($path === $housePath) {
            return $url;
        }

        if (preg_match('#^'.preg_quote($housePath, '#').'/photos/(\d+)/edit$#', $path)) {
            return $url;
        }

        if (preg_match('#^'.preg_quote($housePath, '#').'/areas/(\d+)/photos-panel$#', $path, $m)) {
            parse_str($parsed['query'] ?? '', $query);
            $areaId = (int) $m[1];
            $pageName = 'area_'.$areaId.'_page';
            $page = max(1, (int) ($query[$pageName] ?? 1));
            $perPage = (int) ($query['photos_per_page'] ?? session('photos_per_page', 40));

            $area = new InspectionArea;
            $area->id = $areaId;

            return self::forHousePhoto($house, $area, $photo, $page, $perPage);
        }

        if (str_starts_with($path, $housePath.'/')) {
            return null;
        }

        return null;
    }
}
