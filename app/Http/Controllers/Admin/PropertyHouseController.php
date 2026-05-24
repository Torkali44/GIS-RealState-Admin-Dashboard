<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NoteCategory;
use App\Models\PropertyHouse;
use App\Services\InspectionReportPdfGenerator;
use App\Services\InspectionReportWordGenerator;
use App\Services\DriveMediaService;
use App\Services\DriveReportSyncService;
use App\Services\GoogleDriveService;
use App\Support\InspectionReportCache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PropertyHouseController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->query('search');

        $houses = PropertyHouse::query()
            ->withCount(['inspectionAreas', 'photos'])
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('title', 'like', "%{$search}%")
                        ->orWhere('client_name', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%")
                        ->orWhere('reference_code', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.houses.index', compact('houses', 'search'));
    }

    public function create(): View
    {
        return view('admin.houses.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'reference_code' => ['nullable', 'string', 'max:100'],
            'inspection_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $house = PropertyHouse::create([
            ...$data,
            'user_id' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.houses.show', $house)
            ->with('status', 'تم إنشاء المنزل.');
    }

    public function show(PropertyHouse $house): View
    {
        // Only load areas (photos are paginated per-area in the view, no need to eager-load all).
        $house->load([
            'inspectionAreas' => function ($q) {
                $q->withCount('photos')
                  ->orderBy('sort_order')
                  ->orderBy('id');
            },
        ]);

        $noteCategories = NoteCategory::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->with(['templates' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')])
            ->get(['id', 'name', 'sort_order']);

        $sectionTemplates = \App\Models\SectionTemplate::ordered()->get(['id', 'name']);

        $reportV = InspectionReportCache::versionStamp($house);
        $driveConfigured = GoogleDriveService::isConfigured();
        $driveOAuthNeeded = GoogleDriveService::authMode() === 'oauth'
            && GoogleDriveService::oauthClientConfigured()
            && ! GoogleDriveService::isOAuthConnected();

        $pendingDrivePhotos = 0;
        if ($driveConfigured) {
            $house->loadMissing('inspectionAreas.photos');
            foreach ($house->inspectionAreas as $area) {
                foreach ($area->photos as $photo) {
                    if (DriveMediaService::needsDriveUpload($photo)) {
                        $pendingDrivePhotos++;
                    }
                }
            }
        }

        return view('admin.houses.show', compact(
            'house',
            'noteCategories',
            'sectionTemplates',
            'reportV',
            'driveConfigured',
            'driveOAuthNeeded',
            'pendingDrivePhotos'
        ));
    }

    public function edit(PropertyHouse $house): View
    {
        return view('admin.houses.edit', compact('house'));
    }

    public function update(Request $request, PropertyHouse $house): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'reference_code' => ['nullable', 'string', 'max:100'],
            'inspection_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $house->update($data);
        InspectionReportCache::forget($house);
        DriveReportSyncService::scheduleSync($house);

        return redirect()
            ->route('admin.houses.show', $house)
            ->with('status', 'تم تحديث بيانات المنزل.');
    }

    public function destroy(PropertyHouse $house): RedirectResponse
    {
        $house->load(['inspectionAreas.photos']);

        foreach ($house->inspectionAreas as $area) {
            foreach ($area->photos as $photo) {
                $photo->delete();
            }
            $area->delete();
        }

        $house->delete();

        return redirect()
            ->route('admin.houses.index')
            ->with('status', 'تم حذف المنزل وجميع المرفقات.');
    }

    public function report(
        Request $request,
        PropertyHouse $house,
        InspectionReportPdfGenerator $generator,
        InspectionReportWordGenerator $wordGenerator
    ): Response|RedirectResponse {
        if ($request->query('format') === 'word') {
            return $this->reportWord($house, $wordGenerator);
        }

        $filename = 'inspection-'.$house->id.'.pdf';
        $disk = Storage::disk('public');

        @set_time_limit(0);
        @ignore_user_abort(true);
        @ini_set('max_execution_time', '1200');
        @ini_set('memory_limit', '1024M');

        $photoCount = $house->photos()->count();

        // ── Cache key يعتمد على updated_at للمنزل والصور ──
        // أي تعديل على صورة أو قسم يغير updated_at فيتغير الـ cache key تلقائياً
        $cachePath = InspectionReportCache::relativePath($house);
        $useCache = ! $request->boolean('refresh') && InspectionReportCache::exists($house);

        // ── لو مفيش cache: اعرض صفحة الانتظار أولاً ثم أعد التوجيه ──
        if (! $useCache && ! $request->boolean('_generating')) {
            $redirectUrl = $request->fullUrlWithQuery(['_generating' => '1']);
            $photoText = $photoCount > 0 ? " ({$photoCount} صورة)" : '';
            return response('<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
<meta charset="UTF-8">
<meta http-equiv="refresh" content="3;url=' . $redirectUrl . '">
<title>جاري إنشاء التقرير...</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Tahoma, Arial, sans-serif; background: #f0f9ff; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
  .card { background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.10); padding: 48px 40px; text-align: center; max-width: 420px; width: 90%; }
  .spinner { width: 56px; height: 56px; border: 5px solid #e0f2fe; border-top-color: #0284c7; border-radius: 50%; animation: spin 0.9s linear infinite; margin: 0 auto 24px; }
  @keyframes spin { to { transform: rotate(360deg); } }
  h2 { color: #0c4a6e; font-size: 20px; margin-bottom: 10px; }
  p { color: #475569; font-size: 14px; line-height: 1.7; }
  .badge { display: inline-block; background: #e0f2fe; color: #0284c7; border-radius: 20px; padding: 4px 14px; font-size: 13px; margin-top: 14px; }
</style>
</head>
<body>
<div class="card">
  <div class="spinner"></div>
  <h2>جاري إنشاء التقرير' . $photoText . '...</h2>
  <p>يتم الآن معالجة الصور وتوليد ملف PDF بجودة عالية.<br>سيبدأ التحميل تلقائياً خلال لحظات.</p>
  <div class="badge">⏳ يرجى الانتظار</div>
</div>
</body>
</html>');
        }

        try {
            if (! $disk->exists('reports')) {
                $disk->makeDirectory('reports');
            }

            if ($useCache) {
                $absolutePath = $disk->path($cachePath);
            } else {
                // امسح الـ cache القديم للمنزل ده
                InspectionReportCache::forget($house);

                $tmpDir = storage_path('app/reports/tmp');
                if (! is_dir($tmpDir)) {
                    @mkdir($tmpDir, 0755, true);
                }
                $absolutePath = $tmpDir.DIRECTORY_SEPARATOR.'download-'.$house->id.'-'.uniqid('', true).'.pdf';
                $generator->renderToFile($house, $absolutePath);

                if (is_readable($absolutePath)) {
                    $stream = fopen($absolutePath, 'r');
                    if ($stream !== false) {
                        $disk->put($cachePath, $stream);
                        fclose($stream);
                    }
                }
            }

            if (! is_readable($absolutePath)) {
                throw new \RuntimeException('ملف PDF غير موجود بعد التوليد.');
            }

            if (DriveMediaService::enabled()) {
                try {
                    GoogleDriveService::uploadPdfReport($house, $absolutePath);
                } catch (Throwable $driveEx) {
                    Log::warning('Drive PDF upload failed', ['house_id' => $house->id, 'error' => $driveEx->getMessage()]);
                }
            }

            $disposition = $request->query('inline') ? 'inline' : 'attachment';

            return response()->file($absolutePath, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ])->deleteFileAfterSend(str_contains($absolutePath, storage_path('app'.DIRECTORY_SEPARATOR.'reports'.DIRECTORY_SEPARATOR.'tmp')));
        } catch (Throwable $e) {
            Log::error('PDF report generation failed', [
                'house_id' => $house->id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'photos' => $photoCount,
            ]);

            return redirect()
                ->route('admin.houses.show', $house)
                ->with('error', 'تعذر إنشاء التقرير ('.$photoCount.' صورة). جرّب مرة أخرى بعد دقائق.');
        }
    }

    public function reportWord(PropertyHouse $house, InspectionReportWordGenerator $generator): Response
    {
        $filename = 'inspection-notes-'.$house->id.'.doc';
        $html = $generator->renderHtml($house);

        if (DriveMediaService::enabled()) {
            try {
                GoogleDriveService::uploadWordReport($house, $html);
            } catch (Throwable $e) {
                Log::warning('Drive Word upload failed', ['house_id' => $house->id, 'error' => $e->getMessage()]);
            }
        }

        return response($html, 200, [
            'Content-Type' => 'application/msword; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}