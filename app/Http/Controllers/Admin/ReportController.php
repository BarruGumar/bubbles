<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Models\Bubble;
use App\Models\CommunityPost;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->get('status', ReportStatus::Pending->value);
        $type   = $request->get('type', 'all');

        $reports = Report::with([
                'reporter',
                'reportable' => fn (MorphTo $m) => $m->morphWith([
                    Post::class          => ['user'],
                    CommunityPost::class => ['user'],
                ]),
            ])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($type === 'post',           fn ($q) => $q->where('reportable_type', Post::class))
            ->when($type === 'community_post', fn ($q) => $q->where('reportable_type', CommunityPost::class))
            ->when($type === 'user',           fn ($q) => $q->where('reportable_type', User::class))
            ->when($type === 'community',      fn ($q) => $q->where('reportable_type', Bubble::class))
            ->latest()
            ->paginate(20)
            ->through(function ($r) {
                $reportable = $r->reportable;
                return [
                    'id'                 => $r->id,
                    'reason'             => $r->reason,
                    'status'             => $r->status,
                    'admin_note'         => $r->admin_note,
                    'type'               => class_basename($r->reportable_type),
                    'reporter_name'      => $r->reporter?->name ?? '–',
                    'reportable_content' => match (true) {
                        $reportable instanceof User   => $reportable->name . ' (@' . $reportable->username . ')',
                        $reportable instanceof Bubble => 'Comunidade: ' . ($reportable->title ?? $reportable->label),
                        default                       => $reportable?->content ?? null,
                    },
                    'reportable_author'  => ($reportable instanceof Post || $reportable instanceof CommunityPost)
                        ? ($reportable->user?->name ?? null)
                        : null,
                    'reportable_user_id' => ($reportable instanceof Post || $reportable instanceof CommunityPost)
                        ? ($reportable->user_id ?? null)
                        : ($reportable instanceof User ? $reportable->id : null),
                    'created_at' => $r->created_at->diffForHumans(),
                ];
            });

        return Inertia::render('Admin/Reports', [
            'reports'      => $reports,
            'statusFilter' => $status,
            'typeFilter'   => $type,
        ]);
    }

    public function resolve(Request $request, Report $report): RedirectResponse
    {
        $data = $request->validate(['admin_note' => 'nullable|string|max:500']);

        $report->update(['status' => ReportStatus::Resolved->value, 'admin_note' => $data['admin_note'] ?? null]);

        AuditLogger::log('report.resolve', 'moderation', $report, ['admin_note' => $data['admin_note'] ?? null]);

        return back()->with('status', 'Denúncia resolvida.');
    }

    public function dismiss(Request $request, Report $report): RedirectResponse
    {
        $data = $request->validate(['admin_note' => 'nullable|string|max:500']);

        $report->update(['status' => ReportStatus::Dismissed->value, 'admin_note' => $data['admin_note'] ?? null]);

        AuditLogger::log('report.dismiss', 'moderation', $report, ['admin_note' => $data['admin_note'] ?? null]);

        return back()->with('status', 'Denúncia dispensada.');
    }
}
