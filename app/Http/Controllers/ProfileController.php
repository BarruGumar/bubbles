<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Friend;
use App\Models\User;
use App\Support\StoresImages;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    use StoresImages;

    public function show(string $username): Response
    {
        $profileUser = User::where('username', $username)->firstOrFail();
        $isOwn = auth()->check() && auth()->id() === $profileUser->id;

        $userId = auth()->id();

        $paginated = $profileUser->posts()
            ->withCount('likes')
            ->with([
                'likes' => fn ($q) => $q->where('user_id', $userId ?? 0),
                'comments' => fn ($q) => $q->with('user')->orderBy('created_at')->limit(5),
            ])
            ->latest()
            ->cursorPaginate(12);

        $posts = $paginated->getCollection()->map(fn ($p) => [
            'id' => $p->id,
            'content' => $p->content,
            'image' => $p->image,
            'video' => $p->video,
            'created_at' => $p->created_at->diffForHumans(),
            'likes_count' => $p->likes_count,
            'is_liked' => $p->likes->isNotEmpty(),
            'user_reaction' => $p->likes->first()?->type ?? null,
            'comments' => $p->comments->map(fn ($c) => [
                'id' => $c->id,
                'content' => $c->content,
                'created_at' => $c->created_at->diffForHumans(),
                'is_own' => $userId && $c->user_id === $userId,
                'author' => [
                    'id' => $c->user->id,
                    'name' => $c->user->name,
                    'username' => $c->user->username,
                    'avatar' => $c->user->avatar,
                    'avatar_color' => $c->user->avatar_color ?? '#009ac7',
                ],
            ])->values(),
        ])->values();

        $friendStatus = null;
        $friendId = null;

        if (auth()->check() && ! $isOwn) {
            $record = Friend::where(function ($q) use ($profileUser) {
                $q->where('user_id', auth()->id())->where('friend_id', $profileUser->id);
            })->orWhere(function ($q) use ($profileUser) {
                $q->where('user_id', $profileUser->id)->where('friend_id', auth()->id());
            })->first();

            if (! $record) {
                $friendStatus = 'none';
            } elseif ($record->status === 'pending' && $record->user_id === auth()->id()) {
                $friendStatus = 'pending_sent';
                $friendId = $record->id;
            } elseif ($record->status === 'pending') {
                $friendStatus = 'pending_received';
                $friendId = $record->id;
            } else {
                $friendStatus = 'accepted';
                $friendId = $record->id;
            }
        }

        $communities = $profileUser->communities()->get()->map(fn ($b) => [
            'id' => $b->id,
            'label' => $b->label,
            'title' => $b->community_title ?: $b->label,
            'color' => $b->color ?? '#009ac7',
            'image' => $b->community_image,
        ])->values();

        $friendRecords = Friend::where('status', 'accepted')
            ->where(function ($q) use ($profileUser) {
                $q->where('user_id', $profileUser->id)
                    ->orWhere('friend_id', $profileUser->id);
            })
            ->with(['user', 'friend'])
            ->get();

        $profileFriends = $friendRecords->map(function ($f) use ($profileUser) {
            $u = $f->user_id === $profileUser->id ? $f->friend : $f->user;

            return [
                'id' => $u->id,
                'name' => $u->name,
                'username' => $u->username,
                'avatar' => $u->avatar,
                'avatar_color' => $u->avatar_color ?? '#009ac7',
            ];
        })->values();

        return Inertia::render('Profile/Show', [
            'profileUser' => [
                'id' => $profileUser->id,
                'name' => $profileUser->name,
                'username' => $profileUser->username,
                'bio' => $profileUser->bio,
                'avatar_color' => $profileUser->avatar_color ?? '#009ac7',
                'avatar' => $profileUser->avatar,
                'banner' => $profileUser->banner,
                'created_at' => $profileUser->created_at->format('M Y'),
                'posts_count' => $profileUser->posts()->count(),
            ],
            'posts' => $posts,
            'nextCursor' => $paginated->nextCursor()?->encode(),
            'hasMorePosts' => $paginated->hasMorePages(),
            'communities' => $communities,
            'profileFriends' => $profileFriends,
            'isOwn' => $isOwn,
            'friendStatus' => $friendStatus,
            'friendId' => $friendId,
        ]);
    }

    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    public function uploadAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'max:2048'],
        ]);

        $user = $request->user();
        $this->deleteCloudinaryImage($user->avatar_public_id);

        ['url' => $url, 'public_id' => $pid] = $this->storeImageWithMeta($request->file('avatar'), 'bubbles/avatars', [
            'public_id' => 'user_'.$user->id,
            'overwrite' => true,
            'transformation' => ['width' => 300, 'height' => 300, 'crop' => 'fill', 'gravity' => 'face', 'fetch_format' => 'auto', 'quality' => 'auto'],
        ]);

        $user->update(['avatar' => $url, 'avatar_public_id' => $pid]);

        return back()->with('status', 'avatar-updated');
    }

    public function uploadBanner(Request $request): RedirectResponse
    {
        $request->validate([
            'banner' => ['required', 'image', 'max:4096'],
        ]);

        $user = $request->user();
        $this->deleteCloudinaryImage($user->banner_public_id);

        ['url' => $url, 'public_id' => $pid] = $this->storeImageWithMeta($request->file('banner'), 'bubbles/banners', [
            'public_id' => 'banner_'.$user->id,
            'overwrite' => true,
            'transformation' => ['width' => 1200, 'height' => 400, 'crop' => 'fill', 'fetch_format' => 'auto', 'quality' => 'auto'],
        ]);

        $user->update(['banner' => $url, 'banner_public_id' => $pid]);

        return back()->with('status', 'banner-updated');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
