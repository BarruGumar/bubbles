<?php

use App\Http\Controllers\Admin\AnnouncementController as AdminAnnouncements;
use App\Http\Controllers\Admin\AuditLogController as AdminAuditLogs;
use App\Http\Controllers\Admin\ContentController as AdminContent;
use App\Http\Controllers\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Admin\PunishmentController as AdminPunishments;
use App\Http\Controllers\Admin\ReportController as AdminReports;
use App\Http\Controllers\Admin\UserController as AdminUsers;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\CommunityMediaController;
use App\Http\Controllers\CommunityModerationController;
use App\Http\Controllers\CommunityPostController;
use App\Http\Controllers\CommunitySettingsController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\FriendController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\BlockController;
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
})->middleware(['auth', 'verified'])->name('dashboard');

// Public profile — accessible without login
Route::get('/u/{username}', [ProfileController::class, 'show'])->name('profile.show');

// Communities — public view, posts require auth
Route::get('/c/{id}', [CommunityController::class, 'show'])->name('community.show');

// Search — public (filters private data internally)
Route::middleware('throttle:search')->group(function () {
    Route::get('/search', [SearchController::class, 'index'])->name('search.index');
    Route::get('/api/search', [SearchController::class, 'api'])->name('search.api');
});

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
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar'])->middleware('throttle:uploads')->name('profile.avatar');
    Route::delete('/profile/avatar', [ProfileController::class, 'removeAvatar'])->name('profile.avatar.remove');
    Route::post('/profile/banner', [ProfileController::class, 'uploadBanner'])->middleware('throttle:uploads')->name('profile.banner');
    Route::delete('/profile/banner', [ProfileController::class, 'removeBanner'])->name('profile.banner.remove');

    // Feed
    Route::get('/feed', [FeedController::class, 'index'])->name('feed.index');

    // Posts de perfil
    Route::post('/posts', [PostController::class, 'store'])->middleware('throttle:posts')->name('posts.store');
    Route::patch('/posts/{post}', [PostController::class, 'update'])->name('posts.update');
    Route::delete('/posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');

    // Posts de comunidade
    Route::post('/c/{id}/posts', [CommunityPostController::class, 'store'])->middleware('throttle:posts')->name('community.posts.store');
    Route::patch('/c/{id}/posts/{post}', [CommunityPostController::class, 'update'])->name('community.posts.update');
    Route::delete('/c/{id}/posts/{post}', [CommunityPostController::class, 'destroy'])->name('community.posts.destroy');
    Route::post('/c/{id}/image', [CommunityMediaController::class, 'uploadImage'])->middleware('throttle:uploads')->name('community.image');
    Route::delete('/c/{id}/image', [CommunityMediaController::class, 'removeImage'])->name('community.image.remove');
    Route::post('/c/{id}/banner', [CommunityMediaController::class, 'uploadBanner'])->middleware('throttle:uploads')->name('community.banner');
    Route::delete('/c/{id}/banner', [CommunityMediaController::class, 'removeBanner'])->name('community.banner.remove');

    // Likes e comentários
    Route::post('/posts/{post}/like', [LikeController::class, 'togglePost'])->middleware('throttle:reactions')->name('posts.like');
    Route::get('/posts/{post}/reactors', [LikeController::class, 'reactorsPost'])->name('posts.reactors');
    Route::post('/posts/{post}/comments', [CommentController::class, 'storePost'])->middleware('throttle:reactions')->name('posts.comments.store');
    Route::post('/community-posts/{post}/like', [LikeController::class, 'toggleCommunityPost'])->middleware('throttle:reactions')->name('community-posts.like');
    Route::get('/community-posts/{post}/reactors', [LikeController::class, 'reactorsCommunityPost'])->name('community-posts.reactors');
    Route::post('/community-posts/{post}/comments', [CommentController::class, 'storeCommunityPost'])->middleware('throttle:reactions')->name('community-posts.comments.store');
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');
    Route::post('/comments/{comment}/like', [LikeController::class, 'toggleComment'])->middleware('throttle:reactions')->name('comments.like');
    Route::post('/comments/{comment}/replies', [CommentController::class, 'storeReply'])->middleware('throttle:reactions')->name('comments.replies.store');

    // Community settings + delete (criador apenas)
    Route::put('/c/{id}/settings', [CommunitySettingsController::class, 'update'])->name('community.update');
    Route::delete('/c/{id}', [CommunitySettingsController::class, 'destroy'])->name('community.delete');

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
    Route::post('/conversations/upload-image', [ConversationController::class, 'uploadImage'])->middleware('throttle:messages')->name('conversations.upload-image');
    Route::post('/conversations/{conversation}/messages', [ConversationController::class, 'storeMessage'])->middleware('throttle:messages')->name('messages.store');
    Route::patch('/messages/{message}', [ConversationController::class, 'updateMessage'])->name('messages.update');
    Route::delete('/messages/{message}', [ConversationController::class, 'destroyMessage'])->name('messages.destroy');
    Route::get('/conversations/{conversation}/poll', [ConversationController::class, 'poll'])->name('conversations.poll');
    Route::post('/conversations/{conversation}/typing', [ConversationController::class, 'typing'])->name('conversations.typing');
    Route::post('/conversations/{conversation}/read', [ConversationController::class, 'markRead'])->name('conversations.read');
    Route::post('/conversations/{conversation}/background', [ConversationController::class, 'updateBackground'])->name('conversations.background');

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

    // Communities list (auth user)
    Route::get('/communities', [CommunityController::class, 'userCommunities'])->name('communities.index');

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

    // Blocking
    Route::post('/users/{username}/block', [BlockController::class, 'store'])->name('users.block');
    Route::delete('/users/{username}/block', [BlockController::class, 'destroy'])->name('users.unblock');

    // Reports
    Route::post('/posts/{post}/report', [ReportController::class, 'storePost'])->middleware('throttle:reports')->name('posts.report');
    Route::post('/community-posts/{post}/report', [ReportController::class, 'storeCommunityPost'])->middleware('throttle:reports')->name('community-posts.report');
    Route::post('/users/{user}/report', [ReportController::class, 'storeUser'])->middleware('throttle:reports')->name('users.report');
    Route::post('/c/{id}/report', [ReportController::class, 'storeCommunity'])->middleware('throttle:reports')->name('community.report');
});

// Admin panel
Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', AdminDashboard::class)->name('dashboard');

    // Users
    Route::get('/users', [AdminUsers::class, 'index'])->name('users');
    Route::patch('/users/{user}/role', [AdminUsers::class, 'updateRole'])->name('users.role');
    Route::delete('/users/{user}', [AdminUsers::class, 'destroy'])->name('users.destroy');

    // Content (posts, community posts, communities)
    Route::get('/posts', [AdminContent::class, 'posts'])->name('posts');
    Route::delete('/posts/{id}/force', [AdminContent::class, 'destroyPost'])->name('posts.destroy');
    Route::post('/posts/{id}/restore', [AdminContent::class, 'restorePost'])->name('posts.restore');
    Route::get('/community-posts', [AdminContent::class, 'communityPosts'])->name('community-posts');
    Route::delete('/community-posts/{id}/force', [AdminContent::class, 'destroyCommunityPost'])->name('community-posts.destroy');
    Route::post('/community-posts/{id}/restore', [AdminContent::class, 'restoreCommunityPost'])->name('community-posts.restore');
    Route::get('/communities', [AdminContent::class, 'communities'])->name('communities');
    Route::delete('/communities/{bubble}', [AdminContent::class, 'destroyCommunity'])->name('communities.destroy');

    // Reports
    Route::get('/reports', [AdminReports::class, 'index'])->name('reports');
    Route::patch('/reports/{report}/resolve', [AdminReports::class, 'resolve'])->name('reports.resolve');
    Route::patch('/reports/{report}/dismiss', [AdminReports::class, 'dismiss'])->name('reports.dismiss');

    // Punishments
    Route::get('/punishments', [AdminPunishments::class, 'index'])->name('punishments');
    Route::post('/punishments', [AdminPunishments::class, 'store'])->name('punishments.store');
    Route::patch('/punishments/{punishment}/revoke', [AdminPunishments::class, 'revoke'])->name('punishments.revoke');

    // Audit Logs
    Route::get('/audit-logs', [AdminAuditLogs::class, 'index'])->name('audit-logs');
    Route::delete('/audit-logs', [AdminAuditLogs::class, 'destroyAll'])->name('audit-logs.destroy-all');
    Route::delete('/audit-logs/{log}', [AdminAuditLogs::class, 'destroy'])->name('audit-logs.destroy');

    // Announcements
    Route::get('/announcements', [AdminAnnouncements::class, 'index'])->name('announcements');
    Route::post('/announcements', [AdminAnnouncements::class, 'store'])->name('announcements.store');
    Route::patch('/announcements/{announcement}/toggle', [AdminAnnouncements::class, 'toggle'])->name('announcements.toggle');
    Route::delete('/announcements/{announcement}', [AdminAnnouncements::class, 'destroy'])->name('announcements.destroy');
});

require __DIR__.'/auth.php';
