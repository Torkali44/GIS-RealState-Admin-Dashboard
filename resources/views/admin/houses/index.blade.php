@extends('layouts.admin')

@section('title', 'المنازل')

@section('content')
    <div class="flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white">منازل المعاينة</h1>
            <p class="mt-1 text-slate-400">كل منزل يضم أقسامًا وصورًا مع تقرير PDF</p>
        </div>
        <a
            href="{{ route('admin.houses.create') }}"
            class="inline-flex items-center justify-center rounded-lg bg-emerald-500 px-5 py-2.5 text-sm font-semibold text-slate-950 hover:bg-emerald-400"
        >
            منزل جديد
        </a>
    </div>

    <div class="mt-8 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <form action="{{ route('admin.houses.index') }}" method="GET" class="relative w-full max-w-md">
            <input 
                type="text" 
                name="search" 
                value="{{ $search }}"
                placeholder="ابحث بالعنوان، اسم العميل، أو الموقع..."
                class="w-full rounded-lg border border-slate-800 bg-slate-900/60 py-2.5 pl-10 pr-4 text-sm text-white placeholder-slate-500 focus:border-emerald-500/50 focus:outline-none focus:ring-1 focus:ring-emerald-500/50"
            >
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            @if($search)
                <a href="{{ route('admin.houses.index') }}" class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-500 hover:text-white">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </a>
            @endif
        </form>
    </div>

    <div class="mt-4 overflow-x-auto rounded-xl border border-slate-800 bg-slate-900/40">
        <table class="w-full min-w-[900px] divide-y divide-slate-800 text-right text-sm">
            <thead class="bg-slate-900/80">
                <tr>
                    <th class="px-4 py-3 font-semibold text-slate-300"># ID</th>
                    <th class="px-4 py-3 font-semibold text-slate-300">رقم الطلب</th>
                    <th class="px-4 py-3 font-semibold text-slate-300">اسم العميل</th>
                    <th class="px-4 py-3 font-semibold text-slate-300">الموقع / العنوان</th>
                    <th class="px-4 py-3 font-semibold text-slate-300">الأقسام</th>
                    <th class="px-4 py-3 font-semibold text-slate-300">الصور</th>
                    <th class="px-4 py-3 font-semibold text-slate-300">المرجع</th>
                    <th class="px-4 py-3 font-semibold text-slate-300">الإجراءات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                @forelse ($houses as $house)
                    <tr class="hover:bg-slate-800/40">
                        <td class="px-4 py-3 text-slate-400">{{ $house->id }}</td>
                        <td class="px-4 py-3 font-medium text-white">{{ $house->title ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-300">{{ $house->client_name ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-300">{{ $house->address ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-300">{{ $house->inspection_areas_count }}</td>
                        <td class="px-4 py-3 text-slate-300">{{ $house->photos_count }}</td>
                        <td class="px-4 py-3 text-slate-400">{{ $house->reference_code ?? '—' }}</td>
                        <td class="px-4 py-3 text-left sm:text-right">
                            <div class="flex flex-wrap items-center gap-3">
                                <a href="{{ route('admin.houses.show', $house) }}" class="text-emerald-400 hover:text-emerald-300">فتح</a>
                                <a href="{{ route('admin.houses.edit', $house) }}" class="text-blue-400 hover:text-blue-300">تعديل</a>
                                <form id="delete-house-{{ $house->id }}" action="{{ route('admin.houses.destroy', $house) }}" method="POST" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="button" 
                                        onclick="confirmAction('هل أنت متأكد من حذف هذا المنزل وجميع بياناته؟', function() { document.getElementById('delete-house-{{ $house->id }}').submit(); })"
                                        class="text-red-400 hover:text-red-300">
                                        حذف
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-12 text-center text-slate-500">لا توجد نتائج بحث مطابقة.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $houses->links() }}
    </div>
@endsection
