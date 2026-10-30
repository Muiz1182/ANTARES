<?php

use App\Http\Controllers\Api\ScanApiController;
use Illuminate\Support\Facades\Route;

Route::post('/scan', [ScanApiController::class, 'store'])
    ->middleware(['device.token', 'throttle:scan-api'])
    ->name('api.scan.store');

