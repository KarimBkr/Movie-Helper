<?php

namespace App\Providers;

use App\Contracts\FileStorage;
use App\Services\Claude\ClaudeAnalysisService;
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

        $this->app->singleton(ClaudeAnalysisService::class, fn () => new ClaudeAnalysisService(
            config('anthropic.api_key'),
            config('anthropic.model', 'claude-sonnet-4-6'),
            (int) config('anthropic.max_tokens', 4000),
            (int) config('anthropic.timeout_seconds', 120),
            config('anthropic.base_url', 'https://api.anthropic.com'),
            config('anthropic.version', '2023-06-01'),
        ));
    }

    public function boot(): void {}
}
