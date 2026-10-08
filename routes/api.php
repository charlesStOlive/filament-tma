<?php

use CharlesStOlive\FilamentTma\Http\Controllers\Api\Client\SyncController;
use CharlesStOlive\FilamentTma\Http\Controllers\Api\Client\UserController;
use CharlesStOlive\FilamentTma\Http\Controllers\Api\Master\TicketController;
use CharlesStOlive\FilamentTma\Http\Controllers\Api\PingController;
use CharlesStOlive\FilamentTma\Http\Middleware\AuthenticateInstance;
use CharlesStOlive\FilamentTma\Http\Middleware\AuthenticateMaster;
use Illuminate\Support\Facades\Route;

/*
 * L'API v1 entre les applications (docs/API.md). Les deux groupes existent partout ; chacun ne répond que dans son
 * mode (404 sinon) et seulement à l'autre application.
 */
Route::prefix('api/tma/v1')
    ->middleware(['api', 'auth:'.config('filament-tma.api.guard', 'api'), 'throttle:120,1'])
    ->name('filament-tma.api.')
    ->group(function (): void {
        // Le maître, appelé par une application cliente.
        Route::middleware(AuthenticateInstance::class)->name('master.')->group(function (): void {
            Route::get('master/ping', PingController::class)->name('ping');
            Route::post('tickets', [TicketController::class, 'store'])->name('tickets.store');
            Route::get('tickets/{ulid}', [TicketController::class, 'show'])->name('tickets.show');
            Route::post('tickets/{ulid}/moves', [TicketController::class, 'move'])->name('tickets.move');
            Route::post('tickets/{ulid}/entries', [TicketController::class, 'entry'])->name('tickets.entry');
        });

        // Une application cliente, appelée par le maître.
        Route::middleware(AuthenticateMaster::class)->name('client.')->group(function (): void {
            Route::get('ping', PingController::class)->name('ping');
            Route::get('users', UserController::class)->name('users');
            Route::post('sync', [SyncController::class, 'sync'])->name('sync');
            Route::post('media/{owner}/{ulid}', [SyncController::class, 'media'])->name('media');
        });
    });
