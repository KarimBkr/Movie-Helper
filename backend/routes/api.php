<?php

use App\Http\Controllers\AnalysisController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ScriptController;
use App\Http\Controllers\SequenceController;
use App\Http\Controllers\SequenceElementController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.supabase')->group(function (): void {
    Route::apiResource('projects', ProjectController::class)->only(['index', 'store', 'show']);

    // Scripts FDX (L-01 upload, L-03 parsing)
    Route::post('projects/{project}/scripts/upload', [ScriptController::class, 'upload']);
    Route::post('projects/{project}/scripts/{script}/parse', [ScriptController::class, 'parse']);

    // Analyse IA (L-06)
    Route::post('projects/{project}/analysis/start', [AnalysisController::class, 'start']);
    Route::get('projects/{project}/analysis/{job}', [AnalysisController::class, 'show']);
    Route::post('projects/{project}/analysis/{job}/retry-failed', [AnalysisController::class, 'retryFailed']);

    // Tableau de dépouillement : séquences (L-08) + correction humaine (L-09)
    Route::get('projects/{project}/sequences', [SequenceController::class, 'index']);
    Route::get('projects/{project}/sequences/{sequence}', [SequenceController::class, 'show']);
    Route::patch('sequences/{sequence}/validate', [SequenceController::class, 'validateSequence']);

    // Éléments de dépouillement (L-08/L-09) — accès projet résolu via l'élément/séquence
    Route::post('sequences/{sequence}/elements', [SequenceElementController::class, 'store']);
    Route::patch('elements/{element}', [SequenceElementController::class, 'update']);
    Route::delete('elements/{element}', [SequenceElementController::class, 'destroy']);
});
