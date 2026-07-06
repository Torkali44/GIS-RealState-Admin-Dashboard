<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InspectionArea;
use App\Models\InspectionPhoto;
use App\Models\PropertyHouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Services\DriveReportSyncService;
use App\Support\AreaNameSanitizer;
use App\Support\InspectionReportCache;

class InspectionAreaController extends Controller
{
    public function store(Request $request, PropertyHouse $house): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $max = (int) $house->inspectionAreas()->max('sort_order');
        $house->inspectionAreas()->create([
            'name' => $data['name'],
            'sort_order' => $max + 1,
        ]);
        $this->normalizeSortOrders($house);

        InspectionReportCache::forget($house);
        $house->touch();
        DriveReportSyncService::scheduleSync($house);

        return back()->with('status', 'تمت إضافة القسم.');
    }

    public function bulkStore(Request $request, PropertyHouse $house): JsonResponse
    {
        @ini_set('max_execution_time', '120');

        $maxAreas = max(1, (int) config('upload.folder_import_max_areas', 30));

        $data = $request->validate([
            'sections' => ['required', 'array', 'min:1', 'max:'.$maxAreas],
            'sections.*' => ['required', 'string', 'max:255'],
        ]);

        $areas = [];
        $importedAreaIds = [];

        DB::transaction(function () use ($house, $data, &$areas, &$importedAreaIds): void {
            foreach ($data['sections'] as $index => $rawName) {
                $name = AreaNameSanitizer::sanitize((string) $rawName);
                if ($name === '') {
                    $areas[] = [
                        'index' => $index,
                        'id' => null,
                        'name' => null,
                        'upload_url' => null,
                        'invalid' => true,
                    ];

                    continue;
                }

                $existing = $house->inspectionAreas()->where('name', $name)->first();
                if ($existing) {
                    $existing->update(['sort_order' => $index + 1]);
                    $importedAreaIds[] = (int) $existing->id;
                    $areas[] = [
                        'index' => $index,
                        'id' => (int) $existing->id,
                        'name' => $name,
                        'upload_url' => route('admin.houses.areas.photos.store', [$house, $existing]),
                        'existed' => true,
                    ];

                    continue;
                }

                $area = $house->inspectionAreas()->create([
                    'name' => $name,
                    'sort_order' => $index + 1,
                ]);
                $importedAreaIds[] = (int) $area->id;

                $areas[] = [
                    'index' => $index,
                    'id' => (int) $area->id,
                    'name' => $name,
                    'upload_url' => route('admin.houses.areas.photos.store', [$house, $area]),
                    'existed' => false,
                ];
            }

            $tail = count($importedAreaIds);
            $house->inspectionAreas()
                ->whereNotIn('id', $importedAreaIds)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->each(function (InspectionArea $area) use (&$tail): void {
                    $tail++;
                    $area->update(['sort_order' => $tail]);
                });

            $this->normalizeSortOrders($house);
        });

        if ($areas === [] || ! collect($areas)->contains(fn (array $a): bool => ! empty($a['upload_url']))) {
            return response()->json([
                'success' => false,
                'message' => 'لم يُعثر على أسماء أقسام صالحة.',
            ], 422);
        }

        InspectionReportCache::forget($house);
        $house->touch();
        DriveReportSyncService::scheduleSync($house);

        return response()->json([
            'success' => true,
            'areas' => $areas,
            'created_count' => count(array_filter($areas, fn (array $a): bool => ! ($a['existed'] ?? false))),
        ]);
    }

    public function importBatchStatus(Request $request, PropertyHouse $house): JsonResponse
    {
        $batchId = $request->query('batch_id');
        if (! is_string($batchId) || trim($batchId) === '') {
            return response()->json(['success' => false, 'message' => 'معرّف الدفعة مطلوب.'], 422);
        }

        $rows = InspectionPhoto::query()
            ->where('upload_batch_id', $batchId)
            ->whereNotNull('upload_file_key')
            ->whereHas('inspectionArea', fn ($q) => $q->where('property_house_id', $house->id))
            ->get(['inspection_area_id', 'upload_file_key']);

        $completedKeys = [];
        foreach ($rows as $row) {
            $areaId = (string) $row->inspection_area_id;
            $completedKeys[$areaId] ??= [];
            $completedKeys[$areaId][] = $row->upload_file_key;
        }

        return response()->json([
            'success' => true,
            'completed_keys' => $completedKeys,
            'total_saved' => $rows->count(),
        ]);
    }

    public function photosPanel(Request $request, PropertyHouse $house, InspectionArea $area): Response|\Illuminate\Http\RedirectResponse
    {
        $this->assertAreaBelongs($house, $area);

        if (! $request->ajax() && ! $request->wantsJson()) {
            $params = collect($request->query())->filter(fn ($v) => is_scalar($v))->all();

            return redirect()->to(
                route('admin.houses.show', $house)
                .($params !== [] ? '?'.http_build_query($params) : '')
                .'#area-'.$area->id
            );
        }

        $perPageOptions = [20, 40, 80, 120];
        $perPage = (int) $request->query('photos_per_page', session('photos_per_page', 40));
        if (! in_array($perPage, $perPageOptions, true)) {
            $perPage = 40;
        }

        $pageName = 'area_'.$area->id.'_page';
        $currentPage = max(1, (int) $request->query($pageName, 1));

        if ($request->filled('highlight_photo')) {
            $photoId = (int) $request->query('highlight_photo');
            $index = $area->photos()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->pluck('id')
                ->search($photoId);

            if ($index !== false) {
                $currentPage = (int) floor($index / $perPage) + 1;
            }
        }

        \Illuminate\Pagination\Paginator::currentPageResolver(fn () => $currentPage);

        return response()->view('admin.houses._area_photos_panel', [
            'house' => $house,
            'area' => $area,
            'perPage' => $perPage,
        ]);
    }

    public function update(Request $request, PropertyHouse $house, InspectionArea $area): RedirectResponse
    {
        $this->assertAreaBelongs($house, $area);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($house, $area, $data): void {
            $area->name = $data['name'];

            if (array_key_exists('sort_order', $data) && $data['sort_order'] !== null) {
                $current = (int) $area->sort_order;
                $max = max(1, (int) $house->inspectionAreas()->max('sort_order'));
                $requested = max(1, min((int) $data['sort_order'], $max));

                if ($requested < $current) {
                    $house->inspectionAreas()
                        ->whereKeyNot($area->id)
                        ->whereBetween('sort_order', [$requested, $current - 1])
                        ->increment('sort_order');
                    $area->sort_order = $requested;
                } elseif ($requested > $current) {
                    $house->inspectionAreas()
                        ->whereKeyNot($area->id)
                        ->whereBetween('sort_order', [$current + 1, $requested])
                        ->decrement('sort_order');
                    $area->sort_order = $requested;
                }
            }

            $area->save();
            $this->normalizeSortOrders($house);
        });

        InspectionReportCache::forget($house);
        $house->touch();
        DriveReportSyncService::scheduleSync($house);

        return redirect()
            ->to(route('admin.houses.show', $house) . '#area-' . $area->id)
            ->with('status', 'تم تحديث بيانات القسم.');
    }

    public function destroy(PropertyHouse $house, InspectionArea $area): RedirectResponse
    {
        $this->assertAreaBelongs($house, $area);

        $area->load('photos');
        foreach ($area->photos as $photo) {
            $photo->delete();
        }

        $area->delete();
        $this->normalizeSortOrders($house);

        InspectionReportCache::forget($house);
        $house->touch();
        DriveReportSyncService::scheduleSync($house);

        return back()->with('status', 'تم حذف القسم وجميع صوره.');
    }

    public function move(Request $request, PropertyHouse $house, InspectionArea $area): RedirectResponse
    {
        $this->assertAreaBelongs($house, $area);

        $data = $request->validate([
            'direction' => ['required', 'in:up,down'],
        ]);

        $query = $house->inspectionAreas()->whereKeyNot($area->id);
        $neighbor = $data['direction'] === 'up'
            ? $query->where('sort_order', '<', $area->sort_order)->orderByDesc('sort_order')->first()
            : $query->where('sort_order', '>', $area->sort_order)->orderBy('sort_order')->first();

        if (! $neighbor) {
            return back();
        }

        $currentOrder = (int) $area->sort_order;
        $area->update(['sort_order' => (int) $neighbor->sort_order]);
        $neighbor->update(['sort_order' => $currentOrder]);

        InspectionReportCache::forget($house);
        $house->touch();
        return back()->with('status', 'تم تحديث ترتيب الأقسام.');
    }

    private function assertAreaBelongs(PropertyHouse $house, InspectionArea $area): void
    {
        if ((int) $area->property_house_id !== (int) $house->id) {
            abort(404);
        }
    }

    private function normalizeSortOrders(PropertyHouse $house): void
    {
        $areas = $house->inspectionAreas()->orderBy('sort_order')->orderBy('id')->get();
        foreach ($areas as $index => $item) {
            $expected = $index + 1;
            if ((int) $item->sort_order !== $expected) {
                $item->sort_order = $expected;
                $item->save();
            }
        }
    }
}