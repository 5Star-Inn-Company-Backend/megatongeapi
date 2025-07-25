<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        RateLimiter::for('uploads', function (Request $request) {
            return $request->user()
                ? Limit::none()
                : Limit::perMinute(5);
        });

        RateLimiter::for('freeTranslation', function (Request $request) {
            return Limit::perHour(50)->by($request->ip());
        });
    }
}
