@extends('layouts.admin')

@section('title', 'مكتبة الأقسام الجاهزة')

@section('content')
    <div class="mb-8">
        <nav class="flex mb-4" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3 rtl:space-x-reverse">
                <li class="inline-flex items-center">
                    <a href="{{ route('admin.houses.index') }}"
                        class="text-sm font-medium text-slate-400 hover:text-emerald-400 transition-colors">الرئيسية</a>
                </li>
                <li>
                    <span class="ms-1 text-sm font-medium text-emerald-500 md:ms-2">مكتبة الأقسام الجاهزة</span>
                </li>
            </ol>
        </nav>

        <h1 class="text-3xl font-black text-white tracking-tight">مكتبة الأقسام الجاهزة</h1>
        <p class="mt-2 max-w-3xl text-sm leading-relaxed text-slate-400">
            هنا تتحكم في الأقسام الجاهزة (مثل: المطبخ، الصالة، الفناء، دورة المياه...).
            هذه الأقسام تظهر في قائمة منسدلة عند إضافة قسم لأي منزل لتختار منها بدل كتابة الاسم يدوياً.
            الترتيب يتم بكتابة الرقم في خانة "الترتيب" — أصغر رقم يظهر في الأول.
        </p>
    </div>

    <div class="grid gap-8 lg:grid-cols-12">
        <aside class="lg:col-span-4">
            <div class="sticky top-8 rounded-2xl border border-slate-800 bg-slate-900/40 p-6">
                <h2 class="text-xl font-bold text-white mb-2">إضافة قسم جاهز</h2>
                <p class="text-sm text-slate-400 mb-6">أضِف اسم قسم جديد ليظهر لك في dropdown إضافة قسم لأي منزل.</p>

                <form method="post" action="{{ route('admin.section-templates.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label for="sec_name" class="block text-sm font-medium text-slate-300 mb-2">اسم القسم</label>
                        <input id="sec_name" name="name" type="text" required maxlength="255"
                            class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white placeholder:text-slate-600 focus:border-emerald-500 focus:outline-none focus:ring-4 focus:ring-emerald-500/10 transition-all"
                            placeholder="مثال: غرفة النوم 4، غرفة المكتب..." />
                    </div>
                    <button type="submit"
                        class="w-full rounded-xl bg-emerald-500 py-3 font-bold text-slate-950 transition hover:bg-emerald-400">
                        إضافة القسم
                    </button>
                </form>

                <div class="mt-6 rounded-xl border border-slate-800 bg-slate-950/50 p-4 text-xs leading-relaxed text-slate-400">
                    <p class="mb-2 font-bold text-slate-300">ملاحظات:</p>
                    <ul class="list-inside list-disc space-y-1">
                        <li>اسم القسم لازم يكون فريد.</li>
                        <li>حذف قسم من المكتبة <strong class="text-amber-400">لا يحذف</strong> الأقسام التي أضيفت لمنازل
                            بهذا الاسم.</li>
                        <li>غيّر الرقم في خانة "الترتيب" واضغط "حفظ" — الباقي يُعاد ترتيبه تلقائياً.</li>
                    </ul>
                </div>
            </div>
        </aside>

        <section class="lg:col-span-8">
            <div class="rounded-2xl border border-slate-800 bg-slate-900/40 p-6">
                <h2 class="text-xl font-bold text-white mb-4">
                    الأقسام المتاحة (<span class="text-emerald-400">{{ $sections->count() }}</span>)
                </h2>

                @if ($sections->isEmpty())
                    <p class="py-12 text-center text-sm italic text-slate-500">لا توجد أقسام جاهزة. أضف أول قسم من النموذج
                        على اليمين.</p>
                @else
                    <ul class="space-y-3">
                        @foreach ($sections as $section)
                            <li id="section-{{ $section->id }}"
                                class="rounded-xl border border-slate-800 bg-slate-950/50 p-4 scroll-mt-24"
                                x-data="{ editing: false, name: @js($section->name) }">

                                <div class="flex flex-wrap items-center justify-between gap-3" x-show="!editing">
                                    <div class="flex items-center gap-3">
                                        <span
                                            class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-500/20 text-sm font-bold text-emerald-300">
                                            {{ $section->sort_order }}
                                        </span>
                                        <span class="text-base font-semibold text-white">{{ $section->name }}</span>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-3">
                                        <form method="post"
                                            action="{{ route('admin.section-templates.update', $section) }}"
                                            class="flex items-center gap-2">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="name" value="{{ $section->name }}">
                                            <label for="order-{{ $section->id }}" class="text-xs text-slate-400">الترتيب</label>
                                            <input id="order-{{ $section->id }}" type="number" name="sort_order"
                                                min="1" max="{{ max($sections->count(), 1) }}"
                                                value="{{ $section->sort_order }}"
                                                class="w-20 rounded-lg border border-slate-700 bg-slate-950 px-2 py-1 text-center text-sm text-white focus:border-emerald-500 focus:outline-none">
                                            <button type="submit"
                                                class="rounded-lg bg-emerald-500/90 px-3 py-1.5 text-xs font-bold text-slate-950 hover:bg-emerald-400">
                                                حفظ
                                            </button>
                                        </form>

                                        <button type="button" @click="editing = true"
                                            class="rounded-lg border border-slate-600 px-3 py-1.5 text-xs font-bold text-slate-300 hover:border-emerald-500 hover:text-emerald-400">
                                            تعديل الاسم
                                        </button>

                                        <form method="post"
                                            action="{{ route('admin.section-templates.destroy', $section) }}"
                                            class="inline"
                                            onsubmit="return confirm('حذف هذا القسم من المكتبة؟');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                class="rounded-lg border border-red-500/40 px-3 py-1.5 text-xs font-bold text-red-400 hover:bg-red-500/10">
                                                حذف
                                            </button>
                                        </form>
                                    </div>
                                </div>

                                <div x-show="editing" x-cloak>
                                    <form method="post"
                                        action="{{ route('admin.section-templates.update', $section) }}"
                                        class="flex flex-col gap-3 sm:flex-row sm:items-center">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="sort_order" value="{{ $section->sort_order }}">
                                        <input type="text" name="name" x-model="name" required maxlength="255"
                                            class="flex-1 rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white focus:border-emerald-500 focus:outline-none">
                                        <div class="flex flex-nowrap gap-2">
                                            <button type="submit"
                                                class="rounded-lg bg-emerald-500 px-4 py-2 text-xs font-bold text-slate-950 hover:bg-emerald-400">حفظ</button>
                                            <button type="button" @click="editing = false; name = @js($section->name)"
                                                class="rounded-lg bg-slate-700 px-4 py-2 text-xs font-bold text-white hover:bg-slate-600">إلغاء</button>
                                        </div>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>
    </div>
@endsection
