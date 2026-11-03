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
        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(
                (int) config('api.rate_limits.register.decay_minutes'),
                (int) config('api.rate_limits.register.max_attempts'))
                ->by($request->ip());
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(
                (int) config('api.rate_limits.login.decay_minutes'),
                (int) config('api.rate_limits.login.max_attempts'))
                ->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip());
        });
    }
}
