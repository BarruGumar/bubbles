<?php

use App\Http\Controllers\BubbleController;
use Illuminate\Support\Facades\Route;

Route::apiResource('bubbles', BubbleController::class)->only([
    'index',
    'store',
    'update',
    'destroy',
]);
