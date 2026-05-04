<?php

use App\Http\Controllers\BubbleController;
use App\Http\Controllers\ConnectionController;
use Illuminate\Support\Facades\Route;

Route::apiResource('bubbles', BubbleController::class)->only([
    'index',
    'store',
    'update',
    'destroy',
]);

Route::apiResource('connections', ConnectionController::class)->only([
    'index',
    'store',
    'destroy',
]);