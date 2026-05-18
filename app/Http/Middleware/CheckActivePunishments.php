<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckActivePunishments
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($user->isBanned()) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'A tua conta foi permanentemente banida.']);
        }

        if ($user->isSuspended()) {
            if ($request->inertia()) {
                return back()->with('error', 'A tua conta está suspensa e não podes realizar esta ação.');
            }

            abort(403, 'A tua conta está suspensa.');
        }

        return $next($request);
    }
}
