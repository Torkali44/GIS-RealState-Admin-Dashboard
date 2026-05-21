@extends('layouts.admin')

@section('title', 'منزل جديد')

@section('content')
    <a href="{{ route('admin.houses.index') }}" class="text-sm text-emerald-400 hover:underline">← العودة للقائمة</a>

    <h1 class="mt-4 text-2xl font-bold text-white">إضافة منزل جديد</h1>
    <p class="mt-1 text-slate-400">بعد الإنشاء ستضيف الأقسام (مثل: الحديقة، المطبخ) ثم ترفع الصور لكل قسم.</p>

    <form method="post" action="{{ route('admin.houses.store') }}" class="mt-8 max-w-xl space-y-5">
        @csrf
        <div>
            <label for="title" class="mb-1 block text-sm font-medium text-slate-300">رقم الطلب</label>
            <input
                id="title"
                name="title"
                type="text"
                required
                value="{{ old('title') }}"
                class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/40"
                placeholder="مثال: فيلا سكنية"
            />
        </div>
        <div>
            <label for="inspection_date" class="mb-1 block text-sm font-medium text-slate-300">تاريخ الفحص</label>
            <input
                id="inspection_date"
                name="inspection_date"
                type="date"
                value="{{ old('inspection_date') }}"
                class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/40"
            />
        </div>
        <div>
            <label for="client_name" class="mb-1 block text-sm font-medium text-slate-300">اسم العميل</label>
            <input
                id="client_name"
                name="client_name"
                type="text"
                value="{{ old('client_name') }}"
                class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/40"
                placeholder="اسم صاحب العقار"
            />
        </div>
        <div>
            <label for="address" class="mb-1 block text-sm font-medium text-slate-300">الموقع / العنوان</label>
            <input
                id="address"
                name="address"
                type="text"
                value="{{ old('address') }}"
                class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/40"
                placeholder="المنطقة، المجمع، الطريق..."
            />
        </div>
        <div>
            <label for="reference_code" class="mb-1 block text-sm font-medium text-slate-300">رمز مرجعي (اختياري)</label>
            <input
                id="reference_code"
                name="reference_code"
                type="text"
                value="{{ old('reference_code') }}"
                class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/40"
            />
        </div>
        <div>
            <label for="notes" class="mb-1 block text-sm font-medium text-slate-300">ملاحظات عامة (اختياري)</label>
            <textarea
                id="notes"
                name="notes"
                rows="3"
                class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/40"
            >{{ old('notes') }}</textarea>
        </div>
        <div class="flex items-center gap-4 pt-2">
            <button type="submit" class="rounded-lg bg-emerald-500 px-8 py-2.5 font-semibold text-slate-950 hover:bg-emerald-400">
                حفظ والمتابعة
            </button>
            <a href="{{ route('admin.houses.index') }}" class="rounded-lg bg-slate-800 px-6 py-2.5 font-semibold text-white hover:bg-slate-700">
                إلغاء
            </a>
        </div>
    </form>
@endsection
