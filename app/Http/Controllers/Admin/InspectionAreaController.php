<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InspectionArea;
use App\Models\PropertyHouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        return back()->with('status', 'تمت إضافة القسم.');
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