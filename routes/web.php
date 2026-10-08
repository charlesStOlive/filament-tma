<?php

use CharlesStOlive\FilamentTma\Http\Controllers\MediaController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])
    ->get('tma/media/{media}', MediaController::class)
    ->name('filament-tma.media');
