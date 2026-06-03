<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable) {
            return redirect()->route('login')->withErrors(['email' => 'Não foi possível autenticar com o Google. Tenta novamente.']);
        }

        // 1. Já tem conta ligada ao Google → login directo
        $user = User::where('google_id', $googleUser->getId())->first();

        if ($user) {
            return $this->loginAndRedirect($user);
        }

        // 2. Existe conta com o mesmo email → liga ao Google
        $user = User::where('email', $googleUser->getEmail())->first();

        if ($user) {
            $user->update(['google_id' => $googleUser->getId()]);
            return $this->loginAndRedirect($user);
        }

        // 3. Conta nova → criar
        $colors = ['#009ac7', '#4ebcff', '#2ea87e', '#e07b4a', '#9b6bdf', '#c74a6b'];

        $user = User::create([
            'name'              => $googleUser->getName(),
            'email'             => $googleUser->getEmail(),
            'google_id'         => $googleUser->getId(),
            'username'          => User::generateUsername($googleUser->getName()),
            'avatar'            => $googleUser->getAvatar(),
            'avatar_color'      => $colors[array_rand($colors)],
            'password'          => null,
            // Google já verificou o email
            'email_verified_at' => now(),
        ]);

        AuditLogger::log('auth.registered', 'auth', $user, ['provider' => 'google']);

        return $this->loginAndRedirect($user);
    }

    private function loginAndRedirect(User $user): RedirectResponse
    {
        AuditLogger::log('auth.login', 'auth', $user, ['provider' => 'google']);

        Auth::login($user, remember: true);

        return redirect()->intended(route('bubbles'));
    }
}
