@extends('layouts.guest')

@section('title', 'تسجيل الدخول')

@section('content')
    <div class="relative flex min-h-screen items-center justify-center overflow-hidden px-4">
        <!-- Background Blur -->
        <div class="absolute -left-20 top-1/4 h-96 w-96 rounded-full bg-emerald-500/10 blur-[120px]"></div>
        <div class="absolute -right-20 bottom-0 h-96 w-96 rounded-full bg-blue-500/10 blur-[120px]"></div>

        <div class="glass-card relative z-10 w-full max-w-md rounded-3xl p-8 lg:p-10 shadow-2xl">
            <div class="mb-10 text-center">
                <div class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-500 shadow-xl shadow-emerald-500/30">
                    <svg class="h-10 w-10 text-slate-950" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 00-2 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>
                <h1 class="text-3xl font-black tracking-tight text-white">تسجيل الدخول</h1>
                <p class="mt-3 text-slate-400">لوحة التحكم الخاصة بالمشرفين فقط</p>
            </div>

            <form method="post" action="{{ route('login') }}" class="space-y-6">
                @csrf
                <div>
                    <label for="email" class="mb-2 block text-sm font-bold text-slate-300">البريد الإلكتروني</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        autocomplete="username"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950/50 px-4 py-3.5 text-white outline-none transition-all focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
                        placeholder="admin@example.com"
                    />
                    @error('email')
                        <p class="mt-2 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label for="password" class="block text-sm font-bold text-slate-300">كلمة المرور</label>
                    </div>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        required
                        autocomplete="current-password"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950/50 px-4 py-3.5 text-white outline-none transition-all focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10"
                        placeholder="••••••••"
                    />
                </div>

                <div class="flex items-center">
                    <input id="remember" type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-slate-700 bg-slate-950 text-emerald-500 focus:ring-0 focus:ring-offset-0" />
                    <label for="remember" class="mr-2 text-sm text-slate-400">تذكرني على هذا الجهاز</label>
                </div>

                <button
                    type="submit"
                    class="group relative w-full overflow-hidden rounded-xl bg-emerald-500 py-4 font-bold text-slate-950 shadow-lg shadow-emerald-500/20 transition-all hover:bg-emerald-400 hover:shadow-emerald-500/40 active:scale-[0.98]"
                >
                    <span class="relative z-10">دخول النظام</span>
                </button>
            </form>

            <div class="mt-10 border-t border-slate-800 pt-6 text-center">
                <a href="{{ route('home') }}" class="inline-flex items-center gap-2 text-sm font-medium text-slate-500 hover:text-emerald-400 transition-colors">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    <span>العودة للرئيسية</span>
                </a>
            </div>
        </div>
    </div>
@endsection

