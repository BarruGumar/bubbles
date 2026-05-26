<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a new email verification notification.
     */
    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        try {
            $request->user()->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            error_log('[Mail] Verification resend failed: ' . $e->getMessage());
            return back()->with('error', 'Falha ao enviar email de verificação. Tenta novamente mais tarde.');
        }

        return back()->with('status', 'verification-link-sent');
    }
}
