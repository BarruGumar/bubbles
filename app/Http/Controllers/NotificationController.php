<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
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
            ->cursorPaginate(30)
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
        $authId = auth()->id();

        auth()->user()
            ->notifications()
            ->where('id', $id)
            ->first()
            ?->markAsRead();

        Cache::forget("user:{$authId}:badge:notifications");

        return back();
    }

    public function markAllRead(): RedirectResponse
    {
        $authId = auth()->id();

        auth()->user()->unreadNotifications->markAsRead();

        Cache::forget("user:{$authId}:badge:notifications");

        return back();
    }

    public function destroy(string $id): RedirectResponse
    {
        $authId = auth()->id();

        auth()->user()
            ->notifications()
            ->where('id', $id)
            ->delete();

        Cache::forget("user:{$authId}:badge:notifications");

        return back();
    }

    public function destroyAll(): RedirectResponse
    {
        $authId = auth()->id();

        auth()->user()->notifications()->delete();

        Cache::forget("user:{$authId}:badge:notifications");

        return back();
    }
}
