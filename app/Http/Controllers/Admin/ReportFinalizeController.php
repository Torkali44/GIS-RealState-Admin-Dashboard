<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ReportFinalizeController extends Controller
{
    public function finalize(\App\Models\PropertyHouse $house, \App\Services\InspectionReportPdfGenerator $generator)
    {
        $filename = 'inspection-'.$house->id.'.pdf';
        $disk = \Illuminate\Support\Facades\Storage::disk('public');
        $path = "reports/{$filename}";

        $tmp = storage_path('app/reports/tmp/finalize-'.$house->id.'-'.uniqid('', true).'.pdf');
        if (! is_dir(dirname($tmp))) {
            @mkdir(dirname($tmp), 0755, true);
        }
        $generator->renderToFile($house, $tmp);
        $stream = fopen($tmp, 'r');
        $disk->put($path, $stream);
        fclose($stream);
        @unlink($tmp);

        // Delete all areas and their photos to free up space
        foreach ($house->inspectionAreas as $area) {
            foreach ($area->photos as $photo) {
                $photo->delete(); // This triggers deleting stored files
            }
            $area->delete();
        }

        return back()->with('status', 'تم حفظ التقرير النهائي بنجاح وحذف كافة الصور المرفقة لتوفير المساحة.');
    }
}
