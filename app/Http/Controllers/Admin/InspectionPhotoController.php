<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InspectionArea;
use App\Models\InspectionPhoto;
use App\Models\NoteCategory;
use App\Models\PropertyHouse;
use App\Services\DriveMediaService;
use App\Services\DriveReportSyncService;
use App\Services\GoogleDriveService;
use Illuminate\Support\Facades\Log;
use Throwable;
use App\Support\RasterImageForTcpdf;
use App\Support\InspectionReportCache;
use App\Support\PhotoImageUrl;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class InspectionPhotoController extends Controller
{
    public function store(Request $request, PropertyHouse $house, InspectionArea $area): RedirectResponse|JsonResponse
    {
        $this->assertAreaBelongs($house, $area);

        // Increase limits for large uploads
        @ini_set('max_execution_time', '600'); // 10 minutes
        @ini_set('memory_limit', '512M');

        $request->validate([
            'photos' => ['required', 'array', 'max:1000'],
            'photos.*' => ['file', 'image', 'max:20480'], // 20MB per photo
            'upload_batch_id' => ['nullable', 'string', 'max:100'],
            'upload_file_keys' => ['nullable', 'array'],
            'upload_file_keys.*' => ['nullable', 'string', 'max:500'],
        ]);

        $maxSort = (int) $area->photos()->max('sort_order');
        $disk = Storage::disk('public');
        $created = 0;
        $skipped = 0;
        $driveFailed = 0;
        $batchId = $request->input('upload_batch_id');
        $fileKeys = $request->input('upload_file_keys', []);

        $uploadedFiles = $request->file('photos', []);
        $pairedFiles = [];
        foreach ($uploadedFiles as $index => $file) {
            if ($file) {
                $pairedFiles[] = [
                    'index' => $index,
                    'file' => $file,
                    'filename' => $file->getClientOriginalName(),
                ];
            }
        }

        // Natural sort by filename before processing so initial sort_order matches natural filename sorting
        usort($pairedFiles, function ($a, $b) {
            return strnatcasecmp($a['filename'], $b['filename']);
        });

        foreach ($pairedFiles as $pair) {
            $index = $pair['index'];
            $file = $pair['file'];
            if (!$file) {
                continue;
            }

            $fileKey = $fileKeys[$index] ?? null;
            if (
                $batchId && $fileKey && $area->photos()
                    ->where('upload_batch_id', $batchId)
                    ->where('upload_file_key', $fileKey)
                    ->exists()
            ) {
                $skipped++;
                continue;
            }

            // Save file
            $path = $file->store("inspections/{$house->id}", 'public');

            // Skip extra conversion for JPEGs to speed up large batch uploads.
            if (!in_array(strtolower((string) $file->getClientOriginalExtension()), ['jpg', 'jpeg'], true)) {
                $converted = RasterImageForTcpdf::convertStoredFileToJpegIfNeeded($disk, $path);
                if ($converted !== null) {
                    $path = $converted;
                }
            }

            $this->copyUploadedToPublicStorage($path, $house);

            $maxSort++;
            try {
                $photo = InspectionPhoto::create([
                    'inspection_area_id' => $area->id,
                    'original_path' => $path,
                    'original_filename' => $file->getClientOriginalName(),
                    'sort_order' => $maxSort,
                    'upload_batch_id' => $batchId,
                    'upload_file_key' => $fileKey,
                ]);
                if (DriveMediaService::enabled() && ! DriveMediaService::pushPhoto($photo)) {
                    $driveFailed++;
                }
                $created++;
            } catch (QueryException $e) {
                if ((string) $e->getCode() !== '23000') {
                    throw $e;
                }
                if ($disk->exists($path)) {
                    $disk->delete($path);
                }
                $skipped++;
            }
        }

        InspectionReportCache::forget($house);
        if ($created > 0) {
            $house->touch();
            DriveReportSyncService::scheduleSync($house);
        }

        $message = 'تم رفع '.$created.' صور بنجاح.';
        if ($driveFailed > 0) {
            $message .= ' لكن فشل رفع '.$driveFailed.' صورة إلى Google Drive (الصور محفوظة على السيرفر).';
            if (DriveMediaService::lastError()) {
                $message .= ' السبب: '.DriveMediaService::lastError();
            }
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'count' => $created,
                'skipped' => $skipped,
                'drive_failed' => $driveFailed,
                'drive_synced' => $driveFailed === 0,
            ]);
        }

        return back()->with($driveFailed > 0 ? 'error' : 'status', $message);
    }

    public function storeMerged(Request $request, PropertyHouse $house, InspectionArea $area): RedirectResponse
    {
        $this->assertAreaBelongs($house, $area);

        $data = $request->validate([
            'merged_image' => ['required', 'file', 'image', 'max:51200'],
            'description' => ['nullable', 'string', 'max:8000'],
        ]);

        $disk = Storage::disk('public');
        $path = $request->file('merged_image')->store("inspections/{$house->id}", 'public');
        $converted = RasterImageForTcpdf::convertStoredFileToJpegIfNeeded($disk, $path);
        if ($converted !== null) {
            $path = $converted;
        }

        $this->copyUploadedToPublicStorage($path, $house);

        $maxSort = (int) $area->photos()->max('sort_order');
        $photo = InspectionPhoto::create([
            'inspection_area_id' => $area->id,
            'original_path' => $path,
            'composite_path' => $path,
            'original_filename' => 'merged-' . now()->format('Ymd-His') . '.jpg',
            'description' => $data['description'] ?? null,
            'sort_order' => $maxSort + 1,
        ]);

        if (DriveMediaService::enabled()) {
            DriveMediaService::pushPhoto($photo);
        }

        InspectionReportCache::forget($house);
        $house->touch();
        DriveReportSyncService::scheduleSync($house);

        return back()->with('status', 'تم دمج الصور وحفظها كصورة جديدة.');
    }

    public function edit(PropertyHouse $house, InspectionPhoto $photo): View
    {
        $this->assertPhotoBelongs($house, $photo);
        $photo->load('inspectionArea');

        $prevPhoto = null;
        $nextPhoto = null;
        if ($photo->inspectionArea) {
            $ids = $photo->inspectionArea->photos()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->pluck('id')
                ->all();
            $ix = array_search((int) $photo->id, $ids, true);
            if ($ix !== false) {
                if ($ix > 0) {
                    $prevPhoto = InspectionPhoto::query()->find($ids[$ix - 1]);
                }
                if ($ix < count($ids) - 1) {
                    $nextPhoto = InspectionPhoto::query()->find($ids[$ix + 1]);
                }
            }
        }

        $allCategories = NoteCategory::query()
            ->with(['templates' => function ($q) {
                $q->orderBy('sort_order')->orderBy('id');
            }])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $existingNotes = $this->photoNotesForPanel($photo);

        return view('admin.photos.edit', compact('house', 'photo', 'prevPhoto', 'nextPhoto', 'allCategories', 'existingNotes'));
    }

    /**
     * @return array<int, array{text:string,category_id:int|null}>
     */
    private function photoNotesForPanel(InspectionPhoto $photo): array
    {
        if (method_exists($photo, 'notesEntries')) {
            return $photo->notesEntries();
        }

        $raw = $photo->notes_json;
        if (! is_array($raw)) {
            return [];
        }

        $clean = [];
        foreach ($raw as $note) {
            if (is_string($note)) {
                $text = trim($note);
                if ($text !== '') {
                    $clean[] = ['text' => $text, 'category_id' => null];
                }
                continue;
            }
            if (! is_array($note)) {
                continue;
            }
            $text = trim((string) ($note['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $cat = $note['category_id'] ?? null;
            $clean[] = [
                'text' => $text,
                'category_id' => (is_numeric($cat) && (int) $cat > 0) ? (int) $cat : null,
            ];
        }

        return $clean;
    }

    /**
     * عرض الصورة عبر التطبيق (لا يعتمد على symlink أو تطابق APP_URL مع عنوان المتصفح).
     */

    public function image(Request $request, PropertyHouse $house, InspectionPhoto $photo): BinaryFileResponse|Response
    {
        @ini_set('max_execution_time', '120');
        @ini_set('memory_limit', '256M');

        if (! PhotoImageUrl::allows($request, $house, $photo)) {
            abort(403, 'رابط الصورة غير صالح — حدّث الصفحة.');
        }

        $this->assertPhotoBelongs($house, $photo);

        $useComposite = $request->boolean('c') && ($photo->composite_path || $photo->drive_composite_file_id);

        DriveMediaService::ensureStorageDirectories();

        $absolute = DriveMediaService::resolveImageForDisplay($photo, $useComposite);

        if (! $absolute && $photo->drive_file_id) {
            DriveMediaService::buildPersistentCache($photo, $useComposite);
            $absolute = DriveMediaService::resolveImageForDisplay($photo->fresh(), $useComposite);
        }

        if (! $absolute && DriveMediaService::enabled() && DriveMediaService::hasLocalCopy($photo)) {
            DriveMediaService::pushPhoto($photo);
            $absolute = DriveMediaService::resolveImageForDisplay($photo->fresh(), $useComposite);
        }

        if ($absolute) {
            return $this->binaryImageFileResponse($absolute);
        }

        Log::error('Photo image 404', [
            'photo_id' => $photo->id,
            'house_id' => $house->id,
            'drive_file_id' => $photo->drive_file_id,
            'local_cached_path' => $photo->local_cached_path,
            'drive_error' => DriveMediaService::lastError(),
        ]);

        abort(404, 'تعذر تحميل الصورة. من صفحة المنزل اضغط «إصلاح عرض الصور (كاش محلي)».');
    }

    private function binaryImageFileResponse(string $absolute): BinaryFileResponse|Response
    {
        if (! is_readable($absolute)) {
            abort(404, 'ملف الصورة غير قابل للقراءة على السيرفر.');
        }

        $mime = mime_content_type($absolute) ?: 'image/jpeg';

        try {
            return response()->file($absolute, [
                'Content-Type' => $mime,
                'Cache-Control' => 'private, max-age=300',
            ]);
        } catch (Throwable) {
            $bytes = file_get_contents($absolute);
            if ($bytes === false || $bytes === '') {
                abort(404, 'تعذر قراءة ملف الصورة.');
            }

            return response($bytes, 200, [
                'Content-Type' => $mime,
                'Content-Length' => (string) strlen($bytes),
                'Cache-Control' => 'private, max-age=300',
            ]);
        }
    }


    public function update(Request $request, PropertyHouse $house, InspectionPhoto $photo): RedirectResponse
    {
        $this->assertPhotoBelongs($house, $photo);

        $request->merge([
            'tip_x' => $request->input('tip_x') === '' || $request->input('tip_x') === null ? null : $request->input('tip_x'),
            'tip_y' => $request->input('tip_y') === '' || $request->input('tip_y') === null ? null : $request->input('tip_y'),
        ]);

        $data = $request->validate([
            'description' => ['nullable', 'string', 'max:8000'],
            'tip_x' => ['nullable', 'numeric', 'between:0,1'],
            'tip_y' => ['nullable', 'numeric', 'between:0,1'],
            'annotations_json' => ['nullable', 'json'],
            'notes' => ['nullable', 'array'],
            'composite' => ['nullable', 'file', 'image', 'max:25600'],
            'clear_arrow' => ['sometimes', 'boolean'],
        ]);

        $notes = $this->normalizeIncomingNotes($request->input('notes', []));

        if ($request->boolean('clear_arrow')) {
            if ($photo->composite_path && Storage::disk('public')->exists($photo->composite_path)) {
                Storage::disk('public')->delete($photo->composite_path);
            }
            $photo->composite_path = null;
            $photo->tip_x = null;
            $photo->tip_y = null;
            $photo->annotations_json = null;
        }

        if ($request->hasFile('composite')) {
            $disk = Storage::disk('public');
            if ($photo->composite_path && $disk->exists($photo->composite_path)) {
                $disk->delete($photo->composite_path);
            }
            $rel = $request->file('composite')->store("inspections/{$house->id}", 'public');
            $converted = RasterImageForTcpdf::convertStoredFileToJpegIfNeeded($disk, $rel);
            $photo->composite_path = $converted ?? $rel;
            $this->copyUploadedToPublicStorage($photo->composite_path, $house);
        }

        $photo->description = $data['description'] ?? null;
        $photo->notes_json = empty($notes) ? null : $notes;
        if (!$request->boolean('clear_arrow')) {
            if (array_key_exists('tip_x', $data)) {
                $photo->tip_x = $data['tip_x'];
            }
            if (array_key_exists('tip_y', $data)) {
                $photo->tip_y = $data['tip_y'];
            }
            if (array_key_exists('annotations_json', $data)) {
                $photo->annotations_json = json_decode($data['annotations_json'], true);
            }
        }

        $photo->save();

        if (DriveMediaService::enabled()) {
            DriveMediaService::pushPhoto($photo->fresh());
        }

        InspectionReportCache::forget($house);
        $house->touch();
        DriveReportSyncService::scheduleSync($house);

        if ($request->filled('redirect_to')) {
            return redirect($request->input('redirect_to'))->with('status', 'تم حفظ التعديلات على الصورة.');
        }

        return redirect()->route('admin.houses.show', $house)->with('status', 'تم حفظ التعديلات على الصورة.');
    }

    public function destroy(PropertyHouse $house, InspectionPhoto $photo): RedirectResponse
    {
        $this->assertPhotoBelongs($house, $photo);
        $photo->delete();

        InspectionReportCache::forget($house);
        $house->touch();
        DriveReportSyncService::scheduleSync($house);

        return back()->with('status', 'تم حذف الصورة.');
    }

    /**
     * حفظ ملاحظات الصورة (مكتبة الملاحظات + ملاحظات يدوية) عبر AJAX من المودال.
     */
    public function updateNotes(Request $request, PropertyHouse $house, InspectionPhoto $photo): JsonResponse
    {
        $this->assertPhotoBelongs($house, $photo);

        $data = $request->validate([
            'notes' => ['nullable', 'array'],
        ]);

        $notes = $this->normalizeIncomingNotes($request->input('notes', []));

        $photo->notes_json = empty($notes) ? null : $notes;
        $photo->save();

        if (DriveMediaService::enabled()) {
            try {
                \App\Services\GoogleDriveService::syncPhotoNotesFile($photo->fresh());
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Drive notes sync failed', ['photo_id' => $photo->id, 'error' => $e->getMessage()]);
            }
        }

        InspectionReportCache::forget($house);
        $house->touch();
        DriveReportSyncService::scheduleSync($house);

        return response()->json([
            'success' => true,
            'notes' => $photo->notesEntries(),
            'count' => count($photo->notesList()),
            'message' => 'تم حفظ الملاحظات.',
        ]);
    }

    /**
     * @param  array<int, mixed>  $incoming
     * @return array<int, array{text:string,category_id:int|null}>
     */
    private function normalizeIncomingNotes(array $incoming): array
    {
        return collect($incoming)
            ->map(function ($item): ?array {
                $text = '';
                $cat = null;

                if (is_string($item)) {
                    $text = trim($item);
                } elseif (is_array($item)) {
                    $text = trim((string) ($item['text'] ?? ''));
                    $cat = $item['category_id'] ?? null;
                }
                if ($text === '') {
                    return null;
                }
                if (mb_strlen($text) > 2000) {
                    $text = mb_substr($text, 0, 2000);
                }
                $categoryId = (is_numeric($cat) && (int) $cat > 0) ? (int) $cat : null;

                return [
                    'text' => $text,
                    'category_id' => $categoryId,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function bulkDestroy(Request $request, PropertyHouse $house, InspectionArea $area): RedirectResponse
    {
        $this->assertAreaBelongs($house, $area);

        $request->validate([
            'photo_ids' => ['required', 'array'],
            'photo_ids.*' => ['integer', 'exists:inspection_photos,id'],
        ]);

        $photos = $area->photos()->whereIn('id', $request->photo_ids)->get();
        foreach ($photos as $photo) {
            $photo->delete();
        }

        InspectionReportCache::forget($house);
        $house->touch();
        DriveReportSyncService::scheduleSync($house);

        return back()->with('status', 'تم حذف الصور المحددة بنجاح.');
    }

    public function deduplicate(PropertyHouse $house, InspectionArea $area): RedirectResponse
    {
        $this->assertAreaBelongs($house, $area);

        $deleted = 0;
        $area->photos()
            ->whereNotNull('original_filename')
            ->get()
            ->groupBy('original_filename')
            ->each(function ($photos) use (&$deleted): void {
                if ($photos->count() < 2) {
                    return;
                }

                $keep = $photos->first(fn(InspectionPhoto $photo): bool => $this->photoHasWork($photo)) ?? $photos->first();
                $photos->reject(fn(InspectionPhoto $photo): bool => (int) $photo->id === (int) $keep->id)
                    ->filter(fn(InspectionPhoto $photo): bool => !$this->photoHasWork($photo))
                    ->each(function (InspectionPhoto $photo) use (&$deleted): void {
                        $photo->delete();
                        $deleted++;
                    });
            });

        InspectionReportCache::forget($house);
        $house->touch();
        DriveReportSyncService::scheduleSync($house);

        return back()->with('status', 'تم حذف ' . $deleted . ' صورة مكررة بدون أسهم أو وصف.');
    }

    public function move(Request $request, PropertyHouse $house, InspectionPhoto $photo): RedirectResponse
    {
        $this->assertPhotoBelongs($house, $photo);

        $data = $request->validate([
            'direction' => ['required', 'in:up,down'],
        ]);

        $query = InspectionPhoto::query()
            ->where('inspection_area_id', $photo->inspection_area_id)
            ->whereKeyNot($photo->id);

        $neighbor = $data['direction'] === 'up'
            ? $query->where('sort_order', '<', $photo->sort_order)->orderByDesc('sort_order')->first()
            : $query->where('sort_order', '>', $photo->sort_order)->orderBy('sort_order')->first();

        if (!$neighbor) {
            return back();
        }

        $currentOrder = (int) $photo->sort_order;
        $photo->update(['sort_order' => (int) $neighbor->sort_order]);
        $neighbor->update(['sort_order' => $currentOrder]);

        if ($request->filled('redirect_to')) {
            InspectionReportCache::forget($house);
            $house->touch();
            DriveReportSyncService::scheduleSync($house);

            return redirect($request->input('redirect_to'))->with('status', 'تم تحديث ترتيب الصور.');
        }

        InspectionReportCache::forget($house);
        $house->touch();
        DriveReportSyncService::scheduleSync($house);

        return back()->with('status', 'تم تحديث ترتيب الصور.');
    }

    private function assertAreaBelongs(PropertyHouse $house, InspectionArea $area): void
    {
        if ((int) $area->property_house_id !== (int) $house->id) {
            abort(404);
        }
    }

    private function assertPhotoBelongs(PropertyHouse $house, InspectionPhoto $photo): void
    {
        $photo->loadMissing('inspectionArea');
        if (!$photo->inspectionArea || (int) $photo->inspectionArea->property_house_id !== (int) $house->id) {
            abort(404);
        }
    }

    private function photoHasWork(InspectionPhoto $photo): bool
    {
        return $photo->hasUserEdits();
    }

    /**
     * Shared hosting: web root reads from public_html/storage, not always symlinked to storage/app/public.
     */
    private function copyUploadedToPublicStorage(string $relativePath, PropertyHouse $house): void
    {
        $relativePath = ltrim($relativePath, '/\\');
        $source = Storage::disk('public')->path($relativePath);
        if (!is_file($source)) {
            return;
        }

        $destDirs = [
            public_path('storage/inspections/' . $house->id),
            dirname(base_path(), 2) . '/storage/inspections/' . $house->id,
        ];

        foreach ($destDirs as $destDir) {
            if (!is_dir($destDir)) {
                @mkdir($destDir, 0755, true);
            }
            $target = rtrim($destDir, '/\\') . DIRECTORY_SEPARATOR . basename($relativePath);
            @copy($source, $target);
        }
    }
}