<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Symfony\Component\Console\Output\BufferedOutput;

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

        $lines[] = '=== تم بنجاح — يمكنك إغلاق هذه الصفحة ===';

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
