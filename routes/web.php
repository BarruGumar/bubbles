<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\CommunityModerationController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\FriendController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/bubbles', [FeedController::class, 'home'])->middleware(['auth', 'verified', 'punishments'])->name('bubbles');

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('bubbles');
    }

    return Inertia::render('Welcome', [
        'canLogin'        => Route::has('login'),
        'canRegister'     => Route::has('register'),
        'punishmentModal' => session('punishment_modal'),
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth'])->name('dashboard');

// Public profile — accessible without login
Route::get('/u/{username}', [ProfileController::class, 'show'])->name('profile.show');

// Communities — public view, posts require auth
Route::get('/c/{id}', [CommunityController::class, 'show'])->name('community.show');

// Search — public (filters private data internally)
Route::get('/search', [SearchController::class, 'index'])->name('search.index');
Route::get('/api/search', [SearchController::class, 'api'])->name('search.api');

// Acknowledge punishment notification — allowed even while suspended
Route::middleware(['auth'])->post('/punishment/{punishment}/acknowledge', function (\App\Models\UserPunishment $punishment) {
    abort_if($punishment->user_id !== auth()->id(), 403);
    if ($punishment->notified_at === null) {
        $punishment->updateQuietly(['notified_at' => now()]);
    }
    return back();
})->name('punishment.acknowledge');

Route::middleware(['auth', 'verified', 'punishments'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::get('/profile/password/confirm', [\App\Http\Controllers\Auth\PasswordController::class, 'confirm'])->name('profile.password.confirm')->middleware('signed');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/theme', [ProfileController::class, 'updateTheme'])->name('profile.theme');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar'])->name('profile.avatar');
    Route::post('/profile/banner', [ProfileController::class, 'uploadBanner'])->name('profile.banner');

    // Feed
    Route::get('/feed', [FeedController::class, 'index'])->name('feed.index');

    // Posts de perfil
    Route::post('/posts', [PostController::class, 'store'])->middleware('throttle:posts')->name('posts.store');
    Route::patch('/posts/{post}', [PostController::class, 'update'])->name('posts.update');
    Route::delete('/posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');

    // Posts de comunidade
    Route::post('/c/{id}/posts', [CommunityController::class, 'store'])->middleware('throttle:posts')->name('community.posts.store');
    Route::patch('/c/{id}/posts/{post}', [CommunityController::class, 'updatePost'])->name('community.posts.update');
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

    // Community moderation
    Route::get('/c/{id}/members', [CommunityModerationController::class, 'members'])->name('community.members');
    Route::patch('/c/{id}/members/{user}/role', [CommunityModerationController::class, 'updateRole'])->name('community.members.role');
    Route::post('/c/{id}/moderation/ban', [CommunityModerationController::class, 'ban'])->name('community.moderation.ban');
    Route::delete('/c/{id}/moderation/ban/{user}', [CommunityModerationController::class, 'unban'])->name('community.moderation.unban');
    Route::post('/c/{id}/moderation/mute', [CommunityModerationController::class, 'mute'])->name('community.moderation.mute');
    Route::delete('/c/{id}/moderation/mute/{user}', [CommunityModerationController::class, 'unmute'])->name('community.moderation.unmute');

    // Conversations & Mensagens
    Route::get('/conversations', [ConversationController::class, 'index'])->name('conversations.index');
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');
    Route::post('/conversations', [ConversationController::class, 'store'])->name('conversations.store');
    Route::post('/conversations/{conversation}/messages', [ConversationController::class, 'storeMessage'])->middleware('throttle:messages')->name('messages.store');
    Route::patch('/messages/{message}', [ConversationController::class, 'updateMessage'])->name('messages.update');
    Route::delete('/messages/{message}', [ConversationController::class, 'destroyMessage'])->name('messages.destroy');
    Route::get('/conversations/{conversation}/poll', [ConversationController::class, 'poll'])->name('conversations.poll');
    Route::post('/conversations/{conversation}/typing', [ConversationController::class, 'typing'])->name('conversations.typing');

    // Grupos
    Route::get('/groups/friends', [GroupController::class, 'friends'])->name('groups.friends');
    Route::post('/groups', [GroupController::class, 'store'])->name('groups.store');
    Route::patch('/groups/{conversation}', [GroupController::class, 'update'])->name('groups.update');
    Route::post('/groups/{conversation}/members', [GroupController::class, 'addMember'])->name('groups.members.add');
    Route::delete('/groups/{conversation}/members/{user}', [GroupController::class, 'removeMember'])->name('groups.members.remove');
    Route::delete('/groups/{conversation}/leave', [GroupController::class, 'leave'])->name('groups.leave');
    Route::patch('/groups/{conversation}/promote', [GroupController::class, 'promoteRole'])->name('groups.members.promote');
    Route::patch('/groups/{conversation}/demote', [GroupController::class, 'demoteRole'])->name('groups.members.demote');
    Route::patch('/groups/{conversation}/owner', [GroupController::class, 'transferOwner'])->name('groups.owner');

    // Friends
    Route::get('/friends', [FriendController::class, 'index'])->name('friends.index');
    Route::post('/friends/{username}', [FriendController::class, 'send'])->middleware('throttle:friends')->name('friends.send');
    Route::patch('/friends/{friend}/accept', [FriendController::class, 'accept'])->name('friends.accept');
    Route::delete('/friends/{friend}', [FriendController::class, 'reject'])->name('friends.reject');

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
    Route::delete('/notifications', [NotificationController::class, 'destroyAll'])->name('notifications.destroy-all');

    // Reports
    Route::post('/posts/{post}/report', [ReportController::class, 'storePost'])->name('posts.report');
    Route::post('/community-posts/{post}/report', [ReportController::class, 'storeCommunityPost'])->name('community-posts.report');
});

// Admin panel
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/users', [AdminController::class, 'users'])->name('users');
    Route::patch('/users/{user}/role', [AdminController::class, 'updateUserRole'])->name('users.role');
    Route::delete('/users/{user}', [AdminController::class, 'destroyUser'])->name('users.destroy');
    Route::get('/posts', [AdminController::class, 'posts'])->name('posts');
    Route::delete('/posts/{id}/force', [AdminController::class, 'destroyPost'])->name('posts.destroy');
    Route::post('/posts/{id}/restore', [AdminController::class, 'restorePost'])->name('posts.restore');
    Route::get('/community-posts', [AdminController::class, 'communityPosts'])->name('community-posts');
    Route::delete('/community-posts/{id}/force', [AdminController::class, 'destroyCommunityPost'])->name('community-posts.destroy');
    Route::post('/community-posts/{id}/restore', [AdminController::class, 'restoreCommunityPost'])->name('community-posts.restore');
    Route::get('/communities', [AdminController::class, 'communities'])->name('communities');
    Route::delete('/communities/{bubble}', [AdminController::class, 'destroyCommunity'])->name('communities.destroy');
    Route::get('/reports', [AdminController::class, 'reports'])->name('reports');
    Route::patch('/reports/{report}/resolve', [AdminController::class, 'resolveReport'])->name('reports.resolve');
    Route::patch('/reports/{report}/dismiss', [AdminController::class, 'dismissReport'])->name('reports.dismiss');

    // Punishments
    Route::get('/punishments', [AdminController::class, 'punishments'])->name('punishments');
    Route::post('/punishments', [AdminController::class, 'createPunishment'])->name('punishments.store');
    Route::patch('/punishments/{punishment}/revoke', [AdminController::class, 'revokePunishment'])->name('punishments.revoke');

    // Audit Logs
    Route::get('/audit-logs', [AdminController::class, 'auditLogs'])->name('audit-logs');
    Route::delete('/audit-logs', [AdminController::class, 'destroyAllAuditLogs'])->name('audit-logs.destroy-all');
    Route::delete('/audit-logs/{log}', [AdminController::class, 'destroyAuditLog'])->name('audit-logs.destroy');
});

require __DIR__.'/auth.php';
