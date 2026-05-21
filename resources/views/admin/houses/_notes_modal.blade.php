{{-- 
    مودال إدارة ملاحظات الصورة - مشترك في صفحة المنزل.
    يستخدم HTML5 <dialog> الذي يكون مخفياً بشكل افتراضي من قِبَل المتصفح،
    ولا يظهر إلا عند استدعاء dialog.showModal() عبر JS.
    لا يعتمد على CSS ولا على Alpine لإخفاء المودال.
--}}
<style>
    /* تأمين إضافي: <dialog> مخفي بشكل افتراضي بالمتصفح، لكن نضمن ذلك */
    dialog#notes-modal-dialog:not([open]) {
        display: none !important;
    }

    dialog#notes-modal-dialog {
        padding: 0;
        border: 0;
        background: transparent;
        max-width: 100vw;
        max-height: 100vh;
    }

    dialog#notes-modal-dialog::backdrop {
        background: rgba(2, 6, 23, 0.85);
        backdrop-filter: blur(8px);
    }
</style>

<dialog id="notes-modal-dialog" x-data="notesModal()">
    <div class="relative flex max-h-[90vh] w-[min(95vw,720px)] flex-col overflow-hidden rounded-2xl border border-emerald-500/30 bg-slate-900 shadow-[0_0_60px_rgba(16,185,129,0.15)]">

        <div class="flex items-center justify-between border-b border-slate-800 bg-slate-900/80 px-6 py-4">
            <div>
                <h2 class="flex items-center gap-2 text-lg font-bold text-white">
                    <svg class="h-5 w-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    ملاحظات الصورة
                </h2>
                <p class="mt-0.5 text-xs text-slate-400">
                    القسم الحالي:
                    <span class="font-bold text-emerald-400" x-text="areaName || '—'"></span>
                </p>
            </div>
            <button type="button" @click="close()"
                class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-800 hover:text-white">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="flex-1 space-y-4 overflow-y-auto px-6 py-5">
            <div class="rounded-xl border border-slate-800 bg-slate-950/50 p-4 space-y-3">
                <p class="text-xs text-slate-400">
                    اختر التصنيف ثم ملاحظة جاهزة. كلمة
                    <code class="rounded bg-slate-800 px-1 text-emerald-400">(الموقع)</code>
                    تُستبدل تلقائياً باسم القسم الحالي.
                </p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-300">التصنيف</label>
                        <select x-model.number="selectedCategoryId" @change="selectedTemplateId = ''"
                            class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white focus:border-emerald-500 focus:outline-none">
                            <option value="0">— اختر تصنيفاً —</option>
                            <template x-for="cat in categories" :key="cat.id">
                                <option :value="cat.id" x-text="formatCatLabel(cat)"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-300">ملاحظة جاهزة</label>
                        <div class="flex gap-2">
                            <select x-model="selectedTemplateId" :disabled="!currentTemplates().length"
                                class="flex-1 rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white focus:border-emerald-500 focus:outline-none disabled:opacity-50">
                                <option value=""
                                    x-text="currentTemplates().length ? '— اختر ملاحظة —' : 'لا توجد ملاحظات'"></option>
                                <template x-for="tpl in currentTemplates()" :key="tpl.id">
                                    <option :value="tpl.id" x-text="tpl.text"></option>
                                </template>
                            </select>
                            <button type="button" @click="addFromTemplate()" :disabled="!selectedTemplateId"
                                class="whitespace-nowrap rounded-lg bg-emerald-500 px-4 py-2 text-sm font-bold text-slate-950 hover:bg-emerald-400 disabled:cursor-not-allowed disabled:opacity-50">
                                + أضف
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-dashed border-slate-700 bg-slate-950/30 p-4">
                <label class="mb-1 block text-xs font-medium text-slate-300">إضافة ملاحظة يدوياً</label>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <input type="text" x-model="manualText" maxlength="2000"
                        @keydown.enter.prevent="addManual()" placeholder="اكتب ملاحظة جديدة..."
                        class="flex-1 rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white placeholder:text-slate-600 focus:border-emerald-500 focus:outline-none">
                    <button type="button" @click="addManual()" :disabled="!manualText.trim()"
                        class="rounded-lg bg-slate-700 px-4 py-2 text-sm font-bold text-white hover:bg-slate-600 disabled:cursor-not-allowed disabled:opacity-50">
                        إضافة
                    </button>
                </div>
            </div>

            <div>
                <div class="mb-2 flex items-center justify-between">
                    <h4 class="text-sm font-bold text-white">
                        ملاحظات الصورة (<span x-text="notes.length"></span>)
                    </h4>
                    <button type="button" @click="notes = []" x-show="notes.length"
                        class="text-xs font-bold text-red-400 hover:underline">
                        حذف الكل
                    </button>
                </div>

                <ul class="space-y-2" x-show="notes.length">
                    <template x-for="(note, idx) in notes" :key="idx">
                        <li class="rounded-lg border border-slate-700 bg-slate-950/60 p-3 overflow-hidden"
                            x-data="{ editing: false, draft: '' }">
                            <div x-show="!editing" class="flex items-start justify-between gap-2">
                                <div class="flex items-start gap-2 min-w-0 flex-1">
                                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-500/20 text-xs font-bold text-emerald-300 mt-0.5"
                                        x-text="idx + 1"></span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm leading-relaxed text-white break-words overflow-wrap-anywhere"
                                            x-text="note.text"></p>
                                        <p class="mt-1 text-[11px] text-slate-400" x-text="categoryLabel(note.category_id)"></p>
                                    </div>
                                </div>
                                <div class="flex shrink-0 flex-nowrap gap-1">
                                    <button type="button" @click="draft = note.text; editing = true"
                                        class="rounded border border-slate-600 px-2 py-1 text-xs font-bold text-slate-300 hover:border-emerald-500 hover:text-emerald-400">تعديل</button>
                                    <button type="button" @click="moveUp(idx)" :disabled="idx === 0"
                                        class="rounded border border-slate-700 px-2 py-1 text-xs font-bold text-slate-400 hover:border-slate-500 disabled:opacity-30"
                                        title="نقل لأعلى">▲</button>
                                    <button type="button" @click="moveDown(idx)"
                                        :disabled="idx === notes.length - 1"
                                        class="rounded border border-slate-700 px-2 py-1 text-xs font-bold text-slate-400 hover:border-slate-500 disabled:opacity-30"
                                        title="نقل لأسفل">▼</button>
                                    <button type="button" @click="removeNote(idx)"
                                        class="rounded border border-red-500/40 px-2 py-1 text-xs font-bold text-red-400 hover:bg-red-500/10">حذف</button>
                                </div>
                            </div>

                            <div x-show="editing" x-cloak class="space-y-2 mt-2">
                                <textarea x-model="draft" rows="3" maxlength="2000"
                                    class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white focus:border-emerald-500 focus:outline-none"></textarea>
                                <div class="flex justify-end gap-2">
                                    <button type="button"
                                        @click="if (draft.trim()) { notes[idx].text = draft.trim(); editing = false; }"
                                        class="rounded-lg bg-emerald-500 px-3 py-1.5 text-xs font-bold text-slate-950 hover:bg-emerald-400">
                                        حفظ التعديل
                                    </button>
                                    <button type="button" @click="editing = false"
                                        class="rounded-lg bg-slate-700 px-3 py-1.5 text-xs font-bold text-white hover:bg-slate-600">
                                        إلغاء
                                    </button>
                                </div>
                            </div>
                        </li>
                    </template>
                </ul>

                <p x-show="!notes.length" class="py-6 text-center text-sm italic text-slate-500">
                    لم تُضَف أي ملاحظة لهذه الصورة بعد.
                </p>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-800 bg-slate-950/60 px-6 py-4">
            <p class="text-xs" :class="statusClass" x-text="statusMessage"></p>
            <div class="flex items-center gap-2">
                <button type="button" @click="close()"
                    class="rounded-lg bg-slate-800 px-5 py-2 text-sm font-bold text-white hover:bg-slate-700">
                    إغلاق
                </button>
                <button type="button" @click="save()" :disabled="saving"
                    class="rounded-lg bg-emerald-500 px-6 py-2 text-sm font-bold text-slate-950 hover:bg-emerald-400 disabled:cursor-not-allowed disabled:opacity-60">
                    <span x-show="!saving">حفظ الملاحظات</span>
                    <span x-show="saving" x-cloak>...جاري الحفظ</span>
                </button>
            </div>
        </div>
    </div>
</dialog>

<script>
    // notesModal معرفة Synchronously قبل بدء Alpine.
    (function () {
        var categories = @json($noteCategories, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        var csrfToken = @json(csrf_token());

        function getDialog() { return document.getElementById('notes-modal-dialog'); }

        window.notesModal = function () {
            return {
                saving: false,
                statusMessage: '',
                statusClass: 'text-slate-400',
                categories: categories,
                selectedCategoryId: 0,
                selectedTemplateId: '',
                manualText: '',
                notes: [],
                photoId: null,
                areaName: '',
                saveUrl: '',

                openWith: function (detail) {
                    this.photoId = detail.photoId;
                    this.areaName = detail.areaName || '';
                    this.saveUrl = detail.saveUrl;
                    this.notes = Array.isArray(detail.initialNotes)
                        ? detail.initialNotes.map(function (n) {
                            if (typeof n === 'string') {
                                return { text: n, category_id: null };
                            }
                            return {
                                text: String((n && n.text) || '').trim(),
                                category_id: (n && n.category_id) ? Number(n.category_id) : null
                            };
                        }).filter(function (n) { return n.text; })
                        : [];
                    this.selectedCategoryId = this.categories.length ? this.categories[0].id : 0;
                    this.selectedTemplateId = '';
                    this.manualText = '';
                    this.statusMessage = '';
                    this.statusClass = 'text-slate-400';
                    var dlg = getDialog();
                    if (dlg && typeof dlg.showModal === 'function') {
                        dlg.showModal();
                    }
                },

                close: function () {
                    var dlg = getDialog();
                    if (dlg && dlg.open) dlg.close();
                },

                currentTemplates: function () {
                    var self = this;
                    var cat = this.categories.find(function (c) { return c.id === self.selectedCategoryId; });
                    return cat ? (cat.templates || []) : [];
                },

                formatCatLabel: function (cat) {
                    if (!cat) return '';
                    var name = String(cat.name || '').trim();
                    var stripped = name.replace(/^([\p{Extended_Pictographic}\p{Emoji_Presentation}\p{So}]+\s*)?(\d+\s*[.\-:)]\s*)/u, function (m, em) {
                        return em || '';
                    }).trim();
                    var order = cat.sort_order || 0;
                    return order + '. ' + stripped;
                },

                categoryLabel: function (catId) {
                    if (!catId) return 'التصنيف: أخرى';
                    var cat = this.categories.find(function (c) { return Number(c.id) === Number(catId); });
                    return 'التصنيف: ' + (cat ? String(cat.name || '').trim() : 'أخرى');
                },

                applyLocation: function (text) {
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
                    out = out.replace(/[\.\،,\s]+$/u, '');
                    return out + ' في ' + area + '.';
                },

                addFromTemplate: function () {
                    if (!this.selectedTemplateId) return;
                    var self = this;
                    var tpl = this.currentTemplates().find(function (t) {
                        return String(t.id) === String(self.selectedTemplateId);
                    });
                    if (!tpl) return;
                    var finalText = this.applyLocation(tpl.text);
                    if (this.notes.some(function (n) { return n.text === finalText; })) {
                        this.statusMessage = 'هذه الملاحظة مضافة بالفعل.';
                        this.statusClass = 'text-amber-400';
                        return;
                    }
                    this.notes.push({
                        text: finalText,
                        category_id: this.selectedCategoryId > 0 ? this.selectedCategoryId : null
                    });
                    this.selectedTemplateId = '';
                    this.statusMessage = '';
                },

                addManual: function () {
                    var t = (this.manualText || '').trim();
                    if (!t) return;
                    this.notes.push({
                        text: t,
                        category_id: this.selectedCategoryId > 0 ? this.selectedCategoryId : null
                    });
                    this.manualText = '';
                },

                removeNote: function (idx) { this.notes.splice(idx, 1); },

                moveUp: function (idx) {
                    if (idx <= 0) return;
                    var tmp = this.notes[idx - 1];
                    this.notes[idx - 1] = this.notes[idx];
                    this.notes[idx] = tmp;
                },

                moveDown: function (idx) {
                    if (idx >= this.notes.length - 1) return;
                    var tmp = this.notes[idx + 1];
                    this.notes[idx + 1] = this.notes[idx];
                    this.notes[idx] = tmp;
                },

                save: function () {
                    if (this.saving) return;
                    var self = this;
                    self.saving = true;
                    self.statusMessage = '';

                    var body = new FormData();
                    body.append('_token', csrfToken);
                    body.append('_method', 'PATCH');
                    self.notes.forEach(function (n, idx) {
                        body.append('notes[' + idx + '][text]', n.text || '');
                        if (n.category_id) {
                            body.append('notes[' + idx + '][category_id]', String(n.category_id));
                        }
                    });

                    fetch(self.saveUrl, {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                        body: body,
                    }).then(function (res) {
                        if (!res.ok) throw new Error('HTTP ' + res.status);
                        return res.json();
                    }).then(function (data) {
                        self.statusMessage = data.message || 'تم الحفظ.';
                        self.statusClass = 'text-emerald-400';

                        var badge = document.querySelector('[data-notes-count-' + self.photoId + ']');
                        if (badge) {
                            badge.textContent = data.count || 0;
                            if (data.count > 0) badge.classList.remove('hidden');
                            else badge.classList.add('hidden');
                        }

                        setTimeout(function () { self.close(); }, 700);
                    }).catch(function (err) {
                        console.error('notes save failed', err);
                        self.statusMessage = 'فشل الحفظ. حاول مرة أخرى.';
                        self.statusClass = 'text-red-400';
                    }).finally(function () { self.saving = false; });
                },
            };
        };

        // ربط زر "الملاحظات" بفتح المودال + كتابة الـ data في scope Alpine.
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-open-notes]');
            if (!btn) return;
            e.preventDefault();
            var initial = [];
            try { initial = JSON.parse(btn.dataset.initialNotes || '[]'); } catch (ex) { initial = []; }

            var dlg = getDialog();
            if (!dlg) return;
            // نستخدم Alpine.$data للوصول لـ scope وتعيين القيم ثم فتح dialog.
            var detail = {
                photoId: btn.dataset.photoId,
                areaName: btn.dataset.areaName || '',
                saveUrl: btn.dataset.saveUrl,
                initialNotes: initial,
            };
            if (window.Alpine && typeof window.Alpine.$data === 'function') {
                var scope = window.Alpine.$data(dlg);
                if (scope && typeof scope.openWith === 'function') {
                    scope.openWith(detail);
                    return;
                }
            }
            // مسار احتياطي: لو Alpine لم يحمّل، افتح المودال مباشرة.
            if (typeof dlg.showModal === 'function') dlg.showModal();
        });

        // إغلاق عند النقر على backdrop (الـ target يكون الـ dialog نفسه).
        document.addEventListener('click', function (e) {
            var dlg = getDialog();
            if (!dlg || !dlg.open) return;
            if (e.target === dlg) dlg.close();
        });
    })();
</script>

<style>
    /* Ensure overflow-wrap works on note text */
    .overflow-wrap-anywhere {
        overflow-wrap: anywhere;
        word-break: break-word;
    }
</style>
