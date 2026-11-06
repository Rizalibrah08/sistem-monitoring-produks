<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi alur kerja untuk staf (F11): tenggat, keterlambatan, dokumen, kontrak, dan
 * pembayaran, dengan tautan langsung ke layar terkait (FR-85). Saat ini disimpan di pusat
 * notifikasi aplikasi; saluran WhatsApp nomor layanan ditambahkan setelah gateway tersedia.
 */
class WorkflowNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $kind,
        public string $title,
        public string $body,
        public string $url,
        public ?string $ticketCode = null,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{kind: string, title: string, body: string, url: string, ticket_code: ?string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'ticket_code' => $this->ticketCode,
        ];
    }
}
