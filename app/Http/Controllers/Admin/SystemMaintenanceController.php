<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DriveMediaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * صيانة السيرفر من المتصفح — بدون SSH (مناسب لـ Namecheap Shared).
 * مفعّلة فقط عند SHOW_SERVER_MAINTENANCE=true في .env
 */
class SystemMaintenanceController extends Controller
{
    public function __construct()
    {
        if (! config('app.show_server_maintenance', false)) {
            abort(404);
        }
    }

    public function index(): View
    {
        $checks = $this->environmentChecks();

        return view('admin.system.maintenance', compact('checks'));
    }

    public function run(Request $request): View
    {
        $lines = [];
        $lines[] = '=== بدء الصيانة: '.now()->format('Y-m-d H:i:s').' ===';

        try {
            DriveMediaService::ensureStorageDirectories();
            $lines[] = 'OK: مجلدات التخزين (cache, reports, temp)';
        } catch (\Throwable $e) {
            $lines[] = 'ERR: مجلدات التخزين — '.$e->getMessage();
        }

        try {
            $lines[] = DriveMediaService::ensureCacheDatabaseColumns()
                ? 'OK: أعمدة كاش الصور في قاعدة البيانات'
                : 'WARN: تعذر التحقق من أعمدة الكاش';
        } catch (\Throwable $e) {
            $lines[] = 'ERR: أعمدة الكاش — '.$e->getMessage();
        }

        foreach ([
            storage_path('app/reports/embed-cache'),
            storage_path('app/reports/tmp'),
            storage_path('app/reports/sections'),
            storage_path('app/public/reports'),
        ] as $dir) {
            if (! is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $lines[] = is_dir($dir) ? 'OK: '.str_replace(base_path(), '', $dir) : 'WARN: '.$dir;
        }

        try {
            if (! Storage::disk('public')->exists('reports')) {
                Storage::disk('public')->makeDirectory('reports');
            }
            $lines[] = 'OK: public/storage/reports';
        } catch (\Throwable $e) {
            $lines[] = 'WARN: reports — '.$e->getMessage();
        }

        $this->runArtisan('config:clear', [], 'مسح كاش الإعدادات', $lines);
        $this->runArtisan('route:clear', [], 'مسح كاش المسارات', $lines);
        $this->runArtisan('view:clear', [], 'مسح كاش الواجهات', $lines);
        $this->runArtisan('migrate', ['--force' => true], 'تحديث قاعدة البيانات', $lines);

        if (function_exists('opcache_reset')) {
            @opcache_reset();
            $lines[] = 'OK: OPcache reset';
        }

        $lines[] = '=== انتهت الصيانة ===';
        $lines[] = 'تذكير: ضع SHOW_SERVER_MAINTENANCE=false في .env على الإنتاج لإخفاء هذه الصفحة.';

        $log = implode("\n", $lines);
        @file_put_contents(storage_path('logs/last-maintenance.txt'), $log);

        $checks = $this->environmentChecks();

        return view('admin.system.maintenance', [
            'checks' => $checks,
            'log' => $log,
            'ran' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  list<string>  $lines
     */
    private function runArtisan(string $command, array $options, string $label, array &$lines): void
    {
        try {
            $output = new BufferedOutput;
            Artisan::call($command, $options, $output);
            $out = trim($output->fetch());
            $lines[] = 'OK: '.$label.($out !== '' ? ' — '.$out : '');
        } catch (\Throwable $e) {
            $lines[] = 'ERR: '.$label.' — '.$e->getMessage();
        }
    }

    /**
     * @return array<string, string>
     */
    private function environmentChecks(): array
    {
        $publicStorage = public_path('storage');
        $storageLink = is_link($publicStorage) || is_dir($publicStorage);

        return [
            'PHP' => PHP_VERSION,
            'GD' => extension_loaded('gd') ? 'مفعّل' : 'غير مفعّل (مهم للصور)',
            'ذاكرة PHP' => ini_get('memory_limit') ?: '—',
            'حد رفع ملف' => ini_get('upload_max_filesize') ?: '—',
            'حد POST' => ini_get('post_max_size') ?: '—',
            'وقت التنفيذ' => ini_get('max_execution_time') ?: '—',
            'مجلد storage' => is_writable(storage_path()) ? 'قابل للكتابة' : 'غير قابل للكتابة',
            'public/storage' => $storageLink ? 'موجود' : 'قد تحتاج ربط يدوي على الاستضافة',
            'FPDI (دمج PDF)' => class_exists('setasign\\Fpdi\\Fpdi') ? 'مثبت' : 'غير مثبت',
            'آخر صيانة' => File::exists(storage_path('logs/last-maintenance.txt'))
                ? date('Y-m-d H:i', File::lastModified(storage_path('logs/last-maintenance.txt')))
                : 'لم تُنفَّذ بعد',
        ];
    }
}
