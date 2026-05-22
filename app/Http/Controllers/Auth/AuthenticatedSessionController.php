<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status'           => session('status'),
            'punishmentModal'  => session('punishment_modal'),
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        try {
            $request->authenticate();
        } catch (ValidationException $e) {
            AuditLogger::log('auth.login_failed', 'auth', null, [
                'email_masked' => self::maskEmail($request->string('email')),
            ]);
            throw $e;
        }

        $request->session()->regenerate();

        AuditLogger::log('auth.login', 'auth');

        return redirect()->intended(route('bubbles', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        AuditLogger::log('auth.logout', 'auth');

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    private static function maskEmail(string $email): string
    {
        if (! str_contains($email, '@')) {
            return '***';
        }
        [$local, $domain] = explode('@', $email, 2);
        return mb_substr($local, 0, 1) . '***@' . $domain;
    }
}
