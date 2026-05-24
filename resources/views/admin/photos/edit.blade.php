@extends('layouts.admin')

@section('title', 'تعديل صورة')

@section('content')
    <div class="mx-auto max-w-6xl space-y-6">
        <a href="{{ route('admin.houses.show', $house) }}" class="text-sm text-emerald-400 hover:underline">← العودة لـ
            {{ $house->title }}</a>

        <div>
            <h1 class="text-2xl font-bold text-white">تعليم الصورة</h1>
            <p class="mt-2 max-w-3xl text-sm leading-relaxed text-slate-400">
                النقطة الحمراء: رأس السهم — الأخضر: طول الذيل — الأزرق: الدوران.
                التكبير من <strong class="text-white">100%</strong> فما فوق فقط: يظهر جزء أكبر من الصورة داخل نفس الإطار (الباقي يُقص)، والعودة لـ 100% تعيد الصورة كاملة.
            </p>
        </div>

        <div id="annotate-root" class="w-full" data-annotate-root>
            <form method="post" action="{{ route('admin.houses.photos.update', [$house, $photo]) }}"
                enctype="multipart/form-data" class="space-y-8" data-annotate-form>
                @csrf
                @method('PATCH')
                <input type="hidden" name="tip_x" value="{{ old('tip_x', $photo->tip_x) }}" data-input-tip-x />
                <input type="hidden" name="tip_y" value="{{ old('tip_y', $photo->tip_y) }}" data-input-tip-y />
                <input type="hidden" name="annotations_json" value='@json($photo->annotations_json ?? [])'
                    data-input-annotations />
                <input type="hidden" name="clear_arrow" value="0" data-clear-flag />

                <div class="grid gap-6 lg:grid-cols-12 lg:items-start">
                    {{-- عمود الصورة --}}
                    <div class="min-w-0 lg:col-span-8" data-annotate-canvas-col>
                        <div class="rounded-2xl border border-slate-800 bg-slate-900/50 p-3 shadow-inner">
                            <div class="text-xs font-semibold text-slate-500 mb-2">معاينة</div>
                            <div
                                class="relative mx-auto flex max-w-full items-center justify-center overflow-hidden rounded-xl border border-emerald-500/40 bg-black/50"
                                data-editor-viewport
                                style="max-height: min(68vh, 640px); min-height: 200px;">
                                <div class="relative inline-block shrink-0 select-none will-change-transform" data-annotate-wrap>
                                    <img src="{{ \App\Support\PhotoImageUrl::make($house, $photo, (bool) ($photo->composite_path || $photo->drive_composite_file_id)) }}"
                                        alt="" class="relative z-0 block max-w-none cursor-crosshair rounded-lg shadow-lg"
                                        data-annotate-image draggable="false"
                                        onerror="this.alt='تعذر تحميل الصورة — حدّث الصفحة أو راجع Drive';" />
                                    <svg data-arrow-svg class="absolute left-0 top-0 z-[1] hidden h-full w-full"
                                        viewBox="0 0 1000 1000" preserveAspectRatio="none" aria-hidden="true">
                                        <line data-arrow-line class="hidden" />
                                        <path data-arrow-head d="" />
                                    </svg>
                                    <div data-drag-handle
                                        class="absolute z-[2] hidden h-7 w-7 -translate-x-1/2 -translate-y-1/2 cursor-grab touch-manipulation rounded-full border border-white/50 bg-red-500/35 shadow-sm ring-1 ring-red-500/20 active:cursor-grabbing"
                                        title="اسحب لتحريك رأس السهم"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- عمود الأدوات --}}
                    <aside class="lg:col-span-4">
                        <div class="sticky top-4 space-y-5 rounded-2xl border border-slate-800 bg-slate-900/60 p-5">
                            <h2 class="text-sm font-bold uppercase tracking-wide text-slate-400">أدوات التعليم</h2>

                            <div class="space-y-2">
                                <div class="flex items-center justify-between gap-2">
                                    <label for="zoom-range" class="text-xs font-medium text-slate-300">تكبير المعاينة</label>
                                    <span class="text-xs font-bold tabular-nums text-emerald-400" data-zoom-value>100%</span>
                                </div>
                                <input id="zoom-range" type="range" min="1" max="2.2" step="0.05" value="1"
                                    data-zoom-range class="w-full accent-emerald-500" />
                                <button type="button" data-zoom-reset
                                    class="w-full rounded-lg border border-slate-600 py-2 text-xs font-bold text-slate-200 hover:bg-slate-800">
                                    حجم طبيعي (100%)
                                </button>
                            </div>

                            <div class="border-t border-slate-800 pt-4 space-y-3">
                                <div>
                                    <label for="arrow-size" class="mb-1 block text-xs text-slate-400">حجم السهم</label>
                                    <input id="arrow-size" type="range" min="0.35" max="2.5" step="0.05" value="0.35"
                                        data-arrow-size class="w-full accent-emerald-500" />
                                </div>
                                <div>
                                    <label for="arrow-angle" class="mb-1 block text-xs text-slate-400">زاوية السهم</label>
                                    <input id="arrow-angle" type="range" min="-180" max="180" step="1" value="-45"
                                        data-arrow-angle class="w-full accent-sky-500" />
                                </div>
                            </div>

                            <div class="border-t border-slate-800 pt-4 space-y-3">
                                <div class="grid grid-cols-2 gap-2">
                                    <button type="button" data-add-arrow
                                        class="rounded-xl border border-emerald-500/40 bg-emerald-500/10 py-2.5 text-xs font-bold text-emerald-300 hover:bg-emerald-500/20">
                                        + سهم
                                    </button>
                                    <button type="button" data-remove-arrow
                                        class="rounded-xl border border-slate-600 py-2.5 text-xs font-bold text-slate-200 hover:bg-slate-800">
                                        حذف
                                    </button>
                                </div>
                                <div class="flex items-center gap-2">
                                    <label for="arrow-count" class="shrink-0 text-xs text-slate-400">العدد</label>
                                    <input id="arrow-count" type="number" min="0" max="20" value="0" data-arrow-count
                                        class="w-full rounded-lg border border-slate-600 bg-slate-950 px-2 py-2 text-sm text-white" />
                                </div>
                                <p class="text-xs leading-relaxed text-slate-500" data-arrow-info>عدد الأسهم: 0</p>
                            </div>
                        </div>
                    </aside>
                </div>

                <input type="file" name="composite" accept="image/jpeg" class="hidden" data-composite-input />

                @php
                    $areaName = (string) ($photo->inspectionArea->name ?? '');
                    $categoriesPayload = $allCategories
                        ->map(function ($c) {
                            return [
                                'id' => (int) $c->id,
                                'name' => (string) $c->name,
                                'sort_order' => (int) $c->sort_order,
                                'templates' => $c->templates
                                    ->map(function ($t) {
                                        return [
                                            'id' => (int) $t->id,
                                            'text' => (string) $t->text,
                                        ];
                                    })
                                    ->values()
                                    ->all(),
                            ];
                        })
                        ->values()
                        ->all();
                @endphp

                @include('admin.photos._notes_panel', [
                    'panelAreaName' => $areaName,
                    'panelCategories' => $categoriesPayload,
                    'panelInitialNotes' => $existingNotes,
                ])

                <div>
                    <label for="description" class="mb-1 block text-sm font-medium text-slate-300">
                        ملاحظات إضافية حرة (اختياري)
                    </label>
                    <textarea id="description" name="description" rows="3"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-white placeholder:text-slate-600 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/40"
                        placeholder="نص إضافي يضاف بعد الملاحظات الجاهزة في التقرير...">{{ old('description', $photo->description) }}</textarea>
                </div>

                <div class="space-y-4 border-t border-slate-800 pt-6">
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="submit"
                            class="inline-flex items-center gap-2 rounded-xl bg-emerald-500 px-8 py-3 font-bold text-slate-950 transition hover:bg-emerald-400">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <span>حفظ التعديلات</span>
                        </button>

                        <a href="{{ route('admin.houses.show', $house) }}"
                            class="rounded-xl border border-slate-700 bg-slate-800/60 px-6 py-3 font-bold text-white hover:bg-slate-800">
                            إلغاء والعودة
                        </a>

                        <button type="button" data-clear-arrow
                            class="inline-flex items-center gap-2 rounded-xl border border-red-500/40 px-5 py-3 text-sm font-bold text-red-400 hover:bg-red-500/10">
                            <span>إزالة كل الأسهم</span>
                        </button>
                    </div>

                    <div class="grid grid-cols-2 gap-3 border-t border-slate-800/80 pt-4 sm:max-w-md">
                        @if ($prevPhoto ?? null)
                            <button type="submit" name="redirect_to" value="{{ route('admin.houses.photos.edit', [$house, $prevPhoto]) }}"
                                class="flex items-center justify-center gap-2 rounded-xl border border-slate-600 bg-slate-800/90 py-3 text-center text-sm font-bold text-white shadow-sm transition hover:border-emerald-500/60 hover:bg-slate-800 hover:text-emerald-300">
                                <span class="text-lg leading-none text-emerald-400">‹</span>
                                <span>السابق</span>
                            </button>
                        @else
                            <span
                                class="flex cursor-not-allowed items-center justify-center gap-2 rounded-xl border border-slate-800/80 bg-slate-900/30 py-3 text-center text-sm font-bold text-slate-600">
                                <span class="text-lg leading-none opacity-40">‹</span>
                                <span>لا يوجد سابق</span>
                            </span>
                        @endif

                        @if ($nextPhoto ?? null)
                            <button type="submit" name="redirect_to" value="{{ route('admin.houses.photos.edit', [$house, $nextPhoto]) }}"
                                class="flex items-center justify-center gap-2 rounded-xl border border-slate-600 bg-slate-800/90 py-3 text-center text-sm font-bold text-white shadow-sm transition hover:border-emerald-500/60 hover:bg-slate-800 hover:text-emerald-300">
                                <span>التالي</span>
                                <span class="text-lg leading-none text-emerald-400">›</span>
                            </button>
                        @else
                            <span
                                class="flex cursor-not-allowed items-center justify-center gap-2 rounded-xl border border-slate-800/80 bg-slate-900/30 py-3 text-center text-sm font-bold text-slate-600">
                                <span>لا يوجد تالي</span>
                                <span class="text-lg leading-none opacity-40">›</span>
                            </span>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="{{ asset('js/inspection-annotate.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var root = document.getElementById('annotate-root');
            if (root && window.initInspectionAnnotate) {
                window.initInspectionAnnotate(root);
            } else if (root) {
                console.error('inspection-annotate.js لم يُحمّل');
            }
        });
    </script>
@endsection
