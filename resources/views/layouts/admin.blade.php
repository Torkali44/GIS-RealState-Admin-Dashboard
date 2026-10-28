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
                <a href="{{ route('admin.houses.index') }}" class="flex items-center gap-3">
                    <img src="{{ asset(config('report.logo_path', 'images/newLog.jpeg')) }}"
                        alt="GIS"
                        class="h-10 w-auto max-w-[120px] object-contain rounded-lg bg-white/95 p-1 shadow-lg shadow-emerald-500/10">
                    <span class="text-xl font-bold tracking-tight text-white">
                        جي اي اس <span class="text-emerald-500">للمعاينة</span>
                    </span>
                </a>
            </div>

            <nav class="flex w-full flex-wrap items-center gap-3 sm:w-auto sm:gap-4">
                <a href="{{ route('admin.houses.index') }}"
                    class="text-sm font-semibold text-slate-400 hover:text-white transition-colors">المنازل</a>
                <a href="{{ route('admin.section-templates.index') }}"
                    class="text-sm font-semibold text-slate-400 hover:text-white transition-colors">الأقسام الجاهزة</a>
                <a href="{{ route('admin.note-categories.index') }}"
                    class="text-sm font-semibold text-slate-400 hover:text-white transition-colors">الملاحظات الجاهزة</a>
                @if (config('app.show_server_maintenance'))
                    <a href="{{ route('admin.system.maintenance') }}"
                        class="text-sm font-semibold text-amber-400/90 hover:text-amber-300 transition-colors">صيانة السيرفر</a>
                @endif
                <!-- <a href="{{ route('admin.system.clear-cache') }}"
                    class="text-sm font-semibold text-slate-400 hover:text-emerald-400 transition-colors"
                    title="بعد رفع التعديلات">مسح الكاش</a> -->
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
    <div id="custom-popup-overlay"
        style="position: fixed; inset: 0; z-index: 99999; background: rgba(2, 6, 23, 0.88); backdrop-filter: blur(6px); display: none;"
        aria-hidden="true">
    </div>
    <div id="custom-popup"
        style="position: fixed; inset: 0; z-index: 100000; display: none; align-items: center; justify-content: center; padding: 1rem;">
        <div id="custom-popup-card"
            style="background: #0f172a; border: 1px solid rgba(148,163,184,0.15); border-radius: 1.5rem; box-shadow: 0 0 60px rgba(0,0,0,0.7), 0 0 0 1px rgba(16,185,129,0.08); padding: 2rem; max-width: 28rem; width: 100%; position: relative; overflow: hidden; text-align: center; transform: scale(0.95); transition: transform 0.25s cubic-bezier(0.34,1.56,0.64,1); opacity: 0; transition: transform 0.25s cubic-bezier(0.34,1.56,0.64,1), opacity 0.2s ease;">
            <!-- Decorative background elements -->
            <div style="position: absolute; top: -6rem; right: -6rem; width: 12rem; height: 12rem; background: rgba(245,158,11,0.08); border-radius: 50%; filter: blur(40px); pointer-events: none;"></div>
            <div style="position: absolute; bottom: -6rem; left: -6rem; width: 12rem; height: 12rem; background: rgba(16,185,129,0.08); border-radius: 50%; filter: blur(40px); pointer-events: none;"></div>

            <div style="position: relative; z-index: 10; display: flex; flex-direction: column; align-items: center;">
                <div id="popup-icon"
                    style="display: flex; height: 4rem; width: 4rem; align-items: center; justify-content: center; border-radius: 50%; background: rgba(30,41,59,0.9); border: 1px solid rgba(71,85,105,0.6); color: #f59e0b; margin-bottom: 1.25rem; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
                    <svg style="height: 2rem; width: 2rem;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>

                <h3 style="font-size: 1.4rem; font-weight: 700; color: #fff; margin-bottom: 0.75rem;">تأكيد الإجراء</h3>
                <p id="popup-message" style="font-size: 1rem; color: #cbd5e1; margin-bottom: 2rem; line-height: 1.7;">رسالة</p>

                <div style="display: flex; align-items: center; justify-content: center; gap: 1rem; width: 100%;">
                    <button id="popup-confirm-btn"
                        style="flex: 1; padding: 0.875rem 1rem; border-radius: 0.75rem; background: #f59e0b; color: #0c0a09; font-weight: 700; font-size: 1rem; border: none; cursor: pointer; transition: all 0.2s;">تأكيد</button>
                    <button id="popup-cancel-btn"
                        style="flex: 1; padding: 0.875rem 1rem; border-radius: 0.75rem; background: rgba(30,41,59,0.9); color: #f1f5f9; font-weight: 700; font-size: 1rem; border: 1px solid rgba(71,85,105,0.5); cursor: pointer; transition: all 0.2s;">إلغاء</button>
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
            const overlay = document.getElementById('custom-popup-overlay');
            const popup = document.getElementById('custom-popup');
            const card = document.getElementById('custom-popup-card');

            overlay.style.display = 'block';
            popup.style.display = 'flex';
            // trigger reflow
            void card.offsetWidth;
            card.style.transform = 'scale(1)';
            card.style.opacity = '1';
        }

        function closePopup() {
            const overlay = document.getElementById('custom-popup-overlay');
            const popup = document.getElementById('custom-popup');
            const card = document.getElementById('custom-popup-card');

            card.style.transform = 'scale(0.95)';
            card.style.opacity = '0';
            setTimeout(() => {
                popup.style.display = 'none';
                overlay.style.display = 'none';
            }, 250);
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