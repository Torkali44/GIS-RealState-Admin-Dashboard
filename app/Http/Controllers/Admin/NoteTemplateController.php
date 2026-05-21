<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NoteCategory;
use App\Models\NoteTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NoteTemplateController extends Controller
{
    public function store(Request $request, NoteCategory $category): RedirectResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:2000'],
        ]);

        $max = (int) $category->templates()->max('sort_order');
        $category->templates()->create([
            'text' => $data['text'],
            'sort_order' => $max + 1,
        ]);

        return back()->with('status', 'تمت إضافة الملاحظة الجاهزة.');
    }

    public function update(Request $request, NoteCategory $category, NoteTemplate $template): RedirectResponse
    {
        $this->assertTemplateBelongs($category, $template);

        $data = $request->validate([
            'text' => ['required', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:1'],
        ]);

        $template->text = $data['text'];
        if (array_key_exists('sort_order', $data) && $data['sort_order'] !== null) {
            $template->sort_order = (int) $data['sort_order'];
        }
        $template->save();

        return back()->with('status', 'تم تحديث الملاحظة الجاهزة.');
    }

    public function destroy(NoteCategory $category, NoteTemplate $template): RedirectResponse
    {
        $this->assertTemplateBelongs($category, $template);

        $template->delete();

        return back()->with('status', 'تم حذف الملاحظة الجاهزة.');
    }

    private function assertTemplateBelongs(NoteCategory $category, NoteTemplate $template): void
    {
        if ((int) $template->note_category_id !== (int) $category->id) {
            abort(404);
        }
    }
}
