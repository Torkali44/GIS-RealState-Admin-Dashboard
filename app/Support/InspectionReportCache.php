<?php

namespace App\Support;

use App\Models\PropertyHouse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * مسار تخزين PDF المخزّن — يتغيّر عند إضافة/تعديل صور أو ترتيبها.
 */
final class InspectionReportCache
{
    private const STAMP_TTL_SECONDS = 3600;

    public static function versionStamp(PropertyHouse $house): string
    {
        try {
            $houseId = (int) $house->id;

            return Cache::remember(
                self::stampCacheKey($houseId),
                self::STAMP_TTL_SECONDS,
                fn (): string => self::buildVersionStamp($house)
            );
        } catch (Throwable) {
            return 'pc0-p0-h0-a0-o0';
        }
    }

    private static function buildVersionStamp(PropertyHouse $house): string
    {
        $house = $house->fresh() ?? $house;

        $photoStats = DB::table('inspection_photos as p')
            ->join('inspection_areas as a', 'a.id', '=', 'p.inspection_area_id')
            ->where('a.property_house_id', $house->id)
            ->selectRaw('COUNT(p.id) as cnt, MAX(p.updated_at) as max_u')
            ->first();

        $photoCount = (int) ($photoStats->cnt ?? 0);
        $photoUnix = ! empty($photoStats->max_u)
            ? \Illuminate\Support\Carbon::parse($photoStats->max_u)->getTimestamp()
            : 0;

        $houseUnix = $house->updated_at
            ? \Illuminate\Support\Carbon::parse($house->updated_at)->getTimestamp()
            : 0;

        $areaUnix = (int) DB::table('inspection_areas')
            ->where('property_house_id', $house->id)
            ->max(DB::raw('UNIX_TIMESTAMP(updated_at)'));

        $orderHash = self::orderFingerprint($house);
        $logoHash = self::logoFingerprint();

        return "pc{$photoCount}-p{$photoUnix}-h{$houseUnix}-a{$areaUnix}-o{$orderHash}-l{$logoHash}";
    }

    /**
     * تغيير ملف اللوجو أو تاريخ تعديله يبطل كاش PDF تلقائياً.
     */
    public static function logoFingerprint(): string
    {
        $relative = (string) config('report.logo_path', 'images/newLog.jpeg');
        $absolute = public_path($relative);
        if (! is_file($absolute)) {
            $absolute = public_path('images/newLog.jpeg');
        }
        if (! is_file($absolute)) {
            return '0';
        }

        return substr(hash('sha256', $absolute.'|'.(string) filemtime($absolute).'|'.(string) filesize($absolute)), 0, 10);
    }

    public static function orderFingerprint(PropertyHouse $house): string
    {
        try {
            $houseId = (int) $house->id;

            return Cache::remember(
                self::orderCacheKey($houseId),
                self::STAMP_TTL_SECONDS,
                function () use ($houseId): string {
                    $driver = DB::connection()->getDriverName();

                    if ($driver === 'mysql') {
                        $row = DB::selectOne(
                            'SELECT SUBSTRING(SHA2(GROUP_CONCAT(
                                CONCAT(a.id, ":", a.sort_order, ":", COALESCE(p.id, 0), ":", COALESCE(p.sort_order, 0))
                                ORDER BY a.sort_order, a.id, p.sort_order, p.id
                                SEPARATOR "|"
                            ), 256), 1, 10) AS h
                            FROM inspection_areas a
                            LEFT JOIN inspection_photos p ON p.inspection_area_id = a.id
                            WHERE a.property_house_id = ?',
                            [$houseId]
                        );

                        return $row->h ?? '0';
                    }

                    return self::orderFingerprintPortable($houseId);
                }
            );
        } catch (Throwable) {
            return '0';
        }
    }

    public static function relativePath(PropertyHouse $house): string
    {
        return 'reports/inspection-'.$house->id.'-'.self::versionStamp($house).'.pdf';
    }

    public static function absolutePath(PropertyHouse $house): string
    {
        return Storage::disk('public')->path(self::relativePath($house));
    }

    public static function exists(PropertyHouse $house): bool
    {
        try {
            return Storage::disk('public')->exists(self::relativePath($house));
        } catch (Throwable) {
            return false;
        }
    }

    public static function forget(PropertyHouse $house): void
    {
        $houseId = (int) $house->id;
        Cache::forget(self::stampCacheKey($houseId));
        Cache::forget(self::orderCacheKey($houseId));

        try {
            $disk = Storage::disk('public');
            if (! $disk->exists('reports')) {
                return;
            }
            foreach ($disk->files('reports') as $file) {
                if (str_starts_with(basename($file), 'inspection-'.$houseId.'-')) {
                    $disk->delete($file);
                }
            }
        } catch (Throwable) {
            // تجاهل
        }
    }

    private static function stampCacheKey(int $houseId): string
    {
        return 'inspection-report-stamp:'.$houseId;
    }

    private static function orderCacheKey(int $houseId): string
    {
        return 'inspection-report-order:'.$houseId;
    }

    private static function orderFingerprintPortable(int $houseId): string
    {
        $rows = DB::table('inspection_areas as a')
            ->leftJoin('inspection_photos as p', 'p.inspection_area_id', '=', 'a.id')
            ->where('a.property_house_id', $houseId)
            ->orderBy('a.sort_order')
            ->orderBy('a.id')
            ->orderBy('p.sort_order')
            ->orderBy('p.id')
            ->select([
                'a.id as area_id',
                'a.sort_order as area_sort',
                'p.id as photo_id',
                'p.sort_order as photo_sort',
            ])
            ->get();

        $parts = [];
        foreach ($rows as $row) {
            $parts[] = $row->area_id.':'.$row->area_sort.':'.($row->photo_id ?? '0').':'.($row->photo_sort ?? '0');
        }

        return substr(hash('sha256', implode('|', $parts)), 0, 10);
    }
}
