<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function show(string $username): Response
    {
        $profileUser = User::where('username', $username)->firstOrFail();
        $posts = $profileUser->posts()->latest()->get()->map(fn ($p) => [
            'id'         => $p->id,
            'content'    => $p->content,
            'created_at' => $p->created_at->diffForHumans(),
        ]);

        return Inertia::render('Profile/Show', [
            'profileUser' => [
                'id'           => $profileUser->id,
                'name'         => $profileUser->name,
                'username'     => $profileUser->username,
                'bio'          => $profileUser->bio,
                'avatar_color' => $profileUser->avatar_color ?? '#009ac7',
                'avatar'       => $profileUser->avatar,
                'banner'       => $profileUser->banner,
                'created_at'   => $profileUser->created_at->format('M Y'),
                'posts_count'  => $posts->count(),
            ],
            'posts' => $posts,
            'isOwn' => auth()->check() && auth()->id() === $profileUser->id,
        ]);
    }

    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status'          => session('status'),
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

        $url = $this->storeImage($request->file('avatar'), 'bubbles/avatars', [
            'public_id'      => 'user_' . $request->user()->id,
            'overwrite'      => true,
            'transformation' => ['width'=>300,'height'=>300,'crop'=>'fill','gravity'=>'face','fetch_format'=>'auto','quality'=>'auto'],
        ]);

        $request->user()->update(['avatar' => $url]);

        return back()->with('status', 'avatar-updated');
    }

    public function uploadBanner(Request $request): RedirectResponse
    {
        $request->validate([
            'banner' => ['required', 'image', 'max:4096'],
        ]);

        $url = $this->storeImage($request->file('banner'), 'bubbles/banners', [
            'public_id'      => 'banner_' . $request->user()->id,
            'overwrite'      => true,
            'transformation' => ['width'=>1200,'height'=>400,'crop'=>'fill','fetch_format'=>'auto','quality'=>'auto'],
        ]);

        $request->user()->update(['banner' => $url]);

        return back()->with('status', 'banner-updated');
    }

    private function storeImage($file, string $folder, array $cloudinaryOptions = []): string
    {
        $key = env('CLOUDINARY_API_KEY', '');
        if (!empty($key) && $key !== 'API_KEY') {
            return Cloudinary::upload($file->getRealPath(), array_merge(
                ['folder' => $folder, 'fetch_format' => 'auto', 'quality' => 'auto'],
                $cloudinaryOptions
            ))->getSecurePath();
        }

        $path = $file->store($folder, 'public');
        return '/storage/' . $path;
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
