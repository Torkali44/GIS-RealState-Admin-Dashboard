<?php

namespace App\Services;

use App\Models\PropertyHouse;
use App\Support\InspectionReportCache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * مزامنة تقرير PDF والتقرير الكتابي (.doc) على Drive بعد أي تغيير على صور/ملاحظات المنزل.
 */
class DriveReportSyncService
{
    public static function enabled(): bool
    {
        return DriveMediaService::enabled();
    }

    /**
     * تشغيل المزامنة بعد إرسال الاستجابة (لا تبطئ رفع الصور في الواجهة).
     */
    public static function scheduleSync(PropertyHouse $house): void
    {
        if (! self::enabled()) {
            return;
        }

        $houseId = (int) $house->id;

        dispatch(function () use ($houseId): void {
            $house = PropertyHouse::find($houseId);
            if ($house) {
                self::syncNow($house);
            }
        })->afterResponse();
    }

    public static function syncNow(PropertyHouse $house): void
    {
        if (! self::enabled()) {
            return;
        }

        try {
            self::syncPdf($house);
        } catch (Throwable $e) {
            Log::warning('Drive PDF report sync failed', [
                'house_id' => $house->id,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            self::syncWord($house);
        } catch (Throwable $e) {
            Log::warning('Drive Word report sync failed', [
                'house_id' => $house->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public static function syncPdf(PropertyHouse $house): void
    {
        $house = $house->fresh() ?? $house;
        InspectionReportCache::forget($house);

        $tmpDir = storage_path('app/reports/tmp');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $absolutePath = $tmpDir.DIRECTORY_SEPARATOR.'drive-sync-'.$house->id.'-'.uniqid('', true).'.pdf';

        try {
            app(InspectionReportPdfGenerator::class)->renderToFile($house, $absolutePath);

            if (! is_readable($absolutePath)) {
                return;
            }

            GoogleDriveService::uploadPdfReport($house->fresh(), $absolutePath);

            $cachePath = InspectionReportCache::relativePath($house->fresh());
            $disk = Storage::disk('public');
            if (! $disk->exists('reports')) {
                $disk->makeDirectory('reports');
            }
            $disk->put($cachePath, file_get_contents($absolutePath) ?: '');
        } finally {
            if (is_file($absolutePath)) {
                @unlink($absolutePath);
            }
        }
    }

    public static function syncWord(PropertyHouse $house): void
    {
        $house = $house->fresh() ?? $house;
        $html = app(InspectionReportWordGenerator::class)->renderHtml($house);
        GoogleDriveService::uploadWordReport($house, $html);
    }
}
