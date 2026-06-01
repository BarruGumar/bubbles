<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bubble;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use App\Models\UserPunishment;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'isSiteOwner' => auth()->user()->isSiteOwner(),
            'stats' => [
                'users'       => User::count(),
                'posts'       => Post::withTrashed()->count(),
                'communities' => Bubble::count(),
                'reports'     => Report::where('status', 'pending')->count(),
                'punishments' => UserPunishment::active()->count(),
            ],
            'recentReports' => Report::with('reporter')
                ->where('status', 'pending')
                ->latest()
                ->take(5)
                ->get()
                ->map(fn ($r) => [
                    'id'            => $r->id,
                    'reason'        => $r->reason,
                    'type'          => class_basename($r->reportable_type),
                    'reporter_name' => $r->reporter->name ?? '?',
                    'created_at'    => $r->created_at->diffForHumans(),
                ]),
        ]);
    }
}
