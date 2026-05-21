<?php

namespace App\Support;

use App\Models\PropertyHouse;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * مسار تخزين PDF المخزّن — يتغيّر عند إضافة/تعديل صور.
 */
final class InspectionReportCache
{
    /**
     * بصمة التقرير — تتغير عند أي صورة/منزل/قسم يتعدّل.
     */
    public static function versionStamp(PropertyHouse $house): string
    {
        try {
            $house = $house->fresh() ?? $house;
            $photoCount = (int) $house->photos()->count();
            $photoMax = $house->photos()->max('updated_at');
            $photoUnix = $photoMax
                ? \Illuminate\Support\Carbon::parse($photoMax)->getTimestamp()
                : 0;
            $houseUnix = $house->updated_at
                ? \Illuminate\Support\Carbon::parse($house->updated_at)->getTimestamp()
                : 0;
            $areaMax = $house->inspectionAreas()->max('updated_at');
            $areaUnix = $areaMax
                ? \Illuminate\Support\Carbon::parse($areaMax)->getTimestamp()
                : 0;

            return "pc{$photoCount}-p{$photoUnix}-h{$houseUnix}-a{$areaUnix}";
        } catch (Throwable) {
            return 'pc0-p0-h0-a0';
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
        try {
            $disk = Storage::disk('public');
            if (! $disk->exists('reports')) {
                return;
            }
            foreach ($disk->files('reports') as $file) {
                if (str_starts_with(basename($file), 'inspection-'.$house->id.'-')) {
                    $disk->delete($file);
                }
            }
        } catch (Throwable) {
            // تجاهل
        }
    }
}
