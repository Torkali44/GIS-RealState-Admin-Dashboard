<?php

namespace App\Support;

use App\Models\InspectionPhoto;
use App\Models\PropertyHouse;
use App\Services\DriveMediaService;
use App\Services\GoogleDriveService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * حذف شامل لملفات الصور على السيرفر وقاعدة البيانات.
 */
final class HouseMediaCleanup
{
    public static function purgePhotoFiles(InspectionPhoto $photo, int $houseId): void
    {
        if (DriveMediaService::enabled()) {
            DriveMediaService::deleteDriveAssets($photo);
        }

        DriveMediaService::invalidatePersistentCache($photo, false);
        DriveMediaService::invalidatePersistentCache($photo, true);

        $disk = Storage::disk('public');
        $paths = array_unique(array_filter([
            $photo->original_path,
            $photo->composite_path,
            $photo->local_cached_path,
            $photo->processed_cache_path,
        ]));

        foreach ($paths as $path) {
            if ($path && $disk->exists($path)) {
                $disk->delete($path);
            }
            self::deleteMirrorFile($houseId, $path);
        }

        foreach ($paths as $path) {
            if ($path) {
                self::deleteMirrorFile($houseId, basename($path));
            }
        }
    }

    /**
     * حذف كل صور وأقسام المنزل من DB والسيرفر (يبقى سجل المنزل).
     */
    public static function purgeAllPhotosAndAreas(PropertyHouse $house): int
    {
        $house->load(['inspectionAreas.photos']);
        $deletedPhotos = 0;

        foreach ($house->inspectionAreas as $area) {
            foreach ($area->photos as $photo) {
                self::purgePhotoFiles($photo, (int) $house->id);
                $photo->deleteQuietly();
                $deletedPhotos++;
            }
            $area->deleteQuietly();
        }

        self::removeHouseInspectionDirectories((int) $house->id);
        self::purgeHouseEmbedCache((int) $house->id);
        InspectionReportCache::forget($house);

        return $deletedPhotos;
    }

    /**
     * حذف كل ملفات المنزل بما فيها التقارير (لزر حذف المنزل).
     */
    public static function purgeEntireHouse(PropertyHouse $house): void
    {
        self::purgeAllPhotosAndAreas($house);
        self::purgeAllHouseReports((int) $house->id);

        if (DriveMediaService::enabled()) {
            try {
                GoogleDriveService::deleteHouseFolder($house);
            } catch (Throwable) {
                // ignore
            }
        }
    }

    /**
     * الاحتفاظ بتقرير نهائي واحد فقط: reports/inspection-{id}.pdf
     */
    public static function purgeCachedReportsExceptFinal(int $houseId, string $keepRelativePath): void
    {
        try {
            $disk = Storage::disk('public');
            if (! $disk->exists('reports')) {
                return;
            }
            $keep = ltrim(str_replace('\\', '/', $keepRelativePath), '/');
            foreach ($disk->files('reports') as $file) {
                $base = basename($file);
                if (! str_starts_with($base, 'inspection-'.$houseId)) {
                    continue;
                }
                if ($file === $keep || $base === basename($keep)) {
                    continue;
                }
                $disk->delete($file);
            }
        } catch (Throwable) {
            // ignore
        }
    }

    public static function purgeAllHouseReports(int $houseId): void
    {
        try {
            $disk = Storage::disk('public');
            if (! $disk->exists('reports')) {
                return;
            }
            foreach ($disk->files('reports') as $file) {
                if (str_starts_with(basename($file), 'inspection-'.$houseId)) {
                    $disk->delete($file);
                }
            }
        } catch (Throwable) {
            // ignore
        }
    }

    public static function removeHouseInspectionDirectories(int $houseId): void
    {
        try {
            Storage::disk('public')->deleteDirectory('inspections/'.$houseId);
        } catch (Throwable) {
            // ignore
        }

        foreach ([
            public_path('storage/inspections/'.$houseId),
            dirname(base_path(), 2).'/storage/inspections/'.$houseId,
        ] as $dir) {
            if (is_dir($dir)) {
                File::deleteDirectory($dir);
            }
        }
    }

    public static function purgeHouseEmbedCache(int $houseId): void
    {
        unset($houseId);

        $tmp = storage_path('app/reports/tmp');
        if (! is_dir($tmp)) {
            return;
        }

        foreach (glob($tmp.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    private static function deleteMirrorFile(int $houseId, ?string $pathOrBasename): void
    {
        if ($pathOrBasename === null || $pathOrBasename === '') {
            return;
        }

        $basename = str_contains($pathOrBasename, '/') ? basename($pathOrBasename) : $pathOrBasename;
        foreach ([
            public_path('storage/inspections/'.$houseId.'/'.$basename),
            dirname(base_path(), 2).'/storage/inspections/'.$houseId.'/'.$basename,
        ] as $mirror) {
            if (is_file($mirror)) {
                @unlink($mirror);
            }
        }
    }
}
