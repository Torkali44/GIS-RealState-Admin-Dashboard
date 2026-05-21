<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NoteCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class NoteCategoryController extends Controller
{
    public function index(): View
    {
        $categories = NoteCategory::query()
            ->withCount('templates')
            ->with(['templates' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('admin.note_categories.index', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $max = (int) NoteCategory::query()->max('sort_order');
        $created = NoteCategory::query()->create([
            'name' => $data['name'],
            'sort_order' => $max + 1,
        ]);
        $this->normalizeOrders();

        return redirect()
            ->to(route('admin.note-categories.index') . '#cat-' . $created->id)
            ->with('status', 'تمت إضافة التصنيف.');
    }

    public function update(Request $request, NoteCategory $category): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($category, $data): void {
            $category->name = $data['name'];

            if (array_key_exists('sort_order', $data) && $data['sort_order'] !== null) {
                $current = (int) $category->sort_order;
                $max = max(1, (int) NoteCategory::query()->max('sort_order'));
                $requested = max(1, min((int) $data['sort_order'], $max));

                if ($requested < $current) {
                    NoteCategory::query()
                        ->whereKeyNot($category->id)
                        ->whereBetween('sort_order', [$requested, $current - 1])
                        ->increment('sort_order');
                    $category->sort_order = $requested;
                } elseif ($requested > $current) {
                    NoteCategory::query()
                        ->whereKeyNot($category->id)
                        ->whereBetween('sort_order', [$current + 1, $requested])
                        ->decrement('sort_order');
                    $category->sort_order = $requested;
                }
            }

            $category->save();
            $this->normalizeOrders();
        });

        return redirect()
            ->to(route('admin.note-categories.index') . '#cat-' . $category->id)
            ->with('status', 'تم تحديث التصنيف.');
    }

    public function destroy(NoteCategory $category): RedirectResponse
    {
        $category->delete();
        $this->normalizeOrders();

        return back()->with('status', 'تم حذف التصنيف وملاحظاته الجاهزة.');
    }

    private function normalizeOrders(): void
    {
        $items = NoteCategory::query()->orderBy('sort_order')->orderBy('id')->get();
        foreach ($items as $index => $item) {
            $expected = $index + 1;
            if ((int) $item->sort_order !== $expected) {
                $item->update(['sort_order' => $expected]);
            }
        }
    }
}
