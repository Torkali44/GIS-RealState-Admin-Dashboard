@extends('layouts.admin')

@section('title', 'جاري إنشاء التقرير')

@section('content')
    <div class="mx-auto max-w-lg rounded-2xl border border-slate-700/80 bg-slate-900/90 p-8 text-center shadow-xl">
        <div class="mb-4 inline-flex h-14 w-14 items-center justify-center rounded-full bg-amber-500/20">
            <svg class="h-8 w-8 animate-spin text-amber-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
        </div>

        <h1 class="text-xl font-bold text-white">جاري إنشاء تقرير PDF</h1>
        <p class="mt-2 text-slate-300">{{ $house->title }}</p>
        <p class="mt-4 text-2xl font-semibold text-amber-400">{{ $done }} / {{ $total }}</p>
        <p class="mt-2 text-sm text-slate-400">لا تغلق هذه الصفحة — قد يستغرق عدة دقائق للمنازل الكبيرة</p>

        <meta http-equiv="refresh" content="1;url={{ $nextUrl }}">
        <p class="mt-6">
            <a href="{{ $nextUrl }}" class="text-sm text-emerald-400 underline">متابعة يدوياً إن توقف التحميل</a>
        </p>
    </div>
@endsection
