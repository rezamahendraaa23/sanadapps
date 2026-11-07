<?php

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
