<?php

use App\Http\Controllers\Api\InstrumentSyncController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/admin', [WebhookController::class, 'handle'])
    ->middleware(['webhook.signature', 'throttle:60,1']);

Route::prefix('v1')->middleware(['instruments.api_key', 'throttle:500,30'])->group(function () {
    Route::get('/instruments', [InstrumentSyncController::class, 'index']);
});
