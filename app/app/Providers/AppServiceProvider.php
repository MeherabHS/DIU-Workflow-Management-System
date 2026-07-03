<?php

namespace App\Providers;

use App\Models\Project;
use App\Models\User;
use App\Observers\ProjectObserver;
use App\Policies\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        $this->configureRateLimiters();

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        Vite::prefetch(concurrency: 3);

        Gate::policy(User::class, UserPolicy::class);

        Project::observe(ProjectObserver::class);
    }

    private function configureRateLimiters(): void
    {
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(
            Str::lower((string) $request->input('email')).'|'.$request->ip()
        ));

        RateLimiter::for('register', fn (Request $request) => [
            Limit::perHour(3)->by($request->ip()),
            Limit::perDay(10)->by($request->ip()),
        ]);

        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinutes(15, 3)->by(
            Str::lower((string) $request->input('email')).'|'.$request->ip()
        ));

        RateLimiter::for('confirm-password', fn (Request $request) => Limit::perMinute(6)->by(
            $this->rateLimitUserKey($request)
        ));

        RateLimiter::for('ai-comparison', fn (Request $request) => Limit::perMinute(3)->by(
            $this->rateLimitUserKey($request)
        ));

        RateLimiter::for('workflow-upload', fn (Request $request) => Limit::perMinutes(10, 10)->by(
            $this->rateLimitUserKey($request)
        ));

        RateLimiter::for('profile-photo', fn (Request $request) => Limit::perMinutes(10, 5)->by(
            $this->rateLimitUserKey($request)
        ));

        RateLimiter::for('workflow-message', fn (Request $request) => Limit::perMinute(20)->by(
            $this->rateLimitUserKey($request)
        ));

        RateLimiter::for('notification-action', function (Request $request) {
            $limit = $request->routeIs('notifications.read-all') ? 10 : 60;

            return Limit::perMinute($limit)->by($this->rateLimitUserKey($request));
        });

        RateLimiter::for('report-export', function (Request $request) {
            if ($request->query('export') !== 'csv') {
                return Limit::none();
            }

            return Limit::perMinutes(10, 5)->by($this->rateLimitUserKey($request));
        });
    }

    private function rateLimitUserKey(Request $request): string
    {
        return (string) ($request->user()?->id ?? $request->ip());
    }
}
