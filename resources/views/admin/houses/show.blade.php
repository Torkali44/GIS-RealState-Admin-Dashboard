@extends('layouts.admin')

@section('title', $house->title)

@section('content')
    <div class="mb-8 flex flex-col gap-6 md:flex-row md:items-start md:justify-between">
        <div>
            <nav class="flex mb-4" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3 rtl:space-x-reverse">
                    <li class="inline-flex items-center">
                        <a href="{{ route('admin.houses.index') }}"
                            class="text-sm font-medium text-slate-400 hover:text-emerald-400 transition-colors">
                            <svg class="w-4 h-4 mr-2.5 rtl:ml-2.5" fill="currentColor" viewBox="0 0 20 20">
                                <path
                                    d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z">
                                </path>
                            </svg>
                            جميع المنازل
                        </a>
                    </li>
                    <li>
                        <div class="flex items-center">
                            <svg class="w-6 h-6 text-slate-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
                                    clip-rule="evenodd"></path>
                            </svg>
                            <span class="ms-1 text-sm font-medium text-emerald-500 md:ms-2">تفاصيل العقار</span>
                        </div>
                    </li>
                </ol>
            </nav>
            <h1 class="text-3xl font-black text-white tracking-tight">{{ $house->title }}</h1>

            <div class="mt-3 flex flex-wrap gap-4">
                @if ($house->client_name)
                    <div class="flex items-center gap-2 text-slate-300">
                        <svg class="h-4 w-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <span class="text-sm font-medium">العميل: {{ $house->client_name }}</span>
                    </div>
                @endif
                @if ($house->address)
                    <div class="flex items-center gap-2 text-slate-300">
                        <svg class="h-4 w-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span class="text-sm font-medium">{{ $house->address }}</span>
                    </div>
                @endif
                @if ($house->reference_code)
                    <div
                        class="inline-flex items-center rounded-md bg-slate-800 px-2 py-1 text-xs font-bold text-slate-400 uppercase tracking-wider">
                        REF: {{ $house->reference_code }}
                    </div>
                @endif
                <div
                    class="inline-flex items-center rounded-md bg-slate-800 px-2 py-1 text-xs font-bold text-slate-400 uppercase tracking-wider">
                    ID: #{{ $house->id }}
                </div>
            </div>
        </div>

        <div class="flex w-full flex-wrap items-stretch gap-3 md:w-auto md:justify-end">
             <a
                    href="{{ route('admin.houses.report', ['house' => $house, 'inline' => 1, 'v' => $reportV]) }}"
                    data-pdf-export
                    target="_blank"
                    class="group flex items-center gap-2 rounded-xl bg-emerald-500/20 px-4 py-3 font-bold text-emerald-400 transition hover:bg-emerald-500 hover:text-slate-950"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <span>عرض التقرير</span>
                </a> 

            <a href="{{ route('admin.houses.report', ['house' => $house, 'v' => $reportV]) }}"
                data-pdf-export
                class="group flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-500 px-6 py-3 font-bold text-slate-950 transition hover:bg-emerald-400 hover:shadow-[0_0_20px_rgba(16,185,129,0.2)] sm:w-auto">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
                <span>تحميل PDF</span>
            </a>

            <a href="{{ route('admin.houses.report', ['house' => $house, 'format' => 'word']) }}"
                class="group flex w-full items-center justify-center gap-2 rounded-xl border border-emerald-500/40 bg-slate-900 px-6 py-3 font-bold text-emerald-300 transition hover:bg-emerald-500 hover:text-slate-950 sm:w-auto">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
                <span>تحميل Word</span>
            </a>

            @if ($driveConfigured && $house->drive_folder_id)
                <a href="https://drive.google.com/drive/folders/{{ $house->drive_folder_id }}"
                    target="_blank"
                    class="group flex w-full items-center justify-center gap-2 rounded-xl border border-blue-500/40 bg-slate-900 px-4 py-3 font-bold text-blue-300 transition hover:bg-blue-600 hover:text-white sm:w-auto">
                    <span>فتح فولدر Drive</span>
                </a>
            @endif

            @if ($house->drive_pdf_id)
                <a href="https://drive.google.com/file/d/{{ $house->drive_pdf_id }}/view"
                    target="_blank"
                    class="group flex w-full items-center justify-center gap-2 rounded-xl border border-blue-500/40 bg-slate-900 px-4 py-3 font-bold text-blue-300 transition hover:bg-blue-600 hover:text-white sm:w-auto">
                    <span>التقرير PDF على Drive</span>
                </a>
            @endif
            @if ($house->drive_word_file_id)
                <a href="https://drive.google.com/file/d/{{ $house->drive_word_file_id }}/view"
                    target="_blank"
                    class="group flex w-full items-center justify-center gap-2 rounded-xl border border-blue-500/40 bg-slate-900 px-4 py-3 font-bold text-blue-300 transition hover:bg-blue-600 hover:text-white sm:w-auto">
                    <span>التقرير الكتابي (.doc) على Drive</span>
                </a>
            @endif
            
                @if ($house->inspectionAreas->isNotEmpty())
                <form id="finalize-report-form" method="post" action="{{ route('admin.houses.report.finalize', $house) }}" class="w-full sm:w-auto">
                    @csrf
                    <button type="button" onclick="confirmAction('تحذير: هذا الإجراء سيقوم بحفظ التقرير نهائياً وحذف كافة الصور المرفقة بالأقسام لتوفير المساحة. هل أنت متأكد؟', function() { document.getElementById('finalize-report-form').submit(); })" class="group flex w-full items-center justify-center gap-2 rounded-xl bg-amber-500 px-4 py-3 font-bold text-slate-950 transition hover:bg-amber-400 hover:shadow-[0_0_20px_rgba(245,158,11,0.2)] sm:w-auto">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        <span>حفظ التقرير نهائياً وحذف الصور</span>
                    </button>
                </form>
                @endif 

            <form id="delete-house-form" method="post" action="{{ route('admin.houses.destroy', $house) }}" class="w-full sm:w-auto">
                @csrf
                @method('DELETE')
                <button type="button"
                    onclick="confirmAction('هل أنت متأكد من حذف هذا المنزل وجميع بياناته؟', function() { document.getElementById('delete-house-form').submit(); })"
                    class="flex h-12 w-full items-center justify-center rounded-xl border border-red-500/30 text-red-500 transition hover:bg-red-500 hover:text-white sm:w-12">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </button>
            </form>
        </div>
    </div>

    @if ($house->notes)
        <div class="mb-10 rounded-2xl border border-slate-800 bg-slate-900/40 p-5">
            <h3 class="text-xs font-bold uppercase tracking-widest text-slate-500 mb-2">ملاحظات عامة</h3>
            <p class="text-slate-300 leading-relaxed">{{ $house->notes }}</p>
        </div>
    @endif

    @if ($driveOAuthNeeded ?? false)
        <div class="mb-6 rounded-xl border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">
            <strong>ربط Google Drive مطلوب:</strong> الفولدرات قد تظهر لكن الصور والتقارير لا تُرفع بدون ربط حسابك (Service Account لا يرفع ملفات على Gmail).
            <a href="{{ route('admin.google-drive.connect') }}" class="mr-3 inline-flex rounded-lg bg-amber-500 px-4 py-2 font-bold text-slate-950 hover:bg-amber-400">ربط Google Drive</a>
            <span class="text-amber-200/80">راجع <code class="rounded bg-slate-900 px-1.5 py-0.5">GOOGLE_DRIVE_SETUP.md</code></span>
        </div>
    @elseif ($driveConfigured)
        <div class="mb-6 rounded-xl border border-blue-500/30 bg-blue-500/10 px-4 py-3 text-sm text-blue-100">
            <strong>Google Drive:</strong> التخزين الأساسي على Drive. السيرفر يحتفظ بكاش مؤقت للعرض والأسهم فقط (يُحذف من <code class="rounded bg-slate-900 px-1">public</code> بعد الرفع).
            @if ($house->drive_folder_id)
                <a href="https://drive.google.com/drive/folders/{{ $house->drive_folder_id }}" target="_blank" class="mr-2 underline text-blue-300">فولدر هذا المنزل</a>
            @endif
            @if (($pendingDrivePhotos ?? 0) > 0)
                <form method="post" action="{{ route('admin.houses.drive.sync', $house) }}" class="inline mr-2">
                    @csrf
                    <button type="submit" class="rounded-lg bg-blue-600 px-3 py-1 text-xs font-bold text-white hover:bg-blue-500">
                        مزامنة {{ $pendingDrivePhotos }} صورة قديمة مع Drive
                    </button>
                </form>
            @endif
            <form method="post" action="{{ route('admin.houses.drive.repair-cache', $house) }}" class="inline mr-2">
                @csrf
                <button type="submit" class="rounded-lg bg-emerald-600 px-3 py-1 text-xs font-bold text-white hover:bg-emerald-500">
                    إصلاح عرض الصور (كاش محلي)
                </button>
            </form>
            <form method="post" action="{{ route('admin.google-drive.disconnect') }}" class="inline mr-2">
                @csrf
                <button type="submit" class="text-xs text-blue-300/70 underline hover:text-blue-200">فصل الحساب</button>
            </form>
        </div>
    @else
        <div class="mb-6 rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-200">
            Google Drive غير مفعّل — راجع <code class="rounded bg-slate-900 px-1.5 py-0.5">GOOGLE_DRIVE_SETUP.md</code>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        <!-- Sidebar: Add Area -->
        <div class="lg:col-span-4">
            <div class="sticky top-8 rounded-2xl border border-slate-800 bg-slate-900/40 p-6">
                <h2 class="text-xl font-bold text-white mb-4">إضافة قسم جديد</h2>
                <p class="text-sm text-slate-400 mb-6">قم بتقسيم المنزل إلى أماكن (مثل: المطبخ، الصالة، الدور الأول) لتنظيم
                    الصور.</p>

                <form method="post" action="{{ route('admin.houses.areas.store', $house) }}" class="space-y-4"
                    x-data="{ mode: 'select', selected: '', custom: '' }">
                    @csrf

                    <div class="flex gap-2 rounded-xl border border-slate-800 bg-slate-950/50 p-1 text-xs">
                        <button type="button" @click="mode = 'select'"
                            :class="mode === 'select' ? 'bg-emerald-500 text-slate-950' : 'text-slate-300 hover:text-white'"
                            class="flex-1 rounded-lg px-3 py-1.5 font-bold transition">من المكتبة</button>
                        <button type="button" @click="mode = 'custom'"
                            :class="mode === 'custom' ? 'bg-emerald-500 text-slate-950' : 'text-slate-300 hover:text-white'"
                            class="flex-1 rounded-lg px-3 py-1.5 font-bold transition">يدوي</button>
                    </div>

                    <div x-show="mode === 'select'">
                        <label for="area_select" class="block text-sm font-medium text-slate-300 mb-2">اختر قسماً جاهزاً</label>
                        @if ($sectionTemplates->isEmpty())
                            <div class="rounded-xl border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-xs text-amber-300">
                                لا توجد أقسام جاهزة. أضف أقساماً من
                                <a href="{{ route('admin.section-templates.index') }}" class="font-bold underline">مكتبة الأقسام الجاهزة</a>
                                أو أدخل اسماً يدوياً.
                            </div>
                        @else
                            <select id="area_select" x-model="selected"
                                class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white focus:border-emerald-500 focus:outline-none focus:ring-4 focus:ring-emerald-500/10 transition-all">
                                <option value="">— اختر —</option>
                                @foreach ($sectionTemplates as $tpl)
                                    <option value="{{ $tpl->name }}">{{ $tpl->name }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>

                    <div x-show="mode === 'custom'" x-cloak>
                        <label for="area_custom" class="block text-sm font-medium text-slate-300 mb-2">أدخل اسم القسم يدوياً</label>
                        <input id="area_custom" type="text" x-model="custom" maxlength="255"
                            class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white placeholder:text-slate-600 focus:border-emerald-500 focus:outline-none focus:ring-4 focus:ring-emerald-500/10 transition-all"
                            placeholder="مثال: غرفة الضيوف..." />
                    </div>

                    <input type="hidden" name="name"
                        :value="mode === 'select' ? selected : custom">

                    <button type="submit"
                        :disabled="(mode === 'select' && !selected) || (mode === 'custom' && !custom.trim())"
                        class="w-full rounded-xl bg-emerald-500 py-3 font-bold text-slate-950 transition hover:bg-emerald-400 disabled:cursor-not-allowed disabled:opacity-50">
                        إضافة القسم
                    </button>
                </form>
            </div>
        </div>

        <!-- Main Content: Areas and Photos -->
        <div class="lg:col-span-8 space-y-8">
            @forelse ($house->inspectionAreas as $area)
                {{-- ===== Section Title: Centered + Sticky ===== --}}
                @php
                    $areaNameAlpine = json_encode((string) $area->name, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
                @endphp
                <div id="area-{{ $area->id }}" class="scroll-mt-6">
                    {{-- Prominent centered section name --}}
                    <div class="sticky top-[73px] z-20 -mx-4 sm:-mx-6 px-4 sm:px-6 py-4 mb-2 bg-slate-950/95 backdrop-blur-md border-b border-slate-800/60">
                        <div class="flex flex-col items-center gap-2">
                            <div class="flex items-center gap-3">
                                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-500/15 text-emerald-400 font-black text-lg border border-emerald-500/20">
                                    {{ $loop->iteration }}
                                </span>
                                <div x-data="{ editing: false, name: {{ $areaNameAlpine }} }" class="flex flex-wrap items-center gap-3">
                                    <h3 x-show="!editing" class="text-2xl md:text-3xl font-black text-white tracking-tight">{{ $area->name }}</h3>
                                    <button type="button" x-show="!editing" @click="editing = true"
                                        class="rounded-lg border border-slate-600 bg-slate-800/80 px-2.5 py-1 text-xs font-bold text-slate-300 hover:border-emerald-500 hover:text-emerald-400">
                                        تعديل
                                    </button>
                                    <form x-show="editing" method="post" x-cloak
                                        action="{{ route('admin.houses.areas.update', [$house, $area]) }}"
                                        class="flex flex-wrap items-center gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <input type="text" name="name" x-model="name"
                                            class="rounded-lg border border-slate-700 bg-slate-950 px-2 py-1 text-sm text-white focus:border-emerald-500 focus:outline-none">
                                        <button type="submit" class="text-xs font-bold text-emerald-500 hover:underline">حفظ</button>
                                        <button type="button" @click="editing = false; name = {{ $areaNameAlpine }}"
                                            class="text-xs font-bold text-slate-500 hover:text-slate-300">إلغاء</button>
                                    </form>
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center justify-center gap-2 mt-1">
                                <form method="post" action="{{ route('admin.houses.areas.update', [$house, $area]) }}" class="flex flex-wrap items-center gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="name" value="{{ $area->name }}">
                                    <label for="sort-order-{{ $area->id }}" class="text-xs text-slate-400">رقم القسم</label>
                                    <input id="sort-order-{{ $area->id }}" type="number" name="sort_order" min="1"
                                        value="{{ (int) $area->sort_order }}"
                                        class="w-16 rounded border border-slate-700 bg-slate-950 px-2 py-1 text-xs text-white focus:border-emerald-500 focus:outline-none">
                                    <button type="submit" class="rounded border border-slate-700 px-2 py-1 text-xs text-slate-300 hover:border-emerald-500 hover:text-emerald-400">
                                        تحديث
                                    </button>
                                </form>
                                <form id="delete-area-form-{{ $area->id }}" method="post"
                                    action="{{ route('admin.houses.areas.destroy', [$house, $area]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button"
                                        onclick="confirmAction('حذف هذا القسم بجميع صوره؟', function() { document.getElementById('delete-area-form-{{ $area->id }}').submit(); })"
                                        class="p-2 text-slate-500 hover:text-red-400 transition-colors" title="حذف القسم">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                <div class="rounded-2xl border border-slate-800 bg-slate-900/20 overflow-hidden">

                    <div class="p-6">
                        <!-- Upload Form -->
                        <form method="post" action="{{ route('admin.houses.areas.photos.store', [$house, $area]) }}"
                            enctype="multipart/form-data"
                            class="mb-8 p-6 rounded-xl border-2 border-dashed border-slate-800 hover:border-emerald-500/50 transition-colors group"
                            data-upload-form
                            data-photos-panel-url="{{ route('admin.houses.areas.photos.panel', [$house, $area]) }}">
                            @csrf
                            <div class="flex flex-col items-center justify-center text-center">
                                <div
                                    class="mb-4 rounded-full bg-slate-800 p-4 text-slate-400 group-hover:text-emerald-500 transition-colors">
                                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <label class="cursor-pointer">
                                    <span class="text-base font-bold text-white">اختر الصور أو اسحبها هنا</span>
                                    <p class="mt-1 text-sm text-slate-500">PNG, JPG, WebP حتى 1000 صورة</p>
                                    <input type="file" name="photos[]" multiple accept="image/*" required class="hidden"
                                        data-upload-input />
                                </label>
                                <div class="mt-4 flex flex-wrap justify-center gap-2" data-file-preview></div>
                                <button type="submit"
                                    class="mt-6 rounded-xl bg-slate-800 px-8 py-2.5 text-sm font-bold text-white hover:bg-slate-700 transition-colors">
                                    حفظ الصور ورفعها
                                </button>
                            </div>
                        </form>

                         <form
                                    method="post"
                                    action="{{ route('admin.houses.areas.photos.merge', [$house, $area]) }}"
                                    enctype="multipart/form-data"
                                    class="mb-8 rounded-xl border border-amber-500/30 bg-amber-500/5 p-4"
                                    data-merge-form
                                    data-photos-panel-url="{{ route('admin.houses.areas.photos.panel', [$house, $area]) }}"
                                    data-merge-max-height="{{ config('upload.merge_max_height', 900) }}"
                                    data-merge-quality="{{ config('upload.merge_jpeg_quality', 0.85) }}"
                                >
                                    @csrf
                                    @if ($errors->has('merge'))
                                        <p class="mb-2 rounded-lg border border-red-500/40 bg-red-500/10 px-3 py-2 text-xs font-bold text-red-300">
                                            {{ $errors->first('merge') }}
                                        </p>
                                    @endif
                                    <input type="file" name="merged_image" accept="image/jpeg" class="hidden" data-merged-image-input />
                                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                        <div>
                                            <h4 class="text-sm font-bold text-amber-300">التعامل مع الصور المحددة</h4>
                                            <p class="text-xs text-slate-400">حدد صورتين أو أكثر من الشبكة للدمج، أو حدد عدة صور لحذفها معاً.</p>
                                        </div>
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <button type="button" onclick="selectAllPhotos({{ $area->id }})" class="rounded-lg border border-slate-600 px-4 py-2 text-sm font-bold text-slate-300 hover:bg-slate-800">
                                                تحديد الكل
                                            </button>
                                            <button type="button" data-build-merge class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-bold text-slate-950 hover:bg-amber-400">
                                                دمج
                                            </button>
                                            <button type="button" onclick="bulkDeletePhotos({{ $area->id }})" class="rounded-lg bg-red-500 px-4 py-2 text-sm font-bold text-white hover:bg-red-400">
                                                حذف
                                            </button>
                                        </div>
                                    </div>

                                    <div class="mt-3">
                                        <label class="mb-1 block text-xs text-slate-400">وصف للصورة المدمجة (اختياري)</label>
                                        <textarea name="description" rows="2" class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white"></textarea>
                                    </div>
                                    <div class="mt-3 hidden rounded-lg border border-slate-700 p-2" data-merge-preview-wrap>
                                        <img data-merge-preview class="mx-auto max-h-48 rounded" alt="" />
                                    </div>
                                </form> 

                        <!-- Moved bulk delete form outside of merge form -->
                        <form id="bulk-delete-form-{{ $area->id }}" method="POST"
                            action="{{ route('admin.houses.areas.photos.bulk_destroy', [$house, $area]) }}" class="hidden">
                            @csrf
                            @method('DELETE')
                        </form>

                        @include('admin.houses._area_photos_panel', ['house' => $house, 'area' => $area])

                    </div>
                </div>
                </div> {{-- end area wrapper --}}
                
            @empty
                <div class="rounded-3xl border border-dashed border-slate-800 p-16 text-center">
                    <div
                        class="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-slate-900/50 text-slate-600">
                        <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-white">لا توجد أقسام بعد</h3>
                    <p class="mt-2 text-slate-500">ابدأ بإضافة أول قسم (مكان) للعقار من خلال القائمة الجانبية.</p>
                </div>
            @endforelse
        </div>
    </div>

    @include('admin.houses._notes_modal', ['noteCategories' => $noteCategories])

    <style>
        #pdf-unannotated-dialog:not([open]) { display: none !important; }
        #pdf-unannotated-dialog {
            margin: auto;
            padding: 0;
            border: 0;
            background: transparent;
            width: min(96vw, 42rem);
            max-width: 42rem;
        }
        #pdf-unannotated-dialog::backdrop {
            background: rgba(2, 6, 23, 0.8);
        }
        #pdf-unannotated-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        @media (min-width: 480px) {
            #pdf-unannotated-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }
        #pdf-unannotated-grid .pdf-unannotated-thumb {
            width: 100%;
            aspect-ratio: 4 / 3;
            min-height: 5.5rem;
            overflow: hidden;
            background: rgba(0, 0, 0, 0.45);
        }
        #pdf-unannotated-grid .pdf-unannotated-thumb img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
    </style>
    <dialog id="pdf-unannotated-dialog">
        <div class="overflow-hidden rounded-2xl border border-amber-500/40 bg-slate-900 text-white shadow-2xl">
            <div class="border-b border-slate-800 px-4 py-3 sm:px-5 sm:py-4">
                <h3 class="text-base font-bold text-amber-300 sm:text-lg">صور بدون تعليم</h3>
                <p class="mt-1 text-xs leading-relaxed text-slate-400 sm:text-sm">
                    حدّد ما تريد حذفه، أو تابع التقرير مع بقاء الصور كما هي.
                </p>
            </div>
            <div class="flex items-center justify-between gap-2 border-b border-slate-800/80 px-4 py-2.5 sm:px-5">
                <label class="flex cursor-pointer items-center gap-2 text-xs font-bold text-slate-300 sm:text-sm">
                    <input type="checkbox" id="pdf-unannotated-select-all" class="h-4 w-4 accent-amber-500" checked />
                    تحديد الكل
                </label>
                <span id="pdf-unannotated-count" class="text-xs text-slate-500"></span>
            </div>
            <div id="pdf-unannotated-grid" class="grid max-h-[min(65vh,32rem)] gap-3 overflow-y-auto p-4 sm:gap-4 sm:p-5"></div>
            <div class="flex flex-col gap-2 border-t border-slate-800 p-4 sm:flex-row sm:flex-wrap sm:justify-end sm:p-5">
                <button type="button" id="pdf-unannotated-delete"
                    class="w-full rounded-xl bg-red-500/90 px-4 py-2.5 text-sm font-bold text-white hover:bg-red-400 sm:w-auto sm:min-w-[12rem]">
                    حذف وتوليد التقرير
                </button>
                <button type="button" id="pdf-unannotated-continue"
                    class="w-full rounded-xl bg-emerald-500 px-4 py-2.5 text-sm font-bold text-slate-950 hover:bg-emerald-400 sm:w-auto sm:min-w-[11rem]">
                    متابعة التقرير
                </button>
                <button type="button" id="pdf-unannotated-cancel"
                    class="w-full rounded-xl bg-slate-800 px-4 py-2 text-sm font-bold text-slate-400 hover:bg-slate-700 sm:w-auto">
                    إلغاء
                </button>
            </div>
        </div>
    </dialog>

    <form id="pdf-unannotated-delete-form" method="post" action="{{ route('admin.houses.photos.unannotated.destroy', $house) }}" class="hidden">
        @csrf
        @method('DELETE')
    </form>

    @push('scripts')
        @php
            $inspectionUploadConfig = [
                'maxDim' => (int) config('upload.client_max_dimension', 1400),
                'jpegQuality' => (float) config('upload.client_jpeg_quality', 0.8),
                'skipBelowBytes' => (int) config('upload.client_skip_process_below_bytes', 400000),
                'chunkSize' => (int) config('upload.chunk_size', 20),
                'parallelRequests' => (int) config('upload.parallel_requests', 2),
                'processConcurrency' => (int) config('upload.client_process_concurrency', 10),
                'maxRetries' => 3,
                'bulkThreshold' => (int) config('upload.bulk_threshold', 40),
                'bulkChunkSize' => (int) config('upload.bulk_chunk_size', 25),
                'bulkParallelRequests' => (int) config('upload.bulk_parallel_requests', 3),
                'bulkSkipClientProcess' => (bool) config('upload.bulk_skip_client_process', true),
            ];
        @endphp
        <script>
            window.INSPECTION_UPLOAD_CONFIG = @json($inspectionUploadConfig);
        </script>
        <script src="{{ asset('js/inspection-panel.js') }}"></script>
        <script src="{{ asset('js/inspection-upload.js') }}"></script>
        <script src="{{ asset('js/inspection-merge.js') }}"></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                var hash = window.location.hash;
                if (hash && hash.indexOf('#photo-') === 0) {
                    var target = document.querySelector(hash);
                    if (target) {
                        setTimeout(function () {
                            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            target.classList.add('ring-4', 'ring-amber-400/80', 'ring-offset-2', 'ring-offset-slate-950');
                            setTimeout(function () {
                                target.classList.remove('ring-4', 'ring-amber-400/80', 'ring-offset-2', 'ring-offset-slate-950');
                            }, 4500);
                        }, 150);
                    }
                }

                var pdfPendingUrl = null;
                var pdfPendingTarget = null;
                var pdfDialog = document.getElementById('pdf-unannotated-dialog');
                var pdfGrid = document.getElementById('pdf-unannotated-grid');
                var pdfSelectAll = document.getElementById('pdf-unannotated-select-all');
                var pdfCountLabel = document.getElementById('pdf-unannotated-count');
                var pdfDeleteForm = document.getElementById('pdf-unannotated-delete-form');
                var unannotatedUrl = @json(route('admin.houses.report.unannotated', $house));

                function pdfGoToReport() {
                    if (!pdfPendingUrl) return;
                    if (pdfPendingTarget === '_blank') {
                        window.open(pdfPendingUrl, '_blank');
                    } else {
                        window.location.href = pdfPendingUrl;
                    }
                }

                function pdfDeletePhotosAndReport(photoIds) {
                    if (!pdfDeleteForm || !pdfPendingUrl || !photoIds.length) return;

                    var tokenInput = pdfDeleteForm.querySelector('input[name="_token"]');
                    var csrf = tokenInput ? tokenInput.value : '';
                    var exportUrl = pdfPendingUrl;
                    var exportTarget = pdfPendingTarget;
                    var pdfDelete = document.getElementById('pdf-unannotated-delete');
                    var pdfContinueBtn = document.getElementById('pdf-unannotated-continue');

                    if (pdfDialog) pdfDialog.close();
                    if (pdfDelete) {
                        pdfDelete.disabled = true;
                        pdfDelete.textContent = 'جاري الحذف وتوليد التقرير...';
                    }
                    if (pdfContinueBtn) pdfContinueBtn.disabled = true;

                    var fd = new FormData();
                    fd.append('_token', csrf);
                    fd.append('_method', 'DELETE');
                    photoIds.forEach(function (id) {
                        fd.append('photo_ids[]', String(id));
                    });

                    fetch(pdfDeleteForm.action, {
                        method: 'POST',
                        body: fd,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                        credentials: 'same-origin',
                    })
                        .then(function (res) {
                            if (!res.ok) throw new Error('delete failed');
                            return res.json();
                        })
                        .then(function () {
                            pdfPendingUrl = exportUrl;
                            pdfPendingTarget = exportTarget;
                            pdfGoToReport();
                        })
                        .catch(function () {
                            if (pdfDelete) {
                                pdfDelete.disabled = false;
                                pdfDelete.textContent = 'حذف وتوليد التقرير';
                            }
                            if (pdfContinueBtn) pdfContinueBtn.disabled = false;
                            window.location.href = exportUrl;
                        });
                }

                function pdfUpdateCountLabel() {
                    if (!pdfGrid || !pdfCountLabel) return;
                    var boxes = pdfGrid.querySelectorAll('[data-pdf-photo-check]');
                    var checked = pdfGrid.querySelectorAll('[data-pdf-photo-check]:checked').length;
                    pdfCountLabel.textContent = checked + ' / ' + boxes.length + ' محدد';
                    if (pdfSelectAll && boxes.length) {
                        pdfSelectAll.checked = checked === boxes.length;
                        pdfSelectAll.indeterminate = checked > 0 && checked < boxes.length;
                    }
                }

                function openPdfUnannotatedDialog(data, exportUrl, exportTarget) {
                    pdfPendingUrl = exportUrl;
                    pdfPendingTarget = exportTarget || null;
                    if (!pdfGrid || !pdfDialog) {
                        pdfGoToReport();
                        return;
                    }
                    pdfGrid.innerHTML = '';
                    (data.photos || []).forEach(function (p) {
                        var cell = document.createElement('label');
                        cell.className = 'relative block cursor-pointer overflow-hidden rounded-xl border border-slate-700 bg-slate-950 shadow-md transition hover:border-amber-500/50';
                        cell.title = (p.area_name || '') + ' #' + p.id;

                        var check = document.createElement('input');
                        check.type = 'checkbox';
                        check.setAttribute('data-pdf-photo-check', '');
                        check.value = String(p.id);
                        check.className = 'absolute left-2 top-2 z-10 h-5 w-5 accent-amber-500';
                        check.checked = true;

                        var thumbWrap = document.createElement('div');
                        thumbWrap.className = 'pdf-unannotated-thumb';
                        var img = document.createElement('img');
                        img.src = p.thumb_url || '';
                        img.alt = '';
                        img.loading = 'lazy';
                        thumbWrap.appendChild(img);

                        cell.appendChild(check);
                        cell.appendChild(thumbWrap);

                        var areaLabel = (p.area_name || '').trim();
                        if (areaLabel) {
                            var caption = document.createElement('p');
                            caption.className = 'truncate px-2 py-1.5 text-[10px] text-slate-400 sm:text-xs';
                            caption.textContent = areaLabel;
                            cell.appendChild(caption);
                        }

                        check.addEventListener('change', pdfUpdateCountLabel);
                        pdfGrid.appendChild(cell);
                    });
                    pdfUpdateCountLabel();
                    if (typeof pdfDialog.showModal === 'function') {
                        pdfDialog.showModal();
                    } else if (confirm('هناك ' + data.count + ' صورة بدون تعليم. متابعة التصدير؟')) {
                        window.location.href = exportUrl;
                    }
                }

                if (pdfSelectAll) {
                    pdfSelectAll.addEventListener('change', function () {
                        if (!pdfGrid) return;
                        pdfGrid.querySelectorAll('[data-pdf-photo-check]').forEach(function (cb) {
                            cb.checked = pdfSelectAll.checked;
                        });
                        pdfUpdateCountLabel();
                    });
                }

                document.querySelectorAll('[data-pdf-export]').forEach(function (link) {
                    link.addEventListener('click', function (e) {
                        e.preventDefault();
                        var exportUrl = link.getAttribute('href');
                        fetch(unannotatedUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                            .then(function (res) { return res.json(); })
                            .then(function (data) {
                                if (!data.count) {
                                    if (link.hasAttribute('data-pdf-export') && link.getAttribute('target') === '_blank') {
                                        window.open(exportUrl, '_blank');
                                    } else {
                                        window.location.href = exportUrl;
                                    }
                                    return;
                                }
                                openPdfUnannotatedDialog(data, exportUrl, link.getAttribute('target'));
                            })
                            .catch(function () {
                                pdfPendingUrl = exportUrl;
                                pdfPendingTarget = link.getAttribute('target');
                                pdfGoToReport();
                            });
                    });
                });

                var pdfCancel = document.getElementById('pdf-unannotated-cancel');
                var pdfContinue = document.getElementById('pdf-unannotated-continue');
                var pdfDelete = document.getElementById('pdf-unannotated-delete');
                if (pdfCancel && pdfDialog) {
                    pdfCancel.addEventListener('click', function () {
                        pdfDialog.close();
                        pdfPendingUrl = null;
                        pdfPendingTarget = null;
                    });
                }
                if (pdfContinue) {
                    pdfContinue.addEventListener('click', function () {
                        pdfDialog.close();
                        pdfGoToReport();
                    });
                }
                if (pdfDelete) {
                    pdfDelete.addEventListener('click', function () {
                        if (!pdfGrid || !pdfPendingUrl) return;
                        var selected = Array.from(pdfGrid.querySelectorAll('[data-pdf-photo-check]:checked'));
                        var ids = selected.map(function (cb) { return cb.value; });
                        if (!ids.length) {
                            ids = Array.from(pdfGrid.querySelectorAll('[data-pdf-photo-check]')).map(function (cb) {
                                return cb.value;
                            });
                        }
                        pdfDeletePhotosAndReport(ids);
                    });
                }

                document.querySelectorAll('[data-upload-form]').forEach(form => {
                    if (window.initInspectionUpload) {
                        window.initInspectionUpload(form);
                    }
                });
            });

            function selectAllPhotos(areaId) {
                const checkboxes = document.querySelectorAll('.photo-checkbox-' + areaId);
                const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                checkboxes.forEach(cb => cb.checked = !allChecked);
            }

            function bulkDeletePhotos(areaId) {
                const checkboxes = document.querySelectorAll('.photo-checkbox-' + areaId + ':checked');
                if (checkboxes.length === 0) {
                    showAlert('الرجاء تحديد صورة واحدة على الأقل.');
                    return;
                }

                confirmAction('هل أنت متأكد من حذف ' + checkboxes.length + ' صورة/صور؟', function () {
                    const form = document.getElementById('bulk-delete-form-' + areaId);
                    checkboxes.forEach(cb => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'photo_ids[]';
                        input.value = cb.dataset.photoId;
                        form.appendChild(input);
                    });
                    form.submit();
                });
            }
        </script>
    @endpush
@endsection
