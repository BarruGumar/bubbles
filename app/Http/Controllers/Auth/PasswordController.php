<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\PasswordChangeRequested;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $hasPassword = $request->user()->password !== null;

        $validated = $request->validate([
            'current_password' => $hasPassword ? ['required', 'current_password'] : [],
            'password'         => ['required', Password::defaults(), 'confirmed'],
        ]);

        $user = $request->user();

        AuditLogger::log('auth.password_change_requested', 'auth', $user);

        Cache::put("password_change_{$user->id}", Hash::make($validated['password']), now()->addMinutes(15));

        $confirmUrl = URL::temporarySignedRoute(
            'profile.password.confirm',
            now()->addMinutes(15),
            ['user' => $user->id]
        );

        $user->notify(new PasswordChangeRequested($confirmUrl));

        return back()->with('status', 'password-email-sent');
    }

    public function confirm(Request $request): RedirectResponse
    {
        $userId = $request->query('user');
        $pending = Cache::get("password_change_{$userId}");

        if (! $pending) {
            return redirect()->route('profile.edit')->with('status', 'password-link-expired');
        }

        $user = User::findOrFail($userId);
        $user->update(['password' => $pending]);
        Cache::forget("password_change_{$userId}");

        AuditLogger::log('auth.password_changed', 'auth', $user);

        return redirect()->route('profile.edit')->with('status', 'password-changed');
    }
}
