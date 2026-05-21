@extends('layouts.admin')

@section('title', 'تعديل بيانات المنزل')

@section('content')
    <a href="{{ route('admin.houses.index') }}" class="text-sm text-emerald-400 hover:underline">← العودة للقائمة</a>

    <h1 class="mt-4 text-2xl font-bold text-white">تعديل بيانات المنزل</h1>
    <p class="mt-1 text-slate-400">تحديث المعلومات الأساسية لهذا العقار.</p>

    <form method="post" action="{{ route('admin.houses.update', $house) }}" class="mt-8 max-w-xl space-y-5">
        @csrf
        @method('PUT')
        <div>
            <label for="title" class="mb-1 block text-sm font-medium text-slate-300">رقم الطلب</label>
            <input
                id="title"
                name="title"
                type="text"
                required
                value="{{ old('title', $house->title) }}"
                class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/40"
            />
        </div>
        <div>
            <label for="inspection_date" class="mb-1 block text-sm font-medium text-slate-300">تاريخ الفحص</label>
            <input
                id="inspection_date"
                name="inspection_date"
                type="date"
                value="{{ old('inspection_date', optional($house->inspection_date)->format('Y-m-d')) }}"
                class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/40"
            />
        </div>
        <div>
            <label for="client_name" class="mb-1 block text-sm font-medium text-slate-300">اسم العميل</label>
            <input
                id="client_name"
                name="client_name"
                type="text"
                value="{{ old('client_name', $house->client_name) }}"
                class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/40"
            />
        </div>
        <div>
            <label for="address" class="mb-1 block text-sm font-medium text-slate-300">الموقع / العنوان</label>
            <input
                id="address"
                name="address"
                type="text"
                value="{{ old('address', $house->address) }}"
                class="w-full rounded-lg border border-slate-700 bg-slate-950 px-3 py-2 text-white focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/40"
            />
        </div>
        <div>
            <label for="reference_code" class="mb-1 block text-sm font-medium text-slate-300">رمز مرجعي (اختياري)</label>
            <input
                id="reference_code"
                name="reference_code"
                type="text"
                value="{{ old('reference_code', $house->reference_code) }}"
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
            >{{ old('notes', $house->notes) }}</textarea>
        </div>
        <div class="flex items-center gap-4 pt-2">
            <button type="submit" class="rounded-lg bg-emerald-500 px-8 py-2.5 font-semibold text-slate-950 hover:bg-emerald-400">
                حفظ التغييرات
            </button>
            <a href="{{ route('admin.houses.index') }}" class="rounded-lg bg-slate-800 px-6 py-2.5 font-semibold text-white hover:bg-slate-700">
                إلغاء
            </a>
        </div>
    </form>
@endsection
