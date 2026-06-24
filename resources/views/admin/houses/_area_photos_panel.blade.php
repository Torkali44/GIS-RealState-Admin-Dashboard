@php
    $perPageOptions = [20, 40, 80, 120];
    $perPage = (int) ($perPage ?? request()->query('photos_per_page', session('photos_per_page', 40)));
    if (! in_array($perPage, $perPageOptions, true)) {
        $perPage = 40;
    }
    $panelUrl = route('admin.houses.areas.photos.panel', [$house, $area]);
    $scalarQuery = collect(request()->query())->filter(function ($v) {
        return is_scalar($v);
    })->all();
    $photosPaginator = $area->photos()
        ->orderBy('sort_order')
        ->orderBy('id')
        ->paginate($perPage, ['*'], 'area_'.$area->id.'_page');
    $photosPaginator->appends($scalarQuery);
    $photosPaginator->withPath(route('admin.houses.areas.photos.panel', [$house, $area], false));
    $useComposite = fn ($photo) => (bool) ($photo->composite_path || $photo->drive_composite_file_id);
@endphp

@if ($photosPaginator->isEmpty())
    <div class="py-12 text-center" data-area-photos data-panel-url="{{ $panelUrl }}" data-area-id="{{ $area->id }}">
        <p class="text-slate-500 italic">لا توجد صور في هذا القسم حتى الآن.</p>
    </div>
@else
    <div data-area-photos data-panel-url="{{ $panelUrl }}" data-area-id="{{ $area->id }}">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-800 bg-slate-900/40 px-4 py-3">
            <p class="text-xs text-slate-300">
                إجمالي الصور: <span class="font-bold text-emerald-400">{{ $photosPaginator->total() }}</span>
                | الصفحة {{ $photosPaginator->currentPage() }} من {{ $photosPaginator->lastPage() }}
            </p>
            <div class="flex flex-wrap items-center gap-2">
                @if ($photosPaginator->total() > 1)
                    <form method="post" action="{{ route('admin.houses.areas.photos.resort', [$house, $area]) }}" class="inline" data-resort-form data-photos-panel-url="{{ $panelUrl }}">
                        @csrf
                        <button type="submit"
                            class="rounded-lg border border-slate-700 bg-slate-950 px-3 py-1.5 text-xs font-bold text-slate-300 hover:border-emerald-500 hover:text-emerald-400 transition-colors">
                            ترتيب تلقائي بالاسم
                        </button>
                    </form>
                @endif

                <form method="GET" class="flex items-center gap-2 text-xs" data-photos-per-page-form>
                    @foreach ($scalarQuery as $key => $value)
                        @if (! str_starts_with((string) $key, 'area_') && $key !== 'photos_per_page')
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endif
                    @endforeach
                    <label for="photos_per_page_{{ $area->id }}" class="text-slate-400">صور لكل صفحة:</label>
                    <select id="photos_per_page_{{ $area->id }}" name="photos_per_page" data-photos-per-page-select
                        class="rounded border border-slate-700 bg-slate-950 px-2 py-1 text-white focus:border-emerald-500 focus:outline-none">
                        @foreach ($perPageOptions as $option)
                            <option value="{{ $option }}" @selected($perPage === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4" data-photo-grid>
            @foreach ($photosPaginator as $photo)
                @php
                    $composite = $useComposite($photo);
                    $imgRoute = \App\Support\PhotoImageUrl::make($house, $photo, $composite);
                    $thumbRoute = \App\Support\PhotoImageUrl::thumb($house, $photo, $composite, 420);
                    $photoNotesCount = count($photo->notesList());
                    $photoNotesForModal = method_exists($photo, 'notesEntries')
                        ? $photo->notesEntries()
                        : array_map(function ($text) {
                            return ['text' => $text, 'category_id' => null];
                        }, $photo->notesList());
                @endphp

                <div id="photo-{{ $photo->id }}"
                    class="group relative flex aspect-square items-center justify-center rounded-xl border border-slate-800 bg-slate-950 overflow-hidden shadow-lg scroll-mt-24">
                    <label class="absolute left-2 top-2 z-[1] rounded bg-black/50 p-1.5">
                        <input type="checkbox" class="h-4 w-4 accent-amber-500 photo-checkbox-{{ $area->id }}"
                            data-merge-select data-merge-src="{{ $imgRoute }}" data-photo-id="{{ $photo->id }}" />
                    </label>

                    <button type="button"
                        data-open-notes
                        data-photo-id="{{ $photo->id }}"
                        data-area-id="{{ $area->id }}"
                        data-area-name="{{ $area->name }}"
                        data-save-url="{{ route('admin.houses.photos.notes.update', [$house, $photo]) }}"
                        data-initial-notes='@json($photoNotesForModal, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)'
                        class="absolute right-2 top-2 z-[2] inline-flex items-center gap-1 rounded-lg bg-emerald-500/90 px-2 py-1 text-[11px] font-bold text-slate-950 shadow-lg hover:bg-emerald-400 transition-colors"
                        title="إدارة ملاحظات هذه الصورة">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span>الملاحظات</span>
                        <span data-notes-count-{{ $photo->id }}
                            class="rounded-full bg-slate-950/70 px-1.5 py-0.5 text-[10px] text-emerald-300 {{ $photoNotesCount === 0 ? 'hidden' : '' }}">
                            {{ $photoNotesCount }}
                        </span>
                    </button>

                    <img src="{{ $thumbRoute }}" alt=""
                        class="max-h-full max-w-full object-contain transition duration-500 group-hover:scale-105"
                        loading="lazy" decoding="async" fetchpriority="low">

                    <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-transparent to-transparent opacity-60 group-hover:opacity-90 transition-opacity"></div>

                    <div class="absolute inset-0 flex flex-col justify-between p-3 opacity-0 group-hover:opacity-100 transition-opacity duration-300 pt-12">
                        <div class="flex items-center">
                            <div class="flex gap-1">
                                <form method="post" action="{{ route('admin.houses.photos.move', [$house, $photo]) }}" data-photo-move-form>
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="direction" value="up">
                                    <button type="submit" class="p-1.5 rounded-lg bg-black/50 text-white hover:bg-emerald-600 transition-colors" title="نقل الصورة لأعلى">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" /></svg>
                                    </button>
                                </form>
                                <form method="post" action="{{ route('admin.houses.photos.move', [$house, $photo]) }}" data-photo-move-form>
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="direction" value="down">
                                    <button type="submit" class="p-1.5 rounded-lg bg-black/50 text-white hover:bg-emerald-600 transition-colors" title="نقل الصورة لأسفل">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                    </button>
                                </form>
                            </div>
                        </div>
                        <a href="{{ route('admin.houses.photos.edit', [$house, $photo, 'return' => request()->fullUrl().'#photo-'.$photo->id]) }}"
                            class="w-full rounded-lg bg-emerald-500 py-2 text-center text-xs font-bold text-slate-950 hover:bg-emerald-400">
                            {{ $photo->tip_x !== null ? 'تعديل السهم' : 'إضافة سهم' }}
                        </a>
                    </div>

                    @if ($photo->tip_x !== null || $photo->tip_y !== null || filled($photo->description) || filled($photo->composite_path) || ! empty($photo->annotations_json) || $photoNotesCount > 0)
                        <div class="pointer-events-none absolute bottom-2 right-2 z-[5] flex h-8 w-8 items-center justify-center rounded-full border-2 border-emerald-400/80 bg-emerald-500 text-slate-950 shadow-lg shadow-emerald-500/30" title="تم حفظ تعديلات على هذه الصورة">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        @if ($photosPaginator->hasPages())
            @php
                $currentPage = $photosPaginator->currentPage();
                $lastPage = $photosPaginator->lastPage();
                $pageStart = max(1, $currentPage - 2);
                $pageEnd = min($lastPage, $currentPage + 2);
            @endphp
            <nav class="mt-6 flex flex-wrap items-center justify-center gap-2" aria-label="تصفح الصور">
                @if ($photosPaginator->onFirstPage())
                    <span class="rounded-lg border border-slate-800 px-3 py-2 text-xs font-bold text-slate-600">السابق</span>
                @else
                    <a href="{{ $photosPaginator->previousPageUrl() }}" class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-bold text-slate-300 hover:border-emerald-500 hover:text-emerald-400">السابق</a>
                @endif
                @if ($pageStart > 1)
                    <a href="{{ $photosPaginator->url(1) }}" class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-bold text-slate-300 hover:border-emerald-500 hover:text-emerald-400">1</a>
                    @if ($pageStart > 2)<span class="px-2 text-slate-600">...</span>@endif
                @endif
                @for ($page = $pageStart; $page <= $pageEnd; $page++)
                    @if ($page === $currentPage)
                        <span class="rounded-lg bg-emerald-500 px-3 py-2 text-xs font-black text-slate-950">{{ $page }}</span>
                    @else
                        <a href="{{ $photosPaginator->url($page) }}" class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-bold text-slate-300 hover:border-emerald-500 hover:text-emerald-400">{{ $page }}</a>
                    @endif
                @endfor
                @if ($pageEnd < $lastPage)
                    @if ($pageEnd < $lastPage - 1)<span class="px-2 text-slate-600">...</span>@endif
                    <a href="{{ $photosPaginator->url($lastPage) }}" class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-bold text-slate-300 hover:border-emerald-500 hover:text-emerald-400">{{ $lastPage }}</a>
                @endif
                @if ($photosPaginator->hasMorePages())
                    <a href="{{ $photosPaginator->nextPageUrl() }}" class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-bold text-slate-300 hover:border-emerald-500 hover:text-emerald-400">التالي</a>
                @else
                    <span class="rounded-lg border border-slate-800 px-3 py-2 text-xs font-bold text-slate-600">التالي</span>
                @endif
            </nav>
        @endif
    </div>
@endif
