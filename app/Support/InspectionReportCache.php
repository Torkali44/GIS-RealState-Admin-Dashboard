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
    public static function relativePath(PropertyHouse $house): string
    {
        try {
            $photoCount = (int) $house->photos()->count();
            $stamp = $house->photos()->max('updated_at')
                ?? $house->updated_at
                ?? $house->created_at;
            $unix = $stamp ? \Illuminate\Support\Carbon::parse($stamp)->getTimestamp() : 0;
        } catch (Throwable) {
            $photoCount = 0;
            $unix = 0;
        }

        return "reports/inspection-{$house->id}-pc{$photoCount}-u{$unix}.pdf";
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
