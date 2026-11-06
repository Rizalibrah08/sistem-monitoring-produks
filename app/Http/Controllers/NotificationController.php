<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    /**
     * Pusat notifikasi staf: tugas dan peringatan terbaru dengan tautan ke layar terkait (FR-85).
     */
    public function index(Request $request): Response
    {
        $notifications = $request->user()->notifications()->latest()->paginate(30)->withQueryString()
            ->through(fn (DatabaseNotification $notification): array => [
                'id' => $notification->id,
                'kind' => $notification->data['kind'] ?? null,
                'title' => $notification->data['title'] ?? '',
                'body' => $notification->data['body'] ?? '',
                'ticket_code' => $notification->data['ticket_code'] ?? null,
                'read' => $notification->read_at !== null,
                'created_at' => $notification->created_at?->format('d/m/Y H:i'),
                'open_url' => route('notifications.open', $notification->id, absolute: false),
            ]);

        return Inertia::render('Backend/Notifications', [
            'notifications' => $notifications,
            'unread' => $request->user()->unreadNotifications()->count(),
            'urls' => ['read_all' => route('notifications.read-all', absolute: false)],
        ]);
    }

    /** Tandai dibaca lalu buka layar terkait. */
    public function open(Request $request, string $notification): RedirectResponse
    {
        $entry = $request->user()->notifications()->findOrFail($notification);
        $entry->markAsRead();
        $url = (string) ($entry->data['url'] ?? '');

        return redirect(str_starts_with($url, '/') && ! str_starts_with($url, '//') ? $url : route('notifications.index'));
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('status', 'Semua notifikasi ditandai sudah dibaca.');
    }
}
