@extends('layouts.admin')

@section('title', 'صيانة السيرفر')

@section('content')
    <div class="mx-auto max-w-3xl space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-white">صيانة السيرفر (بدون Terminal)</h1>
            <p class="mt-2 text-sm leading-relaxed text-slate-400">
                للاستخدام أثناء الرفع على الاستضافة فقط. بعد الانتهاء ضع
                <code class="rounded bg-slate-800 px-1 text-amber-300">SHOW_SERVER_MAINTENANCE=false</code>
                في ملف <code class="rounded bg-slate-800 px-1">.env</code> لتختفي هذه الصفحة من القائمة.
            </p>
        </div>

        @if (!empty($ran))
            <div class="rounded-xl border border-emerald-500/40 bg-emerald-500/10 p-4">
                <p class="text-sm font-bold text-emerald-300">تم تنفيذ الصيانة بنجاح.</p>
            </div>
        @endif

        <div class="rounded-2xl border border-slate-800 bg-slate-900/50 p-5">
            <h2 class="mb-3 text-sm font-bold text-slate-300">حالة السيرفر</h2>
            <dl class="grid gap-2 text-sm sm:grid-cols-2">
                @foreach ($checks as $label => $value)
                    <div class="flex justify-between gap-2 rounded-lg bg-slate-950/60 px-3 py-2">
                        <dt class="text-slate-500">{{ $label }}</dt>
                        <dd class="font-medium text-slate-200">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        <form method="post" action="{{ route('admin.system.maintenance.run') }}" class="rounded-2xl border border-amber-500/30 bg-amber-500/5 p-5">
            @csrf
            <h2 class="text-base font-bold text-amber-300">تشغيل الصيانة التلقائية</h2>
            <p class="mt-2 text-sm text-slate-400">
                يُنفّذ: تحديث قاعدة البيانات، مسح الكاش، إنشاء مجلدات التقارير والصور، والتحقق من أعمدة الكاش.
            </p>
            <button type="submit"
                class="mt-4 rounded-xl bg-amber-500 px-6 py-3 text-sm font-bold text-slate-950 hover:bg-amber-400">
                تشغيل الصيانة الآن
            </button>
        </form>

        <div class="rounded-2xl border border-slate-800 bg-slate-950/50 p-5 text-sm text-slate-400 space-y-2">
            <h2 class="font-bold text-white">خطوات الرفع على Namecheap</h2>
            <ol class="list-decimal list-inside space-y-1">
                <li>ارفع كل ملفات المشروع (بما فيها <code class="text-emerald-400">vendor</code>) عبر File Manager أو FTP.</li>
                <li>تأكد أن مجلد <code class="text-emerald-400">storage</code> و<code class="text-emerald-400">bootstrap/cache</code> قابل للكتابة (755 أو 775).</li>
                <li>افتح هذا الموقع وسجّل الدخول كمسؤول.</li>
                <li>اضغط «تشغيل الصيانة الآن» مرة واحدة.</li>
                <li>بعد كل رفع: افتح <a href="{{ route('admin.system.clear-cache') }}" class="text-emerald-400 hover:underline">مسح الكاش</a>.</li>
                <li>يمكنك أيضاً زيارة <a href="{{ route('admin.migrate-db') }}" class="text-emerald-400 hover:underline">تحديث قاعدة البيانات السريع</a> إن لزم.</li>
            </ol>
        </div>

        @if (!empty($log))
            <div class="rounded-2xl border border-slate-800 bg-black/40 p-4">
                <h2 class="mb-2 text-sm font-bold text-slate-300">سجل التنفيذ</h2>
                <pre class="max-h-96 overflow-auto text-xs leading-relaxed text-slate-300 whitespace-pre-wrap">{{ $log }}</pre>
            </div>
        @endif
    </div>
@endsection
