<?php

namespace App\Providers;

use App\Contracts\FileStorage;
use App\Services\Storage\SupabaseStorage;
use App\Services\SupabaseAuthService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SupabaseAuthService::class, fn () => new SupabaseAuthService(
            config('supabase.jwt_secret', '')
        ));

        $this->app->singleton(FileStorage::class, fn () => new SupabaseStorage(
            config('supabase.url', ''),
            config('supabase.service_role_key', ''),
        ));
    }

    public function boot(): void {}
}
