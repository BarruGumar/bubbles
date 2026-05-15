<?php

use App\Http\Controllers\BubbleController;
use App\Http\Controllers\ConnectionController;
use Illuminate\Support\Facades\Route;

// Public read endpoints
Route::get('/bubbles',            [BubbleController::class,    'index']);
Route::get('/connections',        [ConnectionController::class, 'index']);
Route::get('/friend-connections', [BubbleController::class,    'friendConnections']);

// Write endpoints — require authenticated session
Route::middleware('auth:sanctum')->group(function () {
    Route::post(  '/bubbles',          [BubbleController::class, 'store'])
        ->middleware('throttle:create-community');
    Route::put(   '/bubbles/{bubble}', [BubbleController::class, 'update']);
    Route::patch( '/bubbles/{bubble}', [BubbleController::class, 'update']);
    Route::delete('/bubbles/{bubble}', [BubbleController::class, 'destroy']);

    Route::post(  '/connections',              [ConnectionController::class, 'store']);
    Route::delete('/connections/{connection}', [ConnectionController::class, 'destroy']);
});
