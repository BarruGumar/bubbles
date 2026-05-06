<?php

use App\Http\Controllers\CommunityController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/bubbles', function () {
    return Inertia::render('Bubbles');
})->middleware(['auth']);

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin'    => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Public profile — accessible without login
Route::get('/u/{username}', [ProfileController::class, 'show'])->name('profile.show');

// Communities — public view, posts require auth
Route::get('/c/{id}', [CommunityController::class, 'show'])->name('community.show');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar'])->name('profile.avatar');
    Route::post('/profile/banner', [ProfileController::class, 'uploadBanner'])->name('profile.banner');

    Route::post('/c/{id}/posts', [CommunityController::class, 'store'])->name('community.posts.store');
    Route::delete('/c/{id}/posts/{post}', [CommunityController::class, 'destroy'])->name('community.posts.destroy');
    Route::post('/c/{id}/image', [CommunityController::class, 'uploadImage'])->name('community.image');
    Route::post('/c/{id}/banner', [CommunityController::class, 'uploadBanner'])->name('community.banner');

    Route::post('/posts', [PostController::class, 'store'])->name('posts.store');
    Route::delete('/posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');
});

require __DIR__ . '/auth.php';
