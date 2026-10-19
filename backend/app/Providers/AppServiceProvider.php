<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
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
        RateLimiter::for('auth-register', fn (Request $request) => Limit::perMinute(6)->by($request->ip()));
        RateLimiter::for('auth-login', fn (Request $request) => Limit::perMinute(6)->by($request->ip()));
        RateLimiter::for('auth-forgot-password', fn (Request $request) => Limit::perMinute(3)->by($request->ip()));
        RateLimiter::for('auth-reset-password', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('mfa-challenge', fn (Request $request) => Limit::perMinute(5)->by(
            $request->ip().'|'.$request->session()->get('mfa_pending_user_id', 'none'),
        ));
        RateLimiter::for('mfa-setup', fn (Request $request) => Limit::perMinute(5)->by($request->user()->getAuthIdentifier()));
        RateLimiter::for('mfa-enable', fn (Request $request) => Limit::perMinute(5)->by($request->user()->getAuthIdentifier()));
        RateLimiter::for('mfa-disable', fn (Request $request) => Limit::perMinute(5)->by($request->user()->getAuthIdentifier()));
        RateLimiter::for('weather', fn (Request $request) => Limit::perMinute(10)->by($request->user()->getAuthIdentifier()));
        RateLimiter::for('maps-search', fn (Request $request) => Limit::perMinute(5)->by($request->user()->getAuthIdentifier()));

        ResetPassword::createUrlUsing(function (object $notifiable, string $token): string {
            $query = http_build_query([
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);

            return rtrim((string) config('app.frontend_url'), '/').'/reset-password?'.$query;
        });
    }
}
