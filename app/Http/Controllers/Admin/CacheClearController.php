<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\InspectionReportPdfGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use ReflectionMethod;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

/**
 * مسح كاش Laravel من المتصفح — بدون Terminal (Namecheap / cPanel).
 */
class CacheClearController extends Controller
{
    public function __invoke(Request $request): View
    {
        $this->authorizeAccess($request);

        $lines = [];
        $lines[] = '=== مسح الكاش: '.now()->format('Y-m-d H:i:s').' ===';

        $this->runArtisan('optimize:clear', [], 'optimize:clear (config + route + view + cache + compiled)', $lines);
        $this->runArtisan('config:clear', [], 'config:clear', $lines);
        $this->runArtisan('cache:clear', [], 'cache:clear', $lines);
        $this->runArtisan('route:clear', [], 'route:clear', $lines);
        $this->runArtisan('view:clear', [], 'view:clear', $lines);

        if (function_exists('opcache_reset')) {
            @opcache_reset();
            $lines[] = 'OK: OPcache reset';
        } else {
            $lines[] = 'SKIP: OPcache غير متاح';
        }

        $this->purgeReportCaches($lines);
        $this->reportLogoStatus($lines);

        $lines[] = '=== تم بنجاح — حمّل PDF بزر «تحديث» لرؤية اللوجو الجديد ===';

        $log = implode("\n", $lines);
        @file_put_contents(storage_path('logs/last-cache-clear.txt'), $log);

        return view('admin.system.clear-cache', [
            'log' => $log,
            'clearedAt' => now()->format('Y-m-d H:i:s'),
            'lastRun' => File::exists(storage_path('logs/last-cache-clear.txt'))
                ? date('Y-m-d H:i:s', File::lastModified(storage_path('logs/last-cache-clear.txt')))
                : null,
        ]);
    }

    /**
     * @param  list<string>  $lines
     */
    private function purgeReportCaches(array &$lines): void
    {
        $deletedPdf = 0;
        try {
            $disk = Storage::disk('public');
            if ($disk->exists('reports')) {
                foreach ($disk->files('reports') as $file) {
                    $base = basename($file);
                    if (str_starts_with($base, 'inspection-') && str_ends_with($base, '.pdf')) {
                        $disk->delete($file);
                        $deletedPdf++;
                    }
                }
            }
        } catch (Throwable $e) {
            $lines[] = 'ERR: حذف PDF كاش — '.$e->getMessage();
        }
        $lines[] = 'OK: حذف PDF كاش التقارير ('.$deletedPdf.' ملف)';

        $logoCache = storage_path('app/reports/logo-cache');
        $deletedLogo = 0;
        if (is_dir($logoCache)) {
            foreach (glob($logoCache.DIRECTORY_SEPARATOR.'*') ?: [] as $f) {
                if (is_file($f) && @unlink($f)) {
                    $deletedLogo++;
                }
            }
        }
        $lines[] = 'OK: حذف كاش اللوجو ('.$deletedLogo.' ملف)';
    }

    /**
     * @param  list<string>  $lines
     */
    private function reportLogoStatus(array &$lines): void
    {
        $lines[] = '--- فحص اللوجو ---';
        $lines[] = 'config REPORT_LOGO_PATH = '.(string) config('report.logo_path');

        $checks = [
            public_path('images/newLog.jpeg'),
            public_path('images/newLog.jpg'),
            public_path('images/company-logo.png'),
        ];
        foreach ($checks as $path) {
            $lines[] = (is_file($path) ? 'FOUND ' : 'MISSING ').$path;
        }

        try {
            $generator = app(InspectionReportPdfGenerator::class);
            $method = new ReflectionMethod($generator, 'resolveLogoPath');
            $method->setAccessible(true);
            $resolved = $method->invoke($generator);
            $lines[] = 'RESOLVED logo = '.(is_string($resolved) ? $resolved : 'NULL');
            if (is_string($resolved) && is_file($resolved)) {
                $info = @getimagesize($resolved);
                $lines[] = 'logo size = '.($info ? ($info[0].'x'.$info[1].' '.$info['mime']) : 'unreadable');
            }
        } catch (Throwable $e) {
            $lines[] = 'ERR: resolveLogoPath — '.$e->getMessage();
        }
    }

    private function authorizeAccess(Request $request): void
    {
        $user = $request->user();
        if ($user && method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return;
        }

        $expected = (string) config('app.deploy_cache_token', '');
        $token = (string) $request->query('token', '');

        if ($expected !== '' && $token !== '' && hash_equals($expected, $token)) {
            return;
        }

        abort(403, 'غير مصرح — سجّل الدخول كمسؤول، أو استخدم رابط التوكن من .env');
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
}
