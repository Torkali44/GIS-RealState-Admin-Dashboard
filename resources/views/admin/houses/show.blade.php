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
                            data-upload-form>
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
                                >
                                    @csrf
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

                        @php
                            $perPageOptions = [20, 40, 80, 120];
                            $perPage = (int) request()->query('photos_per_page', 40);
                            if (! in_array($perPage, $perPageOptions, true)) {
                                $perPage = 40;
                            }
                            // تجنب أخطاء "Array to string conversion" وروابط معطوبة عند وجود معاملات مصفوفة في الرابط
                            $scalarQuery = collect(request()->query())->filter(function ($v) {
                                return is_scalar($v);
                            })->all();
                            $photosPaginator = $area->photos()
                                ->orderBy('sort_order')
                                ->orderBy('id')
                                ->paginate($perPage, ['*'], 'area_'.$area->id.'_page');
                            $photosPaginator->appends($scalarQuery);
                        @endphp

                        @if ($photosPaginator->isEmpty())
                            <div class="py-12 text-center">
                                <p class="text-slate-500 italic">لا توجد صور في هذا القسم حتى الآن.</p>
                            </div>
                        @else
                            <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-800 bg-slate-900/40 px-4 py-3">
                                <p class="text-xs text-slate-300">
                                    إجمالي الصور: <span class="font-bold text-emerald-400">{{ $photosPaginator->total() }}</span>
                                    | الصفحة {{ $photosPaginator->currentPage() }} من {{ $photosPaginator->lastPage() }}
                                </p>
                                <div class="flex flex-wrap items-center gap-2">
                                    

                                    <form method="GET" class="flex items-center gap-2 text-xs">
                                        @foreach ($scalarQuery as $key => $value)
                                            @if (! str_starts_with((string) $key, 'area_') && $key !== 'photos_per_page')
                                                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                            @endif
                                        @endforeach
                                        <label for="photos_per_page_{{ $area->id }}" class="text-slate-400">صور لكل صفحة:</label>
                                        <select id="photos_per_page_{{ $area->id }}" name="photos_per_page" onchange="this.form.submit()"
                                            class="rounded border border-slate-700 bg-slate-950 px-2 py-1 text-white focus:border-emerald-500 focus:outline-none">
                                            @foreach ($perPageOptions as $option)
                                                <option value="{{ $option }}" @selected($perPage === $option)>{{ $option }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                                @foreach ($photosPaginator as $photo)
                                    @php
                                        $imgRoute = \App\Support\PhotoImageUrl::make($house, $photo, (bool) ($photo->composite_path || $photo->drive_composite_file_id));
                                        $thumbRoute = $imgRoute;
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
                                            loading="lazy" decoding="async">

                                        <!-- Overlay info -->
                                        <div
                                            class="absolute inset-0 bg-gradient-to-t from-slate-950 via-transparent to-transparent opacity-60 group-hover:opacity-90 transition-opacity">
                                        </div>

                                        <div
                                            class="absolute inset-0 flex flex-col justify-between p-3 opacity-0 group-hover:opacity-100 transition-opacity duration-300 pt-12">
                                            <div class="flex items-center">
                                                <div class="flex gap-1">
                                                    <form method="post"
                                                        action="{{ route('admin.houses.photos.move', [$house, $photo]) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" name="direction" value="up">
                                                        <input type="hidden" name="redirect_to" value="{{ url()->current() }}#photo-{{ $photo->id }}">
                                                        <button type="submit"
                                                            class="p-1.5 rounded-lg bg-black/50 text-white hover:bg-emerald-600 transition-colors"
                                                            title="نقل الصورة لأعلى">
                                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                    d="M5 15l7-7 7 7" />
                                                            </svg>
                                                        </button>
                                                    </form>
                                                    <form method="post"
                                                        action="{{ route('admin.houses.photos.move', [$house, $photo]) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" name="direction" value="down">
                                                        <input type="hidden" name="redirect_to" value="{{ url()->current() }}#photo-{{ $photo->id }}">
                                                        <button type="submit"
                                                            class="p-1.5 rounded-lg bg-black/50 text-white hover:bg-emerald-600 transition-colors"
                                                            title="نقل الصورة لأسفل">
                                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                    d="M19 9l-7 7-7-7" />
                                                            </svg>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>

                                            <a href="{{ route('admin.houses.photos.edit', [$house, $photo]) }}"
                                                class="w-full rounded-lg bg-emerald-500 py-2 text-center text-xs font-bold text-slate-950 hover:bg-emerald-400">
                                                {{ $photo->tip_x !== null ? 'تعديل السهم' : 'إضافة سهم' }}
                                            </a>
                                        </div>

                                        @if ($photo->tip_x !== null || $photo->tip_y !== null || filled($photo->description) || filled($photo->composite_path) || ! empty($photo->annotations_json) || $photoNotesCount > 0)
                                            <div
                                                class="pointer-events-none absolute bottom-2 right-2 z-[5] flex h-8 w-8 items-center justify-center rounded-full border-2 border-emerald-400/80 bg-emerald-500 text-slate-950 shadow-lg shadow-emerald-500/30"
                                                title="تم حفظ تعديلات على هذه الصورة">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                                        d="M5 13l4 4L19 7" />
                                                </svg>
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
                                        @if ($pageStart > 2)
                                            <span class="px-2 text-slate-600">...</span>
                                        @endif
                                    @endif

                                    @for ($page = $pageStart; $page <= $pageEnd; $page++)
                                        @if ($page === $currentPage)
                                            <span class="rounded-lg bg-emerald-500 px-3 py-2 text-xs font-black text-slate-950">{{ $page }}</span>
                                        @else
                                            <a href="{{ $photosPaginator->url($page) }}" class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-bold text-slate-300 hover:border-emerald-500 hover:text-emerald-400">{{ $page }}</a>
                                        @endif
                                    @endfor

                                    @if ($pageEnd < $lastPage)
                                        @if ($pageEnd < $lastPage - 1)
                                            <span class="px-2 text-slate-600">...</span>
                                        @endif
                                        <a href="{{ $photosPaginator->url($lastPage) }}" class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-bold text-slate-300 hover:border-emerald-500 hover:text-emerald-400">{{ $lastPage }}</a>
                                    @endif

                                    @if ($photosPaginator->hasMorePages())
                                        <a href="{{ $photosPaginator->nextPageUrl() }}" class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-bold text-slate-300 hover:border-emerald-500 hover:text-emerald-400">التالي</a>
                                    @else
                                        <span class="rounded-lg border border-slate-800 px-3 py-2 text-xs font-bold text-slate-600">التالي</span>
                                    @endif
                                </nav>
                            @endif
                        @endif

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

    @push('scripts')
        <script src="{{ asset('js/inspection-upload.js') }}"></script>
        <script src="{{ asset('js/inspection-merge.js') }}"></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {

                document.querySelectorAll('[data-upload-form]').forEach(form => {
                    if (window.initInspectionUpload) {
                        window.initInspectionUpload(form);
                    }
                });
                document.querySelectorAll('[data-merge-form]').forEach(form => {
                    if (window.initInspectionMerge) {
                        window.initInspectionMerge(form);
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
