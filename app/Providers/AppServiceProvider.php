<?php

namespace App\Providers;

use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $appUrl = (string) config('app.url', '');
        if ($appUrl !== '') {
            URL::forceRootUrl(rtrim($appUrl, '/'));
            if (str_starts_with($appUrl, 'https://')) {
                URL::forceScheme('https');
            }
        }

        RedirectIfAuthenticated::redirectUsing(
            fn () => Route::has('admin.houses.index') ? route('admin.houses.index') : '/admin'
        );

        \Illuminate\Pagination\Paginator::useTailwind();
    }
}
