<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
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
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
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
