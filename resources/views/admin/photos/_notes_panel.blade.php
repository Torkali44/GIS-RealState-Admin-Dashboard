{{--
    لوحة الملاحظات الجاهزة + الإضافة اليدوية + التعديل + الحذف.
    تقبل البيانات مباشرة عبر متغيرات تمرَّر من @include:
        - panelAreaName (string)
        - panelCategories (array of categories)
        - panelInitialNotes (array of notes)
    تحفظ البيانات في <script type="application/json"> لتفادي مشاكل @js() مع يونيكود.
    inputs مخفية باسم notes[idx][text]/[category_id] داخل النموذج المحيط.
--}}
@php
    $panelAreaName = $panelAreaName ?? '';
    $panelCategories = $panelCategories ?? [];
    $panelInitialNotes = $panelInitialNotes ?? [];
@endphp

{{-- Data block: safe JSON inside a non-executable script tag --}}
<script type="application/json" id="notes-panel-data">
@json(['areaName' => $panelAreaName, 'categories' => $panelCategories, 'initialNotes' => $panelInitialNotes])
</script>

{{-- Function definition in its own block so it never fails due to data issues --}}
<script>
    window.notesPanel = function () {
        return {
            areaName: '',
            categories: [],
            selectedCategoryId: 0,
            selectedTemplateId: '',
            manualText: '',
            notes: [],

            init() {
                var d = {};
                try {
                    var el = document.getElementById('notes-panel-data');
                    if (el) d = JSON.parse(el.textContent || '{}');
                } catch (e) {
                    console.error('Notes panel data parse error:', e);
                }
                this.areaName = d.areaName || '';
                this.categories = Array.isArray(d.categories) ? d.categories : [];
                this.notes = Array.isArray(d.initialNotes)
                    ? d.initialNotes.map(function (n) {
                        if (typeof n === 'string') {
                            return { text: n, category_id: null };
                        }
                        return {
                            text: String((n && n.text) || '').trim(),
                            category_id: (n && n.category_id) ? Number(n.category_id) : null,
                        };
                    }).filter(function (n) { return n.text; })
                    : [];
                if (this.categories.length) {
                    this.selectedCategoryId = 0;
                }
            },

            currentTemplates() {
                var self = this;
                var cat = this.categories.find(function (c) { return c.id === self.selectedCategoryId; });
                return cat ? (cat.templates || []) : [];
            },

            // عرض اسم التصنيف بصيغة: "{رقم الترتيب}. {اسم بدون رقم قديم}"
            // يتعامل مع: "🚗 1. مواقف" → "1. 🚗 مواقف"   و   "غرفة" → "5. غرفة"
            formatCatLabel(cat) {
                if (!cat) return '';
                var name = String(cat.name || '').trim();
                // إزالة "إيموجي اختياري + رقم + نقطة + مسافات" من بداية الاسم
                var stripped = name.replace(/^(\d+\s*[.\-:)]\s*)/, '').trim();
                var order = cat.sort_order || 0;
                return order + '. ' + stripped;
            },

            applyLocation(text) {
                var placeholder = '(الموقع)';
                var out = (text || '').trim();
                var area = (this.areaName || '').trim();
                if (!area) return out;
                if (out.indexOf(placeholder) !== -1) {
                    return out.split(placeholder).join(area).trim();
                }
                if (out.toLowerCase().indexOf(area.toLowerCase()) !== -1) {
                    return out;
                }
                out = out.replace(/[\.،,\s]+$/u, '');
                return out + ' في ' + area + '.';
            },

            addFromTemplate() {
                if (!this.selectedTemplateId) return;
                var self = this;
                var tpl = this.currentTemplates().find(function (t) {
                    return String(t.id) === String(self.selectedTemplateId);
                });
                if (!tpl) return;
                var finalText = this.applyLocation(tpl.text);
                if (this.notes.some(function (n) { return n.text === finalText && Number(n.category_id || 0) === Number(self.selectedCategoryId || 0); })) return;
                this.notes.push({
                    text: finalText,
                    category_id: this.selectedCategoryId > 0 ? this.selectedCategoryId : null,
                });
                this.selectedTemplateId = '';
            },

            addManual() {
                var t = (this.manualText || '').trim();
                if (!t) return;
                this.notes.push({
                    text: t,
                    category_id: this.selectedCategoryId > 0 ? this.selectedCategoryId : null,
                });
                this.manualText = '';
            },

            removeNote(idx) { this.notes.splice(idx, 1); },

            moveUp(idx) {
                if (idx <= 0) return;
                var tmp = this.notes[idx - 1];
                this.notes[idx - 1] = this.notes[idx];
                this.notes[idx] = tmp;
            },

            moveDown(idx) {
                if (idx >= this.notes.length - 1) return;
                var tmp = this.notes[idx + 1];
                this.notes[idx + 1] = this.notes[idx];
                this.notes[idx] = tmp;
            },

            categoryName(catId) {
                if (!catId) return 'أخرى';
                var cat = this.categories.find(function (c) { return Number(c.id) === Number(catId); });
                return cat ? String(cat.name || '').trim() : 'أخرى';
            },

            groupedNotes() {
                var groups = {};
                var order = [];
                this.notes.forEach(function (n) {
                    var key = n.category_id ? String(n.category_id) : 'other';
                    if (!groups[key]) {
                        groups[key] = [];
                        order.push(key);
                    }
                    groups[key].push(n);
                });
                return order.map(function (key) {
                    return { key: key, notes: groups[key] };
                });
            },
        };
    };
</script>

<div class="rounded-2xl border border-emerald-500/30 bg-emerald-500/5 p-5 space-y-4 overflow-hidden"
    x-data="notesPanel()" x-init="init()">

    {{-- Header --}}
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0 flex-1">
            <h3 class="text-base font-bold text-emerald-300">الملاحظات الجاهزة للقسم</h3>
            <p class="mt-1 text-xs text-slate-400">
                اختر من القائمة لإضافة الملاحظة. كلمة <code class="rounded bg-slate-800 px-1 text-emerald-400">(الموقع)</code>
                تُستبدل تلقائياً باسم القسم
                <span class="font-bold text-emerald-300" x-text="areaName || 'الحالي'"></span>.
            </p>
        </div>
        <a href="{{ route('admin.note-categories.index') }}" target="_blank"
            class="shrink-0 rounded-lg border border-slate-600 px-3 py-1.5 text-xs font-bold text-slate-300 hover:border-emerald-500 hover:text-emerald-400 whitespace-nowrap">
            إدارة المكتبة
        </a>
    </div>

    {{-- Category + Template selectors --}}
    <div class="grid gap-3 sm:grid-cols-2">
        <div class="min-w-0">
            <label class="mb-1 block text-xs font-medium text-slate-300">التصنيف</label>
            <select x-model.number="selectedCategoryId" @change="selectedTemplateId = ''"
                class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white focus:border-emerald-500 focus:outline-none">
                <option value="0">— اختر تصنيفاً —</option>
                <template x-for="cat in categories" :key="cat.id">
                    <option :value="cat.id" x-text="formatCatLabel(cat)"></option>
                </template>
            </select>
        </div>
        <div class="min-w-0">
            <label class="mb-1 block text-xs font-medium text-slate-300">ملاحظة جاهزة</label>
            <div class="flex gap-2">
                <select x-model="selectedTemplateId" :disabled="!currentTemplates().length"
                    class="min-w-0 flex-1 rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white focus:border-emerald-500 focus:outline-none disabled:opacity-50">
                    <option value=""
                        x-text="currentTemplates().length ? '— اختر ملاحظة —' : 'لا توجد ملاحظات في هذا التصنيف'"></option>
                    <template x-for="tpl in currentTemplates()" :key="tpl.id">
                        <option :value="tpl.id" x-text="tpl.text"></option>
                    </template>
                </select>
                <button type="button" @click="addFromTemplate()"
                    :disabled="!selectedTemplateId"
                    class="shrink-0 rounded-lg bg-emerald-500 px-4 py-2 text-sm font-bold text-slate-950 hover:bg-emerald-400 disabled:cursor-not-allowed disabled:opacity-50 whitespace-nowrap">
                    + أضف
                </button>
            </div>
        </div>
    </div>

    {{-- Manual note input --}}
    <div class="rounded-lg border border-dashed border-slate-700 bg-slate-950/40 p-3">
        <label class="mb-1 block text-xs font-medium text-slate-300">إضافة ملاحظة يدوياً</label>
        <div class="flex flex-col gap-2 sm:flex-row">
            <input type="text" x-model="manualText" maxlength="2000"
                @keydown.enter.prevent="addManual()"
                placeholder="اكتب ملاحظة جديدة..."
                class="min-w-0 flex-1 rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white placeholder:text-slate-600 focus:border-emerald-500 focus:outline-none">
            <button type="button" @click="addManual()" :disabled="!manualText.trim()"
                class="shrink-0 rounded-lg bg-slate-700 px-4 py-2 text-sm font-bold text-white hover:bg-slate-600 disabled:cursor-not-allowed disabled:opacity-50">
                إضافة
            </button>
        </div>
    </div>

    {{-- Notes list --}}
    <div class="min-w-0">
        <div class="mb-2 flex items-center justify-between">
            <h4 class="text-sm font-bold text-white">
                ملاحظات الصورة
                (<span x-text="notes.length"></span>)
            </h4>
            <button type="button" @click="notes = []" x-show="notes.length"
                class="text-xs font-bold text-red-400 hover:underline">
                حذف الكل
            </button>
        </div>

        <ul class="space-y-3" x-show="notes.length">
            <template x-for="group in groupedNotes()" :key="group.key">
                <li class="rounded-lg border border-slate-700 bg-slate-950/50 p-3">
                    <h5 class="mb-2 text-xs font-bold text-emerald-300" x-text="categoryName(group.key === 'other' ? null : Number(group.key))"></h5>
                    <ul class="space-y-2">
                        <template x-for="note in group.notes" :key="note.text + '-' + (note.category_id || 'other')">
                            <li class="rounded-lg border border-slate-700 bg-slate-950/70 p-2.5 overflow-hidden"
                                x-data="{ editing: false, draft: '' }">
                                <div x-show="!editing" class="flex items-start justify-between gap-2">
                                    <p class="text-sm leading-relaxed text-white break-words overflow-wrap-anywhere min-w-0 flex-1"
                                        x-text="note.text"></p>
                                    <div class="flex shrink-0 flex-nowrap gap-1">
                                        <button type="button" @click="draft = note.text; editing = true"
                                            class="rounded border border-slate-600 px-2 py-1 text-xs font-bold text-slate-300 hover:border-emerald-500 hover:text-emerald-400">تعديل</button>
                                        <button type="button" @click="removeNote(notes.indexOf(note))"
                                            class="rounded border border-red-500/40 px-2 py-1 text-xs font-bold text-red-400 hover:bg-red-500/10">حذف</button>
                                    </div>
                                </div>
                                <div x-show="editing" x-cloak class="space-y-2">
                                    <textarea x-model="draft" rows="3" maxlength="2000"
                                        class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white focus:border-emerald-500 focus:outline-none"></textarea>
                                    <div class="flex justify-end gap-2">
                                        <button type="button"
                                            @click="if (draft.trim()) { note.text = draft.trim(); editing = false; }"
                                            class="rounded-lg bg-emerald-500 px-3 py-1.5 text-xs font-bold text-slate-950 hover:bg-emerald-400">حفظ
                                            التعديل</button>
                                        <button type="button" @click="editing = false"
                                            class="rounded-lg bg-slate-700 px-3 py-1.5 text-xs font-bold text-white hover:bg-slate-600">إلغاء</button>
                                    </div>
                                </div>
                            </li>
                        </template>
                    </ul>
                </li>
            </template>
        </ul>

        <p x-show="!notes.length" class="text-center text-sm text-slate-500 italic py-4">
            لم تُضَف أي ملاحظة لهذه الصورة بعد.
        </p>
    </div>

    {{-- Hidden inputs that get submitted with the form --}}
    <template x-for="(note, idx) in notes" :key="'in-' + idx">
        <div>
            <input type="hidden" :name="'notes[' + idx + '][text]'" :value="note.text">
            <input type="hidden" :name="'notes[' + idx + '][category_id]'" :value="note.category_id || ''">
        </div>
    </template>
</div>

<style>
    /* Ensure overflow-wrap works on note text */
    .overflow-wrap-anywhere {
        overflow-wrap: anywhere;
        word-break: break-word;
    }
</style>
