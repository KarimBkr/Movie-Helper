<?php

use App\Http\Controllers\ProjectController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.supabase')->group(function (): void {
    Route::get('projects', [ProjectController::class, 'index']);
    Route::post('projects', [ProjectController::class, 'store']);

    Route::middleware('project.member')->group(function (): void {
        Route::get('projects/{id}', [ProjectController::class, 'show']);
    });
});
