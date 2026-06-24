@extends('layouts.admin')

@section('title', 'مسح الكاش')

@section('content')
    <div class="mx-auto max-w-2xl space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-white">مسح الكاش</h1>
            <p class="mt-2 text-sm text-slate-400">
                يُنفَّذ تلقائياً عند فتح هذه الصفحة — استخدمها بعد رفع أي تعديل على السيرفر.
            </p>
        </div>

        <div class="rounded-xl border border-emerald-500/40 bg-emerald-500/10 p-4">
            <p class="text-sm font-bold text-emerald-300">تم مسح الكاش بنجاح.</p>
            @if (!empty($clearedAt))
                <p class="mt-1 text-xs text-emerald-200/80">الوقت: {{ $clearedAt }}</p>
            @endif
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900/50 p-5 text-sm text-slate-400 space-y-3">
            <h2 class="font-bold text-white">الرابط الدائم (بعد تسجيل الدخول كمسؤول)</h2>
            <p class="break-all rounded-lg bg-slate-950 px-3 py-2 font-mono text-xs text-emerald-400" dir="ltr">
                {{ route('admin.system.clear-cache') }}
            </p>
            <p class="text-xs">
                احفظ هذا الرابط — افتحه بعد كل رفع ملفات أو تعديل <code class="text-amber-300">.env</code>.
            </p>
            @if (config('app.deploy_cache_token'))
                <p class="text-xs text-slate-500">
                    بدون تسجيل دخول (اختياري): أضف
                    <code class="text-amber-300">?token=YOUR_TOKEN</code>
                    — القيمة من <code class="text-amber-300">DEPLOY_CACHE_TOKEN</code> في .env
                </p>
            @endif
        </div>

        @if (!empty($log))
            <div class="rounded-2xl border border-slate-800 bg-black/40 p-4">
                <h2 class="mb-2 text-sm font-bold text-slate-300">سجل التنفيذ</h2>
                <pre class="max-h-80 overflow-auto text-xs leading-relaxed text-slate-300 whitespace-pre-wrap" dir="ltr">{{ $log }}</pre>
            </div>
        @endif

        <div class="flex flex-wrap gap-3">
            <a href="{{ route('admin.houses.index') }}"
                class="rounded-xl bg-emerald-500 px-5 py-2.5 text-sm font-bold text-slate-950 hover:bg-emerald-400">
                العودة للمنازل
            </a>
            <a href="{{ route('admin.system.clear-cache') }}"
                class="rounded-xl border border-slate-700 px-5 py-2.5 text-sm font-bold text-slate-300 hover:border-emerald-500 hover:text-emerald-400">
                إعادة المسح
            </a>
        </div>
    </div>
@endsection
