<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CheckActivePunishments
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Cache active punishment types — replaces 3 DB queries per request with 0-1.
        // 30s TTL: fast enough for bans to take effect, cheap enough to not block requests.
        $activeTypes = Cache::remember("user:{$user->id}:active_punishments", 30, function () use ($user) {
            if ($user->role === 'banned') {
                return ['ban'];
            }
            if ($user->role === 'suspended') {
                return ['suspension'];
            }
            return $user->punishments()->active()->pluck('type')->unique()->values()->toArray();
        });

        if (in_array('ban', $activeTypes)) {
            $ban = $user->punishments()->active()->ofType('ban')->latest()->first();
            Cache::forget("user:{$user->id}:active_punishments");
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/')->with('punishment_modal', [
                'type'   => 'ban',
                'reason' => $ban?->reason,
            ]);
        }

        $isWrite = ! in_array($request->method(), ['GET', 'HEAD'])
            && ! $request->routeIs('punishment.acknowledge');

        if (in_array('suspension', $activeTypes) && $isWrite) {
            if ($request->inertia()) {
                return back()->with('error', 'A tua conta está suspensa e não podes realizar esta ação.');
            }
            abort(403, 'A tua conta está suspensa.');
        }

        if (in_array('mute', $activeTypes) && $isWrite) {
            if ($request->inertia()) {
                return back()->with('error', 'Estás em silêncio global e não podes realizar esta ação.');
            }
            abort(403, 'Estás em silêncio global.');
        }

        return $next($request);
    }
}
