<?php

use App\Http\Controllers\BubbleController;
use App\Http\Controllers\ConnectionController;
use Illuminate\Support\Facades\Route;

// Public read endpoints
Route::get('/bubbles', [BubbleController::class,    'index']);
Route::get('/connections', [ConnectionController::class, 'index']);
Route::get('/friend-connections', [BubbleController::class,    'friendConnections']);

Route::post('/login', function (Illuminate\Http\Request $request) {
    $request->validate([
        'email'    => 'required|email',
        'password' => 'required',
    ]);

    $user = \App\Models\User::where('email', $request->email)->first();

    if (! $user || ! \Illuminate\Support\Facades\Hash::check($request->password, $user->password)) {
        return response()->json([
            'message' => 'Credenciais incorrectas.'
        ], 401);
    }

    $token = $user->createToken('bubbles-mobile')->plainTextToken;

    return response()->json([
        'token' => $token,
        'user'  => [
            'id'       => $user->id,
            'name'     => $user->name,
            'username' => $user->username,
            'email'    => $user->email,
            'avatar'   => $user->avatar,
            'role'     => $user->role,
        ],
    ]);
});

// Write endpoints — require authenticated session
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/bubbles', [BubbleController::class, 'store'])
        ->middleware('throttle:create-community');
    Route::put('/bubbles/{bubble}', [BubbleController::class, 'update']);
    Route::patch('/bubbles/{bubble}', [BubbleController::class, 'update']);
    Route::delete('/bubbles/{bubble}', [BubbleController::class, 'destroy']);
});
