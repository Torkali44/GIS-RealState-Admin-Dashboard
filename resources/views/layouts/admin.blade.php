<!DOCTYPE html>
<html lang="ar" dir="rtl" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'لوحة التحكم') — {{ config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&family=Outfit:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
        <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @endif

    <style>
        [x-cloak] {
            display: none !important;
        }

        :root {
            --brand-primary: #10b981;
            --bg-dark: #020617;
            --bg-card: #0f172a;
        }

        body {
            font-family: 'IBM Plex Sans Arabic', 'Outfit', sans-serif;
            background: var(--bg-dark);
            color: #f1f5f9;
            margin: 0;
        }

        .glass-card {
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
    </style>
    @stack('styles')
</head>

<body class="min-h-full antialiased bg-slate-950">
    <header class="sticky top-0 z-40 border-b border-white/5 bg-slate-950/80 backdrop-blur-xl">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-4 py-4 sm:px-6">
            <div class="flex items-center gap-3">
                <div
                    class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-500 shadow-lg shadow-emerald-500/20">
                    <svg class="h-5 w-5 text-slate-950" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                </div>
                <a href="{{ route('admin.houses.index') }}" class="text-xl font-bold tracking-tight text-white">
                    جي اي اس <span class="text-emerald-500">للمعاينة</span>
                </a>
            </div>

            <nav class="flex w-full flex-wrap items-center gap-3 sm:w-auto sm:gap-4">
                <a href="{{ route('admin.houses.index') }}"
                    class="text-sm font-semibold text-slate-400 hover:text-white transition-colors">المنازل</a>
                <a href="{{ route('admin.section-templates.index') }}"
                    class="text-sm font-semibold text-slate-400 hover:text-white transition-colors">الأقسام الجاهزة</a>
                <a href="{{ route('admin.note-categories.index') }}"
                    class="text-sm font-semibold text-slate-400 hover:text-white transition-colors">الملاحظات الجاهزة</a>
                <a href="{{ route('home') }}"
                    class="text-sm font-semibold text-slate-400 hover:text-white transition-colors">الموقع العام</a>
                <div class="h-4 w-px bg-slate-800"></div>
                <form method="post" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit"
                        class="rounded-lg bg-slate-800 px-4 py-2 text-xs font-bold text-white hover:bg-slate-700 transition-colors">
                        تسجيل الخروج
                    </button>
                </form>
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-10">
        @if (!extension_loaded('gd'))
            <div
                class="mb-8 flex items-center gap-4 rounded-2xl border border-amber-500/30 bg-amber-500/10 p-5 text-amber-200">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-500/20">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div class="text-sm">
                    <p class="font-bold">تنبيه فني: إضافة GD غير مفعلة في PHP.</p>
                    <p class="mt-1 opacity-80">استخراج تقارير PDF للصور من نوع PNG قد يفشل. لقد قمنا بتفعيل معالجة تلقائية
                        للصور عند الرفع لضمان الجودة، ولكن يفضل تفعيل الإضافة في الخادم.</p>
                </div>
            </div>
        @endif

        @if (session('status'))
            <div
                class="mb-8 flex items-center gap-3 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-5 py-4 text-emerald-200 animate-in fade-in slide-in-from-top-4">
                <svg class="h-5 w-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                </svg>
                <span class="text-sm font-medium">{{ session('status') }}</span>
            </div>
        @endif

        @if (session('error'))
            <div class="mb-8 flex items-center gap-3 rounded-xl border border-red-500/30 bg-red-500/10 px-5 py-4 text-red-200">
                <svg class="h-5 w-5 text-red-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="text-sm font-medium">{{ session('error') }}</span>
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-8 rounded-xl border border-red-500/30 bg-red-500/10 px-5 py-4 text-red-200">
                <div class="flex items-center gap-3 mb-2">
                    <svg class="h-5 w-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="font-bold text-sm">حدث خطأ ما:</span>
                </div>
                <ul class="list-inside list-disc space-y-1 text-xs opacity-90">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>

    @stack('scripts')

    <!-- Custom Popup HTML -->
    <div id="custom-popup"
        class="fixed inset-0 z-[1] flex items-center justify-center bg-slate-950/80 backdrop-blur-md hidden opacity-0 transition-all duration-300 ">
        <div
            class="bg-slate-900 border border-slate-800 rounded-3xl shadow-[0_0_50px_rgba(0,0,0,0.5)] p-8 max-w-md w-full mx-4 transform scale-95 transition-transform duration-300 relative overflow-hidden text-right">
            <!-- Decorative background elements -->
            <div class="absolute -top-24 -right-24 w-48 h-48 bg-amber-500/10 rounded-full blur-3xl"></div>
            <div class="absolute -bottom-24 -left-24 w-48 h-48 bg-emerald-500/10 rounded-full blur-3xl"></div>

            <div class="relative z-10 flex flex-col items-center text-center">
                <div id="popup-icon"
                    class="flex h-16 w-16 items-center justify-center rounded-full bg-slate-800/80 border border-slate-700 text-amber-500 mb-5 shadow-lg">
                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>

                <h3 class="text-2xl font-bold text-white mb-3">تأكيد الإجراء</h3>
                <p id="popup-message" class="text-base text-slate-300 mb-8 leading-relaxed">رسالة</p>

                <div class="flex items-center justify-center gap-4 w-full">
                    <button id="popup-confirm-btn"
                        class="flex-1 py-3.5 rounded-xl bg-amber-500 text-slate-950 font-bold text-base hover:bg-amber-400 hover:shadow-[0_0_20px_rgba(245,158,11,0.3)] transition-all">تأكيد</button>
                    <button id="popup-cancel-btn"
                        class="flex-1 py-3.5 rounded-xl bg-slate-800 text-white font-bold text-base border border-slate-700 hover:bg-slate-700 transition-all">إلغاء</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // حفظ موضع التمرير قبل أي submit واستعادته بعد إعادة تحميل الصفحة على نفس المسار.
        (function () {
            try {
                if ('scrollRestoration' in history) history.scrollRestoration = 'manual';
            } catch (e) { /* ignore */ }

            var SCROLL_KEY = 'admin_scroll_state';

            document.addEventListener('submit', function (e) {
                var form = e.target;
                if (!(form instanceof HTMLFormElement)) return;
                if (form.dataset.noScrollSave === '1') return;
                try {
                    sessionStorage.setItem(SCROLL_KEY, JSON.stringify({
                        path: window.location.pathname,
                        y: window.scrollY || window.pageYOffset || 0,
                        ts: Date.now(),
                    }));
                } catch (err) { /* ignore */ }
            }, true);

            window.addEventListener('DOMContentLoaded', function () {
                var raw;
                try { raw = sessionStorage.getItem(SCROLL_KEY); } catch (err) { return; }
                if (!raw) return;
                try { sessionStorage.removeItem(SCROLL_KEY); } catch (err) { /* ignore */ }

                var data;
                try { data = JSON.parse(raw); } catch (err) { return; }
                if (!data || typeof data.y !== 'number') return;
                if (Date.now() - (data.ts || 0) > 30000) return;
                if (data.path !== window.location.pathname) return;

                var hash = window.location.hash;
                if (hash && hash.length > 1) {
                    var target = document.querySelector(hash);
                    if (target) {
                        target.scrollIntoView({ behavior: 'auto', block: 'start' });
                        return;
                    }
                }

                window.scrollTo(0, data.y);
            });
        })();

        let popupConfirmCallback = null;

        function showAlert(message) {
            const popup = document.getElementById('custom-popup');
            document.getElementById('popup-message').textContent = message;
            document.getElementById('popup-cancel-btn').style.display = 'none';
            document.getElementById('popup-confirm-btn').textContent = 'حسناً';

            popupConfirmCallback = function () {
                closePopup();
            };

            openPopup();
        }

        function confirmAction(message, callback) {
            const popup = document.getElementById('custom-popup');
            document.getElementById('popup-message').textContent = message;
            document.getElementById('popup-cancel-btn').style.display = 'block';
            document.getElementById('popup-confirm-btn').textContent = 'تأكيد';

            popupConfirmCallback = function () {
                closePopup();
                if (callback) callback();
            };

            openPopup();
        }

        function openPopup() {
            const popup = document.getElementById('custom-popup');
            popup.classList.remove('hidden');
            // trigger reflow
            void popup.offsetWidth;
            popup.classList.remove('opacity-0');
            popup.children[0].classList.remove('scale-95');
            popup.children[0].classList.add('scale-100');
        }

        function closePopup() {
            const popup = document.getElementById('custom-popup');
            popup.classList.add('opacity-0');
            popup.children[0].classList.remove('scale-100');
            popup.children[0].classList.add('scale-95');
            setTimeout(() => {
                popup.classList.add('hidden');
            }, 300);
        }

        document.getElementById('popup-confirm-btn').addEventListener('click', function () {
            if (popupConfirmCallback) popupConfirmCallback();
        });

        document.getElementById('popup-cancel-btn').addEventListener('click', function () {
            closePopup();
        });
    </script>
</body>

</html>