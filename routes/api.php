<?php

use App\Modules\Masjid\Http\AuthenticateScreen;
use App\Modules\Masjid\Http\DisplayController;
use App\Modules\Wallet\Http\AuthenticateDevice;
use App\Modules\Wallet\Http\DeviceScanController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/device')->middleware([AuthenticateDevice::class, 'throttle:600,1'])->group(function (): void {
    Route::post('/scan', [DeviceScanController::class, 'scan']);
    Route::post('/wallet/charge', [DeviceScanController::class, 'charge']);
    Route::post('/attendance/scan', [DeviceScanController::class, 'attendance']);
    Route::post('/gate/scan', [DeviceScanController::class, 'gate']);
    Route::post('/attendance/face/challenge', [DeviceScanController::class, 'faceChallenge']);
    Route::post('/attendance/face', [DeviceScanController::class, 'face']);
});

// Layar TV masjid: token layar hanya bisa membaca manifest dan mengirim status (PRD Masjid 8.7).
Route::prefix('v1/display')->middleware('module:masjid_display')->group(function (): void {
    Route::post('/pair/start', [DisplayController::class, 'pairStart'])->middleware('throttle:20,1');
    Route::get('/pair/status', [DisplayController::class, 'pairStatus'])->middleware('throttle:60,1');
    Route::get('/media/{content}', [DisplayController::class, 'media'])->name('display.media');
    Route::middleware([AuthenticateScreen::class, 'throttle:120,1'])->group(function (): void {
        Route::get('/manifest', [DisplayController::class, 'manifest']);
        Route::get('/manifest/version', [DisplayController::class, 'version']);
        Route::post('/heartbeat', [DisplayController::class, 'heartbeat']);
        Route::post('/command-ack', [DisplayController::class, 'ack']);
    });
});
