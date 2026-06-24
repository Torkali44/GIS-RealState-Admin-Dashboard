<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PropertyHouse;
use App\Services\InspectionReportPdfGenerator;
use App\Support\HouseMediaCleanup;
use App\Support\InspectionReportCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ReportFinalizeController extends Controller
{
    public function finalize(PropertyHouse $house, InspectionReportPdfGenerator $generator): RedirectResponse
    {
        $house->load(['inspectionAreas.photos']);

        $filename = 'inspection-'.$house->id.'.pdf';
        $disk = Storage::disk('public');
        $finalPath = 'reports/'.$filename;

        if (! $disk->exists('reports')) {
            $disk->makeDirectory('reports');
        }

        $saved = false;

        if (InspectionReportCache::exists($house)) {
            try {
                $cachePath = InspectionReportCache::relativePath($house);
                if ($disk->exists($cachePath)) {
                    $disk->copy($cachePath, $finalPath);
                    $saved = $disk->exists($finalPath);
                }
            } catch (Throwable) {
                $saved = false;
            }
        }

        if (! $saved) {
            $tmp = storage_path('app/reports/tmp/finalize-'.$house->id.'-'.uniqid('', true).'.pdf');
            if (! is_dir(dirname($tmp))) {
                @mkdir(dirname($tmp), 0755, true);
            }

            $generator->renderToFile($house, $tmp);
            $stream = fopen($tmp, 'r');
            if ($stream !== false) {
                $disk->put($finalPath, $stream);
                fclose($stream);
            }
            @unlink($tmp);
        }

        $deletedPhotos = HouseMediaCleanup::purgeAllPhotosAndAreas($house);
        HouseMediaCleanup::purgeCachedReportsExceptFinal((int) $house->id, $finalPath);

        return back()->with(
            'status',
            'تم حفظ التقرير النهائي بنجاح وحذف '.$deletedPhotos.' صورة وجميع الأقسام من السيرفر وقاعدة البيانات.'
        );
    }
}
