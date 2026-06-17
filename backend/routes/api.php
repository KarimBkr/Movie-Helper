<?php

use App\Http\Controllers\ProjectController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.supabase')->group(function (): void {
    Route::apiResource('projects', ProjectController::class)->only(['index', 'store', 'show']);
});
