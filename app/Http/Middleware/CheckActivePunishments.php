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
            $ban = $user->punishments()->active()->ofType('ban')->latest()->first();

            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/')->with('punishment_modal', [
                'type'   => 'ban',
                'reason' => $ban?->reason,
            ]);
        }

        // Suspended users can browse (GET) but cannot perform write actions.
        // The punishment.acknowledge route is exempt so they can dismiss notifications.
        if ($user->isSuspended()
            && ! in_array($request->method(), ['GET', 'HEAD'])
            && ! $request->routeIs('punishment.acknowledge')
        ) {
            if ($request->inertia()) {
                return back()->with('error', 'A tua conta está suspensa e não podes realizar esta ação.');
            }

            abort(403, 'A tua conta está suspensa.');
        }

        if ($user->isGloballyMuted()
            && ! in_array($request->method(), ['GET', 'HEAD'])
            && ! $request->routeIs('punishment.acknowledge')
        ) {
            if ($request->inertia()) {
                return back()->with('error', 'Estás em silêncio global e não podes realizar esta ação.');
            }

            abort(403, 'Estás em silêncio global.');
        }

        return $next($request);
    }
}
