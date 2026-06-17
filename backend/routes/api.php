<?php

use Illuminate\Support\Facades\Route;

Route::middleware('auth.supabase')->group(function (): void {
    // J-03 : Projets
    // J-04 : Accès projet
    // J-05 : Membres
    // J-06 : Export XLSX
    // J-07 : Suppression projet
});
