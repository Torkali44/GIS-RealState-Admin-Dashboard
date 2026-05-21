@extends('layouts.guest')

@section('title', 'الرئيسية')

@section('content')
    <div class="relative min-h-screen overflow-hidden">
        <!-- Background Image with Overlay -->
        <div class="absolute inset-0 z-0">
            <img src="{{ asset('images/hero.jpg') }}" class="h-full w-full object-cover" alt="Property Inspection Hero">
            <div class="absolute inset-0 bg-gradient-to-l from-slate-950 via-slate-950/80 to-transparent"></div>
            <div class="absolute inset-0 bg-slate-950/40"></div>
        </div>

        <!-- Navigation -->
        <nav class="relative z-50 flex items-center justify-between px-6 py-8 lg:px-12">
            <div class="flex items-center gap-2">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl  shadow-lg shadow-emerald-500/30">
                   
                    <img src="{{ asset('images/company-logo2.png') }}" class="h-10 w-10 rounded-full bg-slate-900" alt="Logo">
                </div>
                <span class="text-xl font-bold tracking-tight text-white">  جي اي اس <span class="text-emerald-500">للمعاينة </span></span>
            </div>
            <div>
                <a href="{{ route('login') }}" class="rounded-full bg-white/10 px-6 py-2.5 text-sm font-semibold text-white backdrop-blur-md transition hover:bg-white/20">
                    دخول المسؤول
                </a>
            </div>
        </nav>

        <!-- Hero Content -->
        <div class="relative z-10 mx-auto flex min-h-[calc(100vh-100px)] max-w-7xl flex-col justify-center px-6 lg:px-12">
            <div class="max-w-3xl animate-in fade-in slide-in-from-right-10 duration-1000">
                <div class="inline-flex items-center gap-2 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-4 py-1.5 mb-6">
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                    </span>
                    <span class="text-xs font-bold uppercase tracking-widest text-emerald-400">الجيل القادم من معاينة العقارات</span>
                </div>
                
                <h1 class="text-5xl font-extrabold leading-[1.1] text-white sm:text-7xl">
                    حول <span class="gradient-text">معاينة منزلك</span> <br>إلى تقرير احترافي
                </h1>
                
                <p class="mt-8 max-w-2xl text-lg leading-relaxed text-slate-400">
                    المنصة المتكاملة لإدارة معاينات العقارات. ارفع مئات الصور، حدد العيوب بدقة باستخدام الأسهم التفاعلية، 
                    واحصل على تقرير PDF جاهز للطباعة بضغطة زر واحدة.
                </p>
                
                <div class="mt-12 flex flex-wrap gap-5">
                    <a href="{{ route('login') }}" class="group relative flex items-center gap-3 overflow-hidden rounded-2xl bg-emerald-500 px-8 py-4 font-bold text-slate-950 transition-all hover:bg-emerald-400 hover:shadow-[0_0_40px_rgba(16,185,129,0.3)]">
                        <span>ابدأ المعاينة الآن</span>
                        <svg class="h-5 w-5 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                        </svg>
                    </a>
                    
                    <div class="flex -space-x-3 rtl:space-x-reverse items-center">
                        <img class="h-10 w-10 rounded-full border-2 border-slate-900" src="https://i.pravatar.cc/100?u=1" alt="">
                        <img class="h-10 w-10 rounded-full border-2 border-slate-900" src="https://i.pravatar.cc/100?u=2" alt="">
                        <img class="h-10 w-10 rounded-full border-2 border-slate-900" src="https://i.pravatar.cc/100?u=3" alt="">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full border-2 border-slate-900 bg-slate-800 text-[10px] font-bold text-white">
                            +50
                        </div>
                        <span class="mr-4 text-sm font-medium text-slate-500">موثوق من قبل خبراء العقارات</span>
                    </div>
                </div>
            </div>
            
            <!-- Floating Feature Cards -->
            <div class="mt-24 grid grid-cols-1 gap-6 sm:grid-cols-3 lg:max-w-4xl">
                <div class="glass-card group rounded-2xl p-6 transition-all hover:-translate-y-2">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-500 transition-colors group-hover:bg-emerald-500 group-hover:text-slate-950">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-white">رفع كميات ضخمة</h3>
                    <p class="mt-2 text-sm text-slate-500">ارفع حتى 200 صورة لكل عقار مقسمة حسب الغرف والأماكن بسهولة تامة.</p>
                </div>
                
                <div class="glass-card group rounded-2xl p-6 transition-all hover:-translate-y-2">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-500 transition-colors group-hover:bg-emerald-500 group-hover:text-slate-950">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-white">تعليم دقيق للعيوب</h3>
                    <p class="mt-2 text-sm text-slate-500">أضف أسهم توضيحية على الصور لتحديد مكان التشققات أو المشاكل الفنية بدقة.</p>
                </div>
                
                <div class="glass-card group rounded-2xl p-6 transition-all hover:-translate-y-2">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-500 transition-colors group-hover:bg-emerald-500 group-hover:text-slate-950">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-white">تقارير PDF فورية</h3>
                    <p class="mt-2 text-sm text-slate-500">ولد تقرير شامل ومنظم يحتوي على جميع الصور والأوصاف والتعليمات بجودة عالية.</p>
                </div>
            </div>
        </div>

        <!-- Decorative elements -->
        <div class="absolute -left-20 top-1/4 h-96 w-96 rounded-full bg-emerald-500/10 blur-[120px]"></div>
        <div class="absolute -right-20 bottom-0 h-96 w-96 rounded-full bg-blue-500/10 blur-[120px]"></div>
    </div>
@endsection

