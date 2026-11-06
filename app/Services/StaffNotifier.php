<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\DocumentType;
use App\Models\Invoice;
use App\Models\Ticket;
use App\Models\TicketDocument;
use App\Models\TicketStageLog;
use App\Models\User;
use App\Notifications\WorkflowNotification;
use App\StaffRole;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class StaffNotifier
{
    /** @var array<string, list<string>> role penanggung jawab tiap tahap untuk pengingat tenggat */
    private const STAGE_ROLES = [
        'pengembangan_sampel' => ['rnd'],
        'kontrak' => ['marketing'],
        'uji_laboratorium' => ['ra', 'rnd'],
    ];

    /**
     * Dokumen gagal validasi atau dikembalikan verifikator: PIC dokumen diberi tahu (FR-26, FR-71).
     */
    public function documentReturned(TicketDocument $item, string $reason, ?User $except = null): void
    {
        $item->loadMissing(['ticket.client.marketingUser', 'documentType']);
        $pic = $item->documentType->pic_role;
        $recipients = $pic === DocumentType::CLIENT_PIC ? $this->marketingOf($item->ticket) : $this->role($pic ?? StaffRole::Regulatory->value);

        $this->send($recipients, $except, 'dokumen_dikembalikan', "{$item->documentType->name} perlu revisi",
            "{$item->ticket->code} · {$reason}", route('tickets.show', $item->ticket_id, absolute: false), $item->ticket->code);
    }

    /**
     * Dokumen lolos validasi otomatis menunggu verifikasi: QA/QC untuk dokumen mutu, RA untuk
     * lainnya (US-05).
     */
    public function documentAwaitingReview(TicketDocument $item, ?User $except = null): void
    {
        $item->loadMissing(['ticket', 'documentType']);
        $quality = $item->needsContentReview();

        $this->send($this->role($quality ? StaffRole::Quality->value : StaffRole::Regulatory->value), $except, 'dokumen_menunggu_verifikasi',
            "{$item->documentType->name} menunggu verifikasi", "{$item->ticket->code} · {$item->ticket->product_name}",
            route($quality ? 'quality.index' : 'verification.index', absolute: false), $item->ticket->code);
    }

    public function contractSigned(Contract $contract): void
    {
        $ticket = $contract->ticket;

        $this->send($this->marketingOf($ticket), null, 'kontrak_ditandatangani', "Kontrak {$contract->number} sudah ditandatangani klien",
            "{$ticket->code} · konfirmasi kontrak untuk membuka fase pra-registrasi.", route('tickets.show', $ticket, absolute: false), $ticket->code);
    }

    public function invoicePaid(Invoice $invoice): void
    {
        $ticket = $invoice->ticket;

        $this->send($this->role(StaffRole::Finance->value)->merge($this->marketingOf($ticket)), null, 'tagihan_lunas', "Tagihan {$invoice->number} lunas",
            "{$ticket->code} · {$invoice->label()} Rp".number_format($invoice->amount, 0, ',', '.'), route('tickets.show', $ticket, absolute: false), $ticket->code);
    }

    public function nieRecorded(Ticket $ticket, ?User $except = null): void
    {
        $this->send($this->marketingOf($ticket)->merge($this->role(StaffRole::Finance->value)), $except, 'nie_terbit', "NIE {$ticket->nie_number} terbit",
            "{$ticket->code} · {$ticket->product_name} selesai; tagihan pelunasan diterbitkan.", route('tickets.show', $ticket, absolute: false), $ticket->code);
    }

    public function ticketNeedsReview(Ticket $ticket, string $message): void
    {
        $this->send($this->role(StaffRole::Regulatory->value), null, 'perlu_ditinjau', "{$ticket->code} perlu ditinjau",
            $message, route('tickets.show', $ticket, absolute: false), $ticket->code);
    }

    /** Pengingat satu hari kerja sebelum tenggat tahap (FR-36). */
    public function stageDueSoon(TicketStageLog $log): void
    {
        $ticket = $log->ticket;

        $this->send($this->stageOwners($log), null, 'tenggat_tahap', "Tenggat {$log->stage->name} besok",
            "{$ticket->code} · tenggat {$log->due_at->format('d/m/Y H:i')}", route('tickets.show', $ticket, absolute: false), $ticket->code);
    }

    /**
     * Tahap melewati tenggat: eskalasi ke Manajer bila penyebabnya staf, atau informasi ke
     * Marketing bila menunggu klien (FR-37, BR-11).
     */
    public function stageOverdue(TicketStageLog $log): void
    {
        $ticket = $log->ticket;
        $byClient = $log->delay_cause === DelayCause::CLIENT;
        $recipients = $byClient ? $this->marketingOf($ticket) : $this->role(StaffRole::Manager->value)->merge($this->stageOwners($log));

        $this->send($recipients, null, $byClient ? 'menunggu_klien' : 'eskalasi_sla', "{$log->stage->name} melewati tenggat",
            "{$ticket->code} · ".($byClient ? 'menunggu bagian klien; ingatkan klien.' : 'tertunda di pihak internal.'),
            route('tickets.show', $ticket, absolute: false), $ticket->code);
    }

    /** Pengingat tenggat BPOM untuk RA, juga Manajer pada TD3 (FR-36, FR-89, FR-90). */
    public function bpomDeadline(Ticket $ticket, string $label, string $deadline, int $daysLeft, bool $rejectionRisk = false): void
    {
        $recipients = $this->role(StaffRole::Regulatory->value);

        if ($rejectionRisk) {
            $recipients = $recipients->merge($this->role(StaffRole::Manager->value));
        }

        $this->send($recipients, null, 'tenggat_bpom', "{$label}: {$daysLeft} hari kerja lagi",
            "{$ticket->code} · tenggat {$deadline}".($rejectionRisk ? ' · risiko ditolak bila tidak terpenuhi.' : ''),
            route('tickets.show', $ticket, absolute: false), $ticket->code);
    }

    /** @return Collection<int, User> */
    private function stageOwners(TicketStageLog $log): Collection
    {
        return collect(self::STAGE_ROLES[$log->stage->code] ?? [StaffRole::Regulatory->value])
            ->flatMap(fn (string $role): Collection => $role === StaffRole::Marketing->value ? $this->marketingOf($log->ticket) : $this->role($role));
    }

    /** @return Collection<int, User> */
    private function role(string $role): Collection
    {
        return User::query()->whereHas('roles', fn ($query) => $query->where('name', $role))->where('is_active', true)->get();
    }

    /**
     * PIC Marketing klien, atau Manajer bila PIC sedang cuti atau nonaktif (BR-12).
     *
     * @return Collection<int, User>
     */
    private function marketingOf(Ticket $ticket): Collection
    {
        $marketing = $ticket->client->marketingUser;

        return $marketing !== null && $marketing->is_active && ! $marketing->isUnavailable()
            ? collect([$marketing])
            : $this->role(StaffRole::Manager->value);
    }

    /** @param  Collection<int, User>  $recipients */
    private function send(Collection $recipients, ?User $except, string $kind, string $title, string $body, string $url, ?string $ticketCode): void
    {
        $recipients = $recipients->unique('id')->reject(fn (User $user): bool => $user->id === $except?->id)->values();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new WorkflowNotification($kind, $title, $body, $url, $ticketCode));
        }
    }
}
