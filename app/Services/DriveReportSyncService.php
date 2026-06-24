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
        if (! self::enabled() || ! config('google-drive.sync_reports_on_change', false)) {
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
        $disk = Storage::disk('public');

        if (! $disk->exists('reports')) {
            $disk->makeDirectory('reports');
        }

        $cachePath = InspectionReportCache::relativePath($house);
        $absolutePath = null;
        $generated = false;

        if (InspectionReportCache::exists($house)) {
            $absolutePath = $disk->path($cachePath);
        }

        if ($absolutePath === null || ! is_readable($absolutePath)) {
            $tmpDir = storage_path('app/reports/tmp');
            if (! is_dir($tmpDir)) {
                mkdir($tmpDir, 0755, true);
            }

            $absolutePath = $tmpDir.DIRECTORY_SEPARATOR.'drive-sync-'.$house->id.'-'.uniqid('', true).'.pdf';
            $generated = true;

            try {
                app(InspectionReportPdfGenerator::class)->renderToFile($house, $absolutePath);

                if (! is_readable($absolutePath)) {
                    return;
                }

                $stream = fopen($absolutePath, 'rb');
                if ($stream !== false) {
                    $disk->put($cachePath, $stream);
                    fclose($stream);
                }
            } finally {
                if ($generated && is_file($absolutePath)) {
                    @unlink($absolutePath);
                }
            }

            $absolutePath = $disk->path($cachePath);
        }

        if (! is_readable($absolutePath)) {
            return;
        }

        GoogleDriveService::uploadPdfReport($house, $absolutePath);
    }

    public static function syncWord(PropertyHouse $house): void
    {
        $house = $house->fresh() ?? $house;
        $html = app(InspectionReportWordGenerator::class)->renderHtml($house);
        GoogleDriveService::uploadWordReport($house, $html);
    }
}
