<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $colors = ['#009ac7', '#4ebcff', '#2ea87e', '#e07b4a', '#9b6bdf', '#c74a6b'];
        $color = $colors[array_rand($colors)];
        $username = $this->generateUsername($request->name);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'username' => $username,
            'avatar_color' => $color,
        ]);

        try {
            event(new Registered($user));
        } catch (\Exception $e) {
            error_log('[Mail] Registration verification email failed: ' . $e->getMessage());
        }

        Auth::login($user);

        AuditLogger::log('auth.registered', 'auth', $user);

        return redirect(route('bubbles', absolute: false));
    }

    private function generateUsername(string $name): string
    {
        $base = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name));
        $base = $base ?: 'user';
        $base = substr($base, 0, 18);
        $candidate = $base;
        $i = 1;

        while (User::where('username', $candidate)->exists()) {
            $candidate = $base.$i++;
        }

        return $candidate;
    }
}
