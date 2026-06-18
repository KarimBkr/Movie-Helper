<?php

use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ScriptController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.supabase')->group(function (): void {
    Route::apiResource('projects', ProjectController::class)->only(['index', 'store', 'show']);

    // Scripts FDX (L-01 upload, L-03 parsing)
    Route::post('projects/{project}/scripts/upload', [ScriptController::class, 'upload']);
    Route::post('projects/{project}/scripts/{script}/parse', [ScriptController::class, 'parse']);
});
