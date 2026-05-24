<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GoogleDriveService;
use Google\Client;
use Google\Service\Drive;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GoogleDriveAuthController extends Controller
{
    public function connect(): RedirectResponse
    {
        if (! GoogleDriveService::oauthClientConfigured()) {
            return redirect()
                ->route('admin.houses.index')
                ->with('error', 'ضبط GOOGLE_DRIVE_OAUTH_CLIENT_ID و GOOGLE_DRIVE_OAUTH_CLIENT_SECRET في .env أولاً.');
        }

        $client = GoogleDriveService::buildOAuthClient();
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setIncludeGrantedScopes(true);
        $client->setScopes([Drive::DRIVE]);

        return redirect()->away($client->createAuthUrl());
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->has('error')) {
            return redirect()
                ->route('admin.houses.index')
                ->with('error', 'تم إلغاء ربط Google Drive.');
        }

        $code = $request->query('code');
        if (! is_string($code) || $code === '') {
            return redirect()
                ->route('admin.houses.index')
                ->with('error', 'رمز التفويض من Google غير موجود.');
        }

        try {
            $client = GoogleDriveService::buildOAuthClient();
            $token = $client->fetchAccessTokenWithAuthCode($code);

            if (isset($token['error'])) {
                throw new \RuntimeException((string) ($token['error_description'] ?? $token['error']));
            }

            GoogleDriveService::saveOAuthToken($token);
            GoogleDriveService::resetClient();

            return redirect()
                ->route('admin.houses.index')
                ->with('status', 'تم ربط Google Drive بنجاح. جرّب رفع صورة الآن.');
        } catch (\Throwable $e) {
            Log::error('Google Drive OAuth callback failed', ['error' => $e->getMessage()]);

            return redirect()
                ->route('admin.houses.index')
                ->with('error', 'فشل ربط Google Drive: '.$e->getMessage());
        }
    }

    public function disconnect(): RedirectResponse
    {
        GoogleDriveService::clearOAuthToken();
        GoogleDriveService::resetClient();

        return redirect()
            ->route('admin.houses.index')
            ->with('status', 'تم فصل Google Drive.');
    }
}
