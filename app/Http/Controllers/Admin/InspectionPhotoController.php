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
use App\Support\MergedPhotoNotes;
use App\Support\PhotoImageMerger;
use App\Support\PhotoReturnUrl;
use App\Support\ImageThumbCache;
use App\Support\PhotoImageUrl;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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
            'photos.*' => ['nullable', 'file'],
            'upload_batch_id' => ['nullable', 'string', 'max:100'],
            'upload_file_keys' => ['nullable', 'array'],
            'upload_file_keys.*' => ['nullable', 'string', 'max:500'],
            'upload_finalize' => ['sometimes', 'boolean'],
        ]);

        $maxSort = (int) $area->photos()->max('sort_order');
        $disk = Storage::disk('public');
        $created = 0;
        $skipped = 0;
        $failed = 0;
        $driveFailed = 0;
        $driveQueued = 0;
        $batchId = $request->input('upload_batch_id');
        $fileKeys = $request->input('upload_file_keys', []);
        $finalize = $request->boolean('upload_finalize');
        $createdPhotoIds = [];

        $uploadedFiles = $request->file('photos', []);
        $pairedFiles = [];
        foreach ($uploadedFiles as $index => $file) {
            if ($file && $file->isValid()) {
                $pairedFiles[] = [
                    'index' => $index,
                    'file' => $file,
                    'filename' => $file->getClientOriginalName(),
                ];
            } elseif ($file) {
                $failed++;
            }
        }

        usort($pairedFiles, function ($a, $b) {
            return InspectionPhoto::compareFilenames($a['filename'], $b['filename']);
        });

        foreach ($pairedFiles as $pair) {
            $index = $pair['index'];
            $file = $pair['file'];

            try {
                validator(
                    ['photo' => $file],
                    ['photo' => ['file', 'image', 'max:20480']]
                )->validate();
            } catch (ValidationException) {
                $failed++;

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

            $path = $file->store("inspections/{$house->id}", 'public');

            if (! in_array(strtolower((string) $file->getClientOriginalExtension()), ['jpg', 'jpeg'], true)) {
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
                $createdPhotoIds[] = (int) $photo->id;
                $created++;
            } catch (QueryException $e) {
                if ((string) $e->getCode() !== '23000') {
                    if ($disk->exists($path)) {
                        $disk->delete($path);
                    }
                    $failed++;

                    continue;
                }
                if ($disk->exists($path)) {
                    $disk->delete($path);
                }
                $skipped++;
            }
        }

        if (DriveMediaService::enabled() && $createdPhotoIds !== []) {
            if (config('upload.defer_drive_push', true)) {
                DriveMediaService::schedulePushPhotos($createdPhotoIds);
                $driveQueued = count($createdPhotoIds);
            } else {
                foreach ($createdPhotoIds as $photoId) {
                    $photo = InspectionPhoto::query()->find($photoId);
                    if ($photo && ! DriveMediaService::pushPhoto($photo)) {
                        $driveFailed++;
                    }
                }
            }
        }

        if ($finalize && ($created > 0 || $skipped > 0)) {
            InspectionReportCache::forget($house);
            $house->touch();
            DriveReportSyncService::scheduleSync($house);
        } elseif ($created > 0) {
            $house->touch();
        }

        $totalInArea = (int) $area->photos()->count();
        $message = 'تم رفع '.$created.' صور بنجاح.';
        if ($skipped > 0) {
            $message .= ' (تخطي '.$skipped.' مكرر/محفوظ مسبقاً).';
        }
        if ($failed > 0) {
            $message .= ' (فشل '.$failed.' ملف).';
        }
        if ($driveQueued > 0) {
            $message .= ' (رفع Drive في الخلفية لـ '.$driveQueued.' صورة).';
        }
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
                'failed' => $failed,
                'total_in_area' => $totalInArea,
                'area_id' => (int) $area->id,
                'drive_failed' => $driveFailed,
                'drive_queued' => $driveQueued,
                'drive_synced' => $driveFailed === 0 && $driveQueued === 0,
                'finalized' => $finalize,
            ]);
        }

        return back()->with($driveFailed > 0 ? 'error' : 'status', $message);
    }

    public function storeMerged(Request $request, PropertyHouse $house, InspectionArea $area): RedirectResponse|JsonResponse
    {
        $this->assertAreaBelongs($house, $area);

        $data = $request->validate([
            'merged_image' => ['nullable', 'file', 'image', 'max:51200'],
            'description' => ['nullable', 'string', 'max:8000'],
        ]);

        $sourceIds = array_values(array_unique(array_filter(array_map(
            'intval',
            (array) $request->input('source_photo_ids', [])
        ))));

        if (count($sourceIds) < 2) {
            throw ValidationException::withMessages([
                'merge' => ['يجب تحديد صورتين على الأقل من نفس القسم.'],
            ]);
        }

        $sources = $area->photos()->whereIn('id', $sourceIds)->orderBy('sort_order')->orderBy('id')->get();
        if ($sources->count() !== count($sourceIds)) {
            throw ValidationException::withMessages([
                'merge' => ['بعض الصور المحددة غير متوفرة — حدّث الصفحة ثم أعد الدمج.'],
            ]);
        }

        $mergedMeta = MergedPhotoNotes::fromSources($sources, $data['description'] ?? null);

        $disk = Storage::disk('public');
        $tmpMerge = null;

        if ($request->hasFile('merged_image')) {
            $path = $request->file('merged_image')->store("inspections/{$house->id}", 'public');
        } else {
            @set_time_limit(180);
            @ini_set('memory_limit', '512M');

            $tmpMerge = PhotoImageMerger::mergePhotos(
                $sources->all(),
                (int) config('upload.merge_max_height', 900),
                8,
                (int) round((float) config('upload.merge_jpeg_quality', 0.85) * 100)
            );

            if ($tmpMerge === null || ! is_readable($tmpMerge)) {
                throw ValidationException::withMessages([
                    'merge' => ['تعذر دمج الصور — تأكد أن الصور المحددة متاحة على السيرفر (جرّب «إصلاح عرض الصور»).'],
                ]);
            }

            $filename = 'merged-'.now()->format('Ymd-His').'.jpg';
            $path = "inspections/{$house->id}/{$filename}";
            $stream = fopen($tmpMerge, 'rb');
            if ($stream === false) {
                @unlink($tmpMerge);
                throw ValidationException::withMessages([
                    'merge' => ['تعذر حفظ الصورة المدمجة.'],
                ]);
            }
            $disk->put($path, $stream);
            fclose($stream);
            @unlink($tmpMerge);
        }

        if ($request->hasFile('merged_image')) {
            $converted = RasterImageForTcpdf::convertStoredFileToJpegIfNeeded($disk, $path);
            if ($converted !== null) {
                $path = $converted;
            }
        }

        $this->copyUploadedToPublicStorage($path, $house);

        $maxSort = (int) $area->photos()->max('sort_order');
        $photo = null;
        $deleted = 0;

        DB::transaction(function () use ($area, $path, $mergedMeta, $maxSort, $sources, &$photo, &$deleted): void {
            $photo = InspectionPhoto::create([
                'inspection_area_id' => $area->id,
                'original_path' => $path,
                'composite_path' => $path,
                'original_filename' => 'merged-'.now()->format('Ymd-His').'.jpg',
                'description' => $mergedMeta['description'],
                'notes_json' => $mergedMeta['notes_json'] !== [] ? $mergedMeta['notes_json'] : null,
                'sort_order' => $maxSort + 1,
            ]);

            $deleted = 0;
            foreach ($sources as $source) {
                if ((int) $source->id === (int) $photo->id) {
                    continue;
                }
                $source->delete();
                $deleted++;
            }
        });

        if (DriveMediaService::enabled()) {
            DriveMediaService::schedulePushPhotos([(int) $photo->id]);
        }

        InspectionReportCache::forget($house);
        $house->touch();
        DriveReportSyncService::scheduleSync($house);

        $message = 'تم دمج الصور وحفظها كصورة جديدة.';
        if ($deleted > 0) {
            $message .= ' وتم حذف '.$deleted.' من الصور الأصلية.';
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'area_id' => (int) $area->id,
                'deleted' => $deleted,
                'photo_count' => (int) $area->photos()->count(),
            ]);
        }

        return redirect()
            ->route('admin.houses.show', $house)
            ->withFragment('area-'.$area->id)
            ->with('status', $message);
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
            $thumbSize = (int) $request->query('s', 0);
            if ($thumbSize >= 80 && $thumbSize <= 800) {
                $thumb = ImageThumbCache::path($absolute, $thumbSize);
                if ($thumb) {
                    return $this->binaryImageFileResponse($thumb, 86400);
                }
            }

            return $this->binaryImageFileResponse($absolute, 3600);
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

    private function binaryImageFileResponse(string $absolute, int $maxAge = 300): BinaryFileResponse|Response
    {
        if (! is_readable($absolute)) {
            abort(404, 'ملف الصورة غير قابل للقراءة على السيرفر.');
        }

        $mime = mime_content_type($absolute) ?: 'image/jpeg';

        try {
            return response()->file($absolute, [
                'Content-Type' => $mime,
                'Cache-Control' => 'private, max-age='.$maxAge,
            ]);
        } catch (Throwable) {
            $bytes = file_get_contents($absolute);
            if ($bytes === false || $bytes === '') {
                abort(404, 'تعذر قراءة ملف الصورة.');
            }

            return response($bytes, 200, [
                'Content-Type' => $mime,
                'Content-Length' => (string) strlen($bytes),
                'Cache-Control' => 'private, max-age='.$maxAge,
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
            'apply_category_id' => ['nullable', 'integer', 'exists:note_categories,id'],
        ]);

        $notes = $this->normalizeIncomingNotes($request->input('notes', []));
        $applyCategoryId = isset($data['apply_category_id']) ? (int) $data['apply_category_id'] : 0;
        if ($applyCategoryId > 0) {
            $notes = $this->applyCategoryToNotes($notes, $applyCategoryId, $data['description'] ?? null);
            $data['description'] = null;
        }

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

        $returnUrl = PhotoReturnUrl::normalize($request->input('redirect_to'), $house, $photo);
        if ($returnUrl !== null) {
            return redirect($returnUrl)->with('status', 'تم حفظ التعديلات على الصورة.');
        }

        $photo->loadMissing('inspectionArea');
        if ($photo->inspectionArea) {
            return redirect(PhotoReturnUrl::forHousePhoto(
                $house,
                $photo->inspectionArea,
                $photo
            ))->with('status', 'تم حفظ التعديلات على الصورة.');
        }

        return redirect()
            ->route('admin.houses.show', $house)
            ->withFragment('photo-'.$photo->id)
            ->with('status', 'تم حفظ التعديلات على الصورة.');
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
            'apply_category_id' => ['nullable', 'integer', 'exists:note_categories,id'],
        ]);

        $notes = $this->normalizeIncomingNotes($request->input('notes', []));
        $applyCategoryId = isset($data['apply_category_id']) ? (int) $data['apply_category_id'] : 0;
        if ($applyCategoryId > 0) {
            $notes = $this->applyCategoryToNotes($notes, $applyCategoryId, null);
        }

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
    /**
     * @param  array<int, array{text:string,category_id:int|null}>  $notes
     * @return array<int, array{text:string,category_id:int|null}>
     */
    private function applyCategoryToNotes(array $notes, int $categoryId, ?string $description): array
    {
        $notes = array_map(static function (array $note) use ($categoryId): array {
            $note['category_id'] = $categoryId;

            return $note;
        }, $notes);

        $desc = trim((string) $description);
        if ($desc === '') {
            return $notes;
        }

        $alreadyListed = collect($notes)->contains(
            static fn (array $note): bool => trim((string) ($note['text'] ?? '')) === $desc
        );
        if (! $alreadyListed) {
            $notes[] = [
                'text' => $desc,
                'category_id' => $categoryId,
            ];
        }

        return $notes;
    }

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

    public function destroyUnannotated(Request $request, PropertyHouse $house): RedirectResponse
    {
        $data = $request->validate([
            'photo_ids' => ['required', 'array', 'min:1'],
            'photo_ids.*' => ['integer', 'exists:inspection_photos,id'],
        ]);

        $deleted = 0;
        InspectionPhoto::query()
            ->whereIn('id', $data['photo_ids'])
            ->whereHas('inspectionArea', fn ($q) => $q->where('property_house_id', $house->id))
            ->get()
            ->each(function (InspectionPhoto $photo) use (&$deleted): void {
                if ($photo->hasUserEdits()) {
                    return;
                }
                $photo->delete();
                $deleted++;
            });

        InspectionReportCache::forget($house);
        $house->touch();
        DriveReportSyncService::scheduleSync($house);

        $message = 'تم حذف '.$deleted.' صورة بدون تعليم.';

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'deleted' => $deleted,
                'message' => $message,
            ]);
        }

        $thenExport = $request->input('then_export');
        if (is_string($thenExport) && $thenExport !== '' && $this->isHouseReportUrl($house, $thenExport)) {
            return redirect()->to($thenExport)->with('status', $message);
        }

        return back()->with('status', $message);
    }

    private function isHouseReportUrl(PropertyHouse $house, string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return false;
        }

        $allowed = parse_url(route('admin.houses.report', $house), PHP_URL_PATH);

        return is_string($allowed) && $allowed !== '' && $path === $allowed;
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

    public function move(Request $request, PropertyHouse $house, InspectionPhoto $photo): RedirectResponse|JsonResponse
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

        if (! $neighbor) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => 'لا يمكن نقل الصورة أكثر.']);
            }

            return back();
        }

        $currentOrder = (int) $photo->sort_order;
        $photo->update(['sort_order' => (int) $neighbor->sort_order]);
        $neighbor->update(['sort_order' => $currentOrder]);
        $photo->touch();
        $neighbor->touch();

        InspectionReportCache::forget($house);
        $house->touch();
        DriveReportSyncService::scheduleSync($house);

        if ($request->expectsJson() || $request->ajax()) {
            $areaId = (int) $photo->inspection_area_id;

            return response()->json([
                'success' => true,
                'area_id' => $areaId,
                'photo_count' => (int) InspectionPhoto::query()->where('inspection_area_id', $areaId)->count(),
            ]);
        }

        if ($request->filled('redirect_to')) {
            return redirect($request->input('redirect_to'))->with('status', 'تم تحديث ترتيب الصور.');
        }

        return back()->with('status', 'تم تحديث ترتيب الصور.');
    }

    public function resort(Request $request, PropertyHouse $house, InspectionArea $area): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $this->assertAreaBelongs($house, $area);

        $photos = $area->photos()->get()->all();
        $originalIds = array_map(fn($p) => (int) $p->id, $photos);

        usort($photos, function ($a, $b) {
            return InspectionPhoto::compareFilenames($a->original_filename ?? '', $b->original_filename ?? '');
        });

        $sortedIds = array_map(fn($p) => (int) $p->id, $photos);
        $changed = ($originalIds !== $sortedIds);

        if ($changed) {
            $now = now();
            $cases = [];
            $ids = [];

            foreach ($photos as $index => $photo) {
                $order = $index + 1;
                if ((int) $photo->sort_order !== $order) {
                    $cases[] = 'WHEN '.((int) $photo->id).' THEN '.$order;
                    $ids[] = (int) $photo->id;
                }
            }

            if ($ids !== []) {
                $caseSql = implode(' ', $cases);
                $idList = implode(',', $ids);
                DB::update(
                    "UPDATE inspection_photos SET sort_order = CASE id {$caseSql} END, updated_at = ? WHERE id IN ({$idList})",
                    [$now]
                );
            }

            InspectionReportCache::forget($house);
            $house->touch();
            DriveReportSyncService::scheduleSync($house);
        }

        $photoCount = (int) $area->photos()->count();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'changed' => $changed,
                'area_id' => (int) $area->id,
                'photo_count' => $photoCount,
                'message' => $changed ? 'تم إعادة ترتيب الصور بنجاح.' : 'بالفعل مرتبين',
            ]);
        }

        $message = $changed ? 'تم إعادة ترتيب الصور بنجاح.' : 'الصور مرتبة بالفعل.';
        return back()->with('status', $message);
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
        static $storageSymlinkOk = null;
        if ($storageSymlinkOk === null) {
            $storageSymlinkOk = is_link(public_path('storage'));
        }
        if ($storageSymlinkOk) {
            return;
        }

        $relativePath = ltrim($relativePath, '/\\');
        $source = Storage::disk('public')->path($relativePath);
        if (! is_file($source)) {
            return;
        }

        $sourceSize = @filesize($source);
        $destDirs = [
            public_path('storage/inspections/'.$house->id),
            dirname(base_path(), 2).'/storage/inspections/'.$house->id,
        ];

        foreach ($destDirs as $destDir) {
            if (! is_dir($destDir)) {
                @mkdir($destDir, 0755, true);
            }
            $target = rtrim($destDir, '/\\').DIRECTORY_SEPARATOR.basename($relativePath);
            if ($sourceSize !== false && is_file($target) && @filesize($target) === $sourceSize) {
                continue;
            }
            @copy($source, $target);
        }
    }
}