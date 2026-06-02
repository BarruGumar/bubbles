<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(): Response
    {
        $notifications = auth()->user()
            ->notifications()
            ->where('type', '!=', \App\Notifications\MessageReceived::class)
            ->latest()
            ->paginate(30)
            ->through(fn ($n) => [
                'id'         => $n->id,
                'type'       => $n->data['type'] ?? 'generic',
                'message'    => $n->data['message'] ?? '',
                'data'       => $n->data,
                'read'       => ! is_null($n->read_at),
                'created_at' => $n->created_at->diffForHumans(),
                'url'        => $n->data['url'] ?? null,
            ]);

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
        ]);
    }

    public function markRead(string $id): RedirectResponse
    {
        auth()->user()
            ->notifications()
            ->where('id', $id)
            ->first()
            ?->markAsRead();

        \Illuminate\Support\Facades\Cache::forget('user:' . auth()->id() . ':badge:notifications');

        return back();
    }

    public function markAllRead(): RedirectResponse
    {
        auth()->user()->unreadNotifications->markAsRead();

        \Illuminate\Support\Facades\Cache::forget('user:' . auth()->id() . ':badge:notifications');

        return back();
    }

    public function destroy(string $id): RedirectResponse
    {
        auth()->user()
            ->notifications()
            ->where('id', $id)
            ->delete();

        \Illuminate\Support\Facades\Cache::forget('user:' . auth()->id() . ':badge:notifications');

        return back();
    }

    public function destroyAll(): RedirectResponse
    {
        auth()->user()->notifications()->delete();

        \Illuminate\Support\Facades\Cache::forget('user:' . auth()->id() . ':badge:notifications');

        return back();
    }
}
