@extends('layouts.admin')

@section('title', 'مكتبة الملاحظات الجاهزة')

@section('content')
    <div class="mb-8">
        <nav class="flex mb-4" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3 rtl:space-x-reverse">
                <li class="inline-flex items-center">
                    <a href="{{ route('admin.houses.index') }}"
                        class="text-sm font-medium text-slate-400 hover:text-emerald-400 transition-colors">الرئيسية</a>
                </li>
                <li>
                    <span class="ms-1 text-sm font-medium text-emerald-500 md:ms-2">مكتبة الملاحظات الجاهزة</span>
                </li>
            </ol>
        </nav>

        <h1 class="text-3xl font-black text-white tracking-tight">مكتبة الملاحظات الجاهزة</h1>
        <p class="mt-2 max-w-3xl text-sm leading-relaxed text-slate-400">
            هنا تتحكم في تصنيفات الأقسام (المطبخ، الصالة، دورة المياه...) والملاحظات الجاهزة لكل تصنيف.
            استعمل العبارة <code class="rounded bg-slate-800 px-1.5 py-0.5 text-emerald-400">(الموقع)</code>
            داخل نص الملاحظة وسيتم استبدالها تلقائياً باسم القسم عند الاختيار.
        </p>
    </div>

    <div class="grid gap-8 lg:grid-cols-12">
        <aside class="lg:col-span-4">
            <div class="sticky top-8 rounded-2xl border border-slate-800 bg-slate-900/40 p-6">
                <h2 class="text-xl font-bold text-white mb-2">إضافة تصنيف</h2>
                <p class="text-sm text-slate-400 mb-6">أنشئ تصنيفاً جديداً مثل: المطبخ، الفناء، السباكة...</p>

                <form method="post" action="{{ route('admin.note-categories.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label for="cat_name" class="block text-sm font-medium text-slate-300 mb-2">اسم التصنيف</label>
                        <input id="cat_name" name="name" type="text" required maxlength="255"
                            class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white placeholder:text-slate-600 focus:border-emerald-500 focus:outline-none focus:ring-4 focus:ring-emerald-500/10 transition-all"
                            placeholder="مثال: غرفة الغسيل" />
                    </div>
                    <button type="submit"
                        class="w-full rounded-xl bg-emerald-500 py-3 font-bold text-slate-950 transition hover:bg-emerald-400">
                        إضافة التصنيف
                    </button>
                </form>

                <div class="mt-6 rounded-xl border border-slate-800 bg-slate-950/50 p-4 text-xs leading-relaxed text-slate-400">
                    <p class="mb-2 font-bold text-slate-300">تنبيهات:</p>
                    <ul class="list-inside list-disc space-y-1">
                        <li>عند حذف تصنيف، تُحذف جميع ملاحظاته الجاهزة.</li>
                        <li>التصنيف لا يحذف الأقسام الموجودة لكن يفصلها من الملاحظات.</li>
                        <li>كلمة <code class="text-emerald-400">(الموقع)</code> تُستبدل باسم القسم عند الاستخدام.</li>
                    </ul>
                </div>
            </div>
        </aside>

        <div class="lg:col-span-8 space-y-6">
            @forelse ($categories as $category)
                <div id="cat-{{ $category->id }}" x-data="{ editingCat: false }"
                    class="rounded-2xl border border-slate-800 bg-slate-900/30 overflow-hidden scroll-mt-24">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-800 bg-slate-900/40 px-5 py-4">
                        <div class="flex items-center gap-3 flex-wrap">
                            <span
                                class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-500 font-bold">
                                {{ $category->sort_order }}
                            </span>

                            <h3 x-show="!editingCat" class="text-lg font-bold text-white">{{ $category->name }}</h3>
                            <span x-show="!editingCat"
                                class="rounded bg-slate-800 px-2 py-0.5 text-xs font-bold text-slate-400">
                                {{ $category->templates_count }} ملاحظة
                            </span>

                            <form x-show="editingCat" x-cloak method="post"
                                action="{{ route('admin.note-categories.update', $category) }}"
                                class="flex flex-wrap items-center gap-2">
                                @csrf
                                @method('PATCH')
                                <input type="text" name="name" value="{{ $category->name }}" required maxlength="255"
                                    class="rounded-lg border border-slate-700 bg-slate-950 px-3 py-1.5 text-sm text-white focus:border-emerald-500 focus:outline-none">
                                <input type="hidden" name="sort_order" value="{{ (int) $category->sort_order }}">
                                <button type="submit"
                                    class="rounded-lg bg-emerald-500 px-3 py-1.5 text-xs font-bold text-slate-950 hover:bg-emerald-400">حفظ</button>
                                <button type="button" @click="editingCat = false"
                                    class="rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-bold text-slate-300 hover:bg-slate-700">إلغاء</button>
                            </form>
                        </div>

                        <div class="flex items-center gap-2 flex-wrap">
                            {{-- نموذج تغيير الترتيب فقط --}}
                            <form method="post" action="{{ route('admin.note-categories.update', $category) }}"
                                class="flex items-center gap-1.5">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="name" value="{{ $category->name }}">
                                <label for="cat-order-{{ $category->id }}" class="text-xs text-slate-400">الترتيب:</label>
                                <input id="cat-order-{{ $category->id }}" type="number" name="sort_order"
                                    value="{{ (int) $category->sort_order }}" min="1"
                                    max="{{ max($categories->count(), 1) }}"
                                    class="w-16 rounded-lg border border-slate-700 bg-slate-950 px-2 py-1.5 text-center text-sm text-white focus:border-emerald-500 focus:outline-none">
                                <button type="submit"
                                    class="rounded-lg bg-emerald-500/90 px-3 py-1.5 text-xs font-bold text-slate-950 hover:bg-emerald-400">
                                    حفظ
                                </button>
                            </form>

                            <button type="button" @click="editingCat = !editingCat"
                                class="rounded-lg border border-slate-600 px-3 py-1.5 text-xs font-bold text-slate-300 hover:border-emerald-500 hover:text-emerald-400">
                                تعديل الاسم
                            </button>
                            <form id="del-cat-{{ $category->id }}" method="post"
                                action="{{ route('admin.note-categories.destroy', $category) }}">
                                @csrf
                                @method('DELETE')
                                <button type="button"
                                    onclick="confirmAction('حذف التصنيف وجميع ملاحظاته الجاهزة؟', function() { document.getElementById('del-cat-{{ $category->id }}').submit(); })"
                                    class="p-2 text-slate-500 hover:text-red-400 transition-colors" title="حذف التصنيف">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="p-5 space-y-4">
                        <form method="post" action="{{ route('admin.note-categories.templates.store', $category) }}"
                            class="flex flex-col gap-2 rounded-xl border border-dashed border-slate-700 bg-slate-950/40 p-3 sm:flex-row">
                            @csrf
                            <input type="text" name="text" required maxlength="2000"
                                placeholder="أدخل نص ملاحظة جديدة... استخدم (الموقع) لاستبدالها باسم القسم"
                                class="flex-1 rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white placeholder:text-slate-600 focus:border-emerald-500 focus:outline-none">
                            <button type="submit"
                                class="rounded-lg bg-emerald-500 px-4 py-2 text-sm font-bold text-slate-950 hover:bg-emerald-400 whitespace-nowrap">
                                + إضافة ملاحظة
                            </button>
                        </form>

                        @if ($category->templates->isEmpty())
                            <p class="py-6 text-center text-sm text-slate-500 italic">لا توجد ملاحظات جاهزة في هذا التصنيف.
                            </p>
                        @else
                            <ul class="space-y-2">
                                @foreach ($category->templates as $template)
                                    <li x-data="{ editing: false, text: @js($template->text) }"
                                        class="rounded-lg border border-slate-800 bg-slate-950/40 p-3">
                                        <div x-show="!editing" class="flex flex-wrap items-center justify-between gap-3">
                                            <span class="text-sm leading-relaxed text-slate-200">{{ $template->text }}</span>
                                            <div class="flex items-center gap-2">
                                                <button type="button" @click="editing = true"
                                                    class="rounded border border-slate-600 px-2 py-1 text-xs font-bold text-slate-300 hover:border-emerald-500 hover:text-emerald-400">تعديل</button>
                                                <form id="del-tpl-{{ $template->id }}" method="post"
                                                    action="{{ route('admin.note-categories.templates.destroy', [$category, $template]) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="button"
                                                        onclick="confirmAction('حذف هذه الملاحظة الجاهزة؟', function() { document.getElementById('del-tpl-{{ $template->id }}').submit(); })"
                                                        class="rounded border border-red-500/40 px-2 py-1 text-xs font-bold text-red-400 hover:bg-red-500/10">حذف</button>
                                                </form>
                                            </div>
                                        </div>

                                        <form x-show="editing" x-cloak method="post"
                                            action="{{ route('admin.note-categories.templates.update', [$category, $template]) }}"
                                            class="flex flex-wrap items-center gap-2">
                                            @csrf
                                            @method('PATCH')
                                            <input type="text" name="text" x-model="text" required maxlength="2000"
                                                class="flex-1 rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white focus:border-emerald-500 focus:outline-none">
                                            <input type="number" name="sort_order"
                                                value="{{ (int) $template->sort_order }}" min="1"
                                                class="w-16 rounded-lg border border-slate-700 bg-slate-950 px-2 py-2 text-sm text-white focus:border-emerald-500 focus:outline-none"
                                                title="ترتيب">
                                            <button type="submit"
                                                class="rounded-lg bg-emerald-500 px-3 py-2 text-xs font-bold text-slate-950 hover:bg-emerald-400">حفظ</button>
                                            <button type="button" @click="editing = false; text = @js($template->text)"
                                                class="rounded-lg bg-slate-800 px-3 py-2 text-xs font-bold text-slate-300 hover:bg-slate-700">إلغاء</button>
                                        </form>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            @empty
                <div class="rounded-3xl border border-dashed border-slate-800 p-16 text-center">
                    <h3 class="text-xl font-bold text-white">لا توجد تصنيفات بعد</h3>
                    <p class="mt-2 text-slate-500">ابدأ بإضافة أول تصنيف للملاحظات الجاهزة من القائمة الجانبية.</p>
                </div>
            @endforelse
        </div>
    </div>
@endsection
