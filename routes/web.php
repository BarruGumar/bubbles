<?php

use App\Http\Controllers\CommentController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\FriendController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/bubbles', function () {
    return Inertia::render('Bubbles');
})->middleware(['auth'])->name('bubbles');

Route::get('/', function () {
        if (auth()->check()) {
        return redirect()->route('bubbles');
    }

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

    // Posts de perfil
    Route::post('/posts', [PostController::class, 'store'])->middleware('throttle:posts')->name('posts.store');
    Route::delete('/posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');

    // Posts de comunidade
    Route::post('/c/{id}/posts', [CommunityController::class, 'store'])->middleware('throttle:posts')->name('community.posts.store');
    Route::delete('/c/{id}/posts/{post}', [CommunityController::class, 'destroy'])->name('community.posts.destroy');
    Route::post('/c/{id}/image', [CommunityController::class, 'uploadImage'])->name('community.image');
    Route::post('/c/{id}/banner', [CommunityController::class, 'uploadBanner'])->name('community.banner');

    // Likes e comentários
    Route::post('/posts/{post}/like', [LikeController::class, 'togglePost'])->middleware('throttle:reactions')->name('posts.like');
    Route::post('/posts/{post}/comments', [CommentController::class, 'storePost'])->middleware('throttle:reactions')->name('posts.comments.store');
    Route::post('/community-posts/{post}/like', [LikeController::class, 'toggleCommunityPost'])->middleware('throttle:reactions')->name('community-posts.like');
    Route::post('/community-posts/{post}/comments', [CommentController::class, 'storeCommunityPost'])->middleware('throttle:reactions')->name('community-posts.comments.store');
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');

    // Community settings + delete (criador apenas)
    Route::put('/c/{id}/settings', [CommunityController::class, 'updateSettings'])->name('community.update');
    Route::delete('/c/{id}', [CommunityController::class, 'deleteCommunity'])->name('community.delete');

    // Community membership
    Route::post('/c/{id}/join', [CommunityController::class, 'join'])->name('community.join');
    Route::delete('/c/{id}/leave', [CommunityController::class, 'leave'])->name('community.leave');

    // Conversations
    Route::get('/conversations', [ConversationController::class, 'index'])->name('conversations.index');
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');
    Route::post('/conversations', [ConversationController::class, 'store'])->name('conversations.store');
    Route::post('/conversations/{conversation}/messages', [ConversationController::class, 'storeMessage'])->middleware('throttle:messages')->name('messages.store');

    // Friends
    Route::get('/friends', [FriendController::class, 'index'])->name('friends.index');
    Route::post('/friends/{username}', [FriendController::class, 'send'])->middleware('throttle:friends')->name('friends.send');
    Route::patch('/friends/{friend}/accept', [FriendController::class, 'accept'])->name('friends.accept');
    Route::delete('/friends/{friend}', [FriendController::class, 'reject'])->name('friends.reject');
});

require __DIR__ . '/auth.php';
