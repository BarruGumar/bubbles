<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $userId      = $request->get('user_id');
        $ip          = $request->get('ip');
        $action      = $request->get('action');
        $category    = $request->get('category');
        $communityId = $request->get('community_id');
        $from        = $request->get('from');
        $to          = $request->get('to');

        $logs = AuditLog::with(['actor', 'targetUser', 'community'])
            ->when($userId, fn ($q) => $q->where(fn ($q2) => $q2->where('actor_id', $userId)->orWhere('target_user_id', $userId)))
            ->when($ip,          fn ($q) => $q->where('ip_address', $ip))
            ->when($action,      fn ($q) => $q->where('action', 'like', "%$action%"))
            ->when($category,    fn ($q) => $q->where('category', $category))
            ->when($communityId, fn ($q) => $q->where('community_id', $communityId))
            ->when($from,        fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to,          fn ($q) => $q->where('created_at', '<=', $to . ' 23:59:59'))
            ->latest('created_at')
            ->paginate(50)
            ->through(fn ($l) => [
                'id'          => $l->id,
                'actor'       => $l->actor ? ['id' => $l->actor->id, 'name' => $l->actor->name, 'username' => $l->actor->username] : null,
                'actor_role'  => $l->actor_role,
                'action'      => $l->action,
                'category'    => $l->category,
                'target_type' => $l->target_type ? class_basename($l->target_type) : null,
                'target_user' => $l->targetUser ? ['id' => $l->targetUser->id, 'name' => $l->targetUser->name] : null,
                'community'   => $l->community ? ['id' => $l->community->id, 'label' => $l->community->label] : null,
                'ip_address'  => $l->ip_address,
                'method'      => $l->method,
                'route_name'  => $l->route_name,
                'metadata'    => $l->metadata,
                'created_at'  => $l->created_at->format('d/m/Y H:i:s'),
            ]);

        return Inertia::render('Admin/AuditLogs', [
            'logs'        => $logs,
            'filters'     => compact('userId', 'ip', 'action', 'category', 'communityId', 'from', 'to'),
            'isSiteOwner' => auth()->user()->isSiteOwner(),
        ]);
    }

    public function destroy(AuditLog $log): RedirectResponse
    {
        abort_unless(auth()->user()->isSiteOwner(), 403);

        $log->delete();

        return back()->with('status', 'Log eliminado.');
    }

    public function destroyAll(): RedirectResponse
    {
        abort_unless(auth()->user()->isSiteOwner(), 403);

        AuditLog::truncate();

        return back()->with('status', 'Todos os logs foram eliminados.');
    }
}
