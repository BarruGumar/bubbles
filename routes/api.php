<?php

<<<<<<< HEAD
use App\Http\Controllers\BubbleController;
use Illuminate\Support\Facades\Route;

Route::apiResource('bubbles', BubbleController::class)->only([
    'index',
    'store',
    'update',
    'destroy',
]);
=======
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BubbleController;
use App\Http\Controllers\ConnectionController;

Route::get('/bubbles', [BubbleController::class, 'index']);
Route::post('/bubbles', [BubbleController::class, 'store']);

Route::get('/connections', [ConnectionController::class, 'index']);
Route::post('/connections', [ConnectionController::class, 'store']);
>>>>>>> da5b16d (merda)
