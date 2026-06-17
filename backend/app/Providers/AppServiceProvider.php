<?php

namespace App\Providers;

use App\Services\SupabaseAuthService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SupabaseAuthService::class, fn () => new SupabaseAuthService(
            config('supabase.jwt_secret', '')
        ));
    }

    public function boot(): void {}

}
