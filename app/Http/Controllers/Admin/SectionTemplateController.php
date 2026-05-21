<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SectionTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SectionTemplateController extends Controller
{
    public function index(): View
    {
        $sections = SectionTemplate::ordered()->get();
        return view('admin.section_templates.index', compact('sections'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:section_templates,name'],
        ]);

        $max = (int) SectionTemplate::max('sort_order');
        $created = SectionTemplate::create([
            'name' => $data['name'],
            'sort_order' => $max + 1,
        ]);

        return redirect()
            ->to(route('admin.section-templates.index') . '#section-' . $created->id)
            ->with('status', 'تمت إضافة القسم الجاهز.');
    }

    public function update(Request $request, SectionTemplate $sectionTemplate): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:section_templates,name,' . $sectionTemplate->id],
            'sort_order' => ['nullable', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($sectionTemplate, $data): void {
            $sectionTemplate->name = $data['name'];

            if (array_key_exists('sort_order', $data) && $data['sort_order'] !== null) {
                $current = (int) $sectionTemplate->sort_order;
                $max = max(1, (int) SectionTemplate::max('sort_order'));
                $requested = max(1, min((int) $data['sort_order'], $max));

                if ($requested < $current) {
                    SectionTemplate::query()
                        ->whereKeyNot($sectionTemplate->id)
                        ->whereBetween('sort_order', [$requested, $current - 1])
                        ->increment('sort_order');
                    $sectionTemplate->sort_order = $requested;
                } elseif ($requested > $current) {
                    SectionTemplate::query()
                        ->whereKeyNot($sectionTemplate->id)
                        ->whereBetween('sort_order', [$current + 1, $requested])
                        ->decrement('sort_order');
                    $sectionTemplate->sort_order = $requested;
                }
            }

            $sectionTemplate->save();
            $this->normalizeOrders();
        });

        return redirect()
            ->to(route('admin.section-templates.index') . '#section-' . $sectionTemplate->id)
            ->with('status', 'تم تحديث القسم.');
    }

    public function destroy(SectionTemplate $sectionTemplate): RedirectResponse
    {
        $sectionTemplate->delete();
        $this->normalizeOrders();

        return back()->with('status', 'تم حذف القسم من المكتبة.');
    }

    public function move(Request $request, SectionTemplate $sectionTemplate): RedirectResponse
    {
        $data = $request->validate([
            'direction' => ['required', 'in:up,down'],
        ]);

        $query = SectionTemplate::query()->whereKeyNot($sectionTemplate->id);
        $neighbor = $data['direction'] === 'up'
            ? $query->where('sort_order', '<', $sectionTemplate->sort_order)->orderByDesc('sort_order')->first()
            : $query->where('sort_order', '>', $sectionTemplate->sort_order)->orderBy('sort_order')->first();

        if (! $neighbor) {
            return back();
        }

        DB::transaction(function () use ($sectionTemplate, $neighbor): void {
            $current = (int) $sectionTemplate->sort_order;
            $sectionTemplate->update(['sort_order' => (int) $neighbor->sort_order]);
            $neighbor->update(['sort_order' => $current]);
        });

        $this->normalizeOrders();

        return redirect()
            ->to(route('admin.section-templates.index') . '#section-' . $sectionTemplate->id)
            ->with('status', 'تم تحديث الترتيب.');
    }

    private function normalizeOrders(): void
    {
        $items = SectionTemplate::ordered()->get();
        foreach ($items as $index => $item) {
            $expected = $index + 1;
            if ((int) $item->sort_order !== $expected) {
                $item->update(['sort_order' => $expected]);
            }
        }
    }
}
