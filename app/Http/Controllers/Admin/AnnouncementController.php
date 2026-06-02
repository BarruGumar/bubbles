<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AnnouncementType;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    public function index(): Response
    {
        $announcements = Announcement::with('creator:id,name')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($a) => [
                'id'         => $a->id,
                'title'      => $a->title,
                'body'       => $a->body,
                'type'       => $a->type,
                'is_active'  => $a->is_active,
                'expires_at' => $a->expires_at?->toIso8601String(),
                'created_at' => $a->created_at->toIso8601String(),
                'creator'    => ['name' => $a->creator->name],
            ]);

        return Inertia::render('Admin/Announcements', ['announcements' => $announcements]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title'      => 'required|string|max:200',
            'body'       => 'required|string|max:2000',
            'type'       => ['required', Rule::enum(AnnouncementType::class)],
            'expires_at' => 'nullable|date|after:now',
        ]);

        Announcement::create([...$data, 'created_by' => auth()->id(), 'is_active' => true]);

        Cache::forget('announcements:active');
        AuditLogger::log('announcement.create', 'admin', null, ['title' => $data['title']]);

        return back()->with('status', 'Aviso criado com sucesso.');
    }

    public function toggle(Announcement $announcement): RedirectResponse
    {
        $announcement->update(['is_active' => ! $announcement->is_active]);

        Cache::forget('announcements:active');

        return back()->with('status', $announcement->is_active ? 'Aviso ativado.' : 'Aviso desativado.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        AuditLogger::log('announcement.delete', 'admin', null, ['title' => $announcement->title]);
        $announcement->delete();

        Cache::forget('announcements:active');

        return back()->with('status', 'Aviso eliminado.');
    }
}
