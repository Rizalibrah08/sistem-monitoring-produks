<?php

namespace App\Http\Controllers;

use App\ContractStatus;
use App\DocumentStatus;
use App\InvoiceStatus;
use App\Models\Activity;
use App\Models\Contract;
use App\Models\DocumentType;
use App\Models\Invoice;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TaskList;
use App\StaffRole;
use App\TicketStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Dasbor per role: ringkasan angka, tugas teratas, dan untuk Manajer sebaran tiket per
     * fase dan tahap (FR-41, FR-43, FR-84).
     */
    public function __invoke(Request $request, TaskList $tasks): Response
    {
        $user = $request->user();
        $role = $user->getRoleNames()->first();
        $accounts = null;

        if ($user->can('users.manage')) {
            $summary = User::query()->selectRaw('COUNT(*) AS total, COALESCE(SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END), 0) AS active')->first();
            $accounts = [
                'total' => (int) $summary->total,
                'active' => (int) $summary->active,
                'inactive' => (int) $summary->total - (int) $summary->active,
                'activities_today' => Activity::query()->where('created_at', '>=', today())->count(),
            ];
        }

        return Inertia::render('Backend/Dashboard', [
            'accounts' => $accounts,
            'today' => now()->locale('id')->translatedFormat('l, d F Y'),
            'stats' => $accounts === null ? $this->stats($user, $role) : null,
            'tasks' => $user->can('tickets.view') ? $tasks->for($user)->take(8)->values() : [],
            'breakdown' => $role === StaffRole::Manager->value ? $this->breakdown() : null,
            'urls' => ['tasks' => route('tasks.index', absolute: false)],
        ]);
    }

    /** @return list<array{key: string, label: string, value: int|string|null, detail: string}> */
    private function stats(User $user, ?string $role): array
    {
        if ($role === StaffRole::Finance->value) {
            $unpaid = Invoice::query()->where('status', '!=', InvoiceStatus::Paid);

            return [
                ['key' => 'outstanding', 'label' => 'Total piutang', 'value' => 'Rp'.number_format((int) (clone $unpaid)->sum('amount'), 0, ',', '.'), 'detail' => 'Tagihan belum lunas'],
                ['key' => 'unpaid', 'label' => 'Tagihan belum lunas', 'value' => (clone $unpaid)->count(), 'detail' => 'Termasuk tiket yang sudah ditutup'],
                ['key' => 'paid_month', 'label' => 'Lunas bulan ini', 'value' => Invoice::query()->where('status', InvoiceStatus::Paid)->where('paid_at', '>=', now()->startOfMonth())->count(), 'detail' => 'Dikonfirmasi Midtrans'],
                ['key' => 'overdue', 'label' => 'Lewat jatuh tempo', 'value' => (clone $unpaid)->whereDate('due_date', '<', today())->count(), 'detail' => 'Perlu diingatkan'],
            ];
        }

        $tickets = Ticket::query()->visibleTo($user)->where('status', TicketStatus::Active);

        if ($role === StaffRole::Marketing->value) {
            return [
                ['key' => 'prospects', 'label' => 'Prospek baru', 'value' => null, 'detail' => 'Menunggu integrasi WhatsApp'],
                ['key' => 'messages', 'label' => 'Pesan belum dibalas', 'value' => null, 'detail' => 'Menunggu integrasi WhatsApp'],
                ['key' => 'contracts', 'label' => 'Kontrak menunggu tanda tangan', 'value' => Contract::query()->whereIn('status', [ContractStatus::Sent, ContractStatus::Signed])->whereIn('ticket_id', (clone $tickets)->select('id'))->count(), 'detail' => 'Dikirim atau perlu dikonfirmasi'],
                ['key' => 'waiting_client', 'label' => 'Tiket menunggu klien', 'value' => (clone $tickets)->whereHas('documents', fn (Builder $query) => $query->whereIn('status', [DocumentStatus::Missing, DocumentStatus::NeedsRevision])->whereHas('documentType', fn (Builder $query) => $query->where('pic_role', DocumentType::CLIENT_PIC)))->count(), 'detail' => 'Dokumen klien belum lengkap'],
            ];
        }

        return [
            ['key' => 'active', 'label' => 'Tiket aktif', 'value' => (clone $tickets)->count(), 'detail' => 'Sedang berjalan'],
            ['key' => 'late', 'label' => 'Terlambat SLA', 'value' => (clone $tickets)->whereHas('stageLogs', fn (Builder $query) => $query->whereNull('finished_at')->whereNotNull('overdue_at'))->count(), 'detail' => 'Tahap melewati tenggat'],
            ['key' => 'waiting_bpom', 'label' => 'Menunggu BPOM', 'value' => (clone $tickets)->whereHas('currentStage', fn (Builder $query) => $query->where('waits_for_external', true))->count(), 'detail' => 'Tidak dihitung dalam SLA'],
            ['key' => 'review', 'label' => 'Perlu ditinjau', 'value' => (clone $tickets)->whereHas('reviews', fn (Builder $query) => $query->whereNull('resolved_at'))->count(), 'detail' => 'Dokumen bersama berubah'],
        ];
    }

    /** @return list<array{phase: string, stage: string, count: int}> */
    private function breakdown(): array
    {
        return Ticket::query()->where('status', TicketStatus::Active)->with('currentStage')->get()
            ->groupBy(fn (Ticket $ticket): string => $ticket->phase->label().'|'.($ticket->currentStage?->name ?? 'Tanpa tahap'))
            ->map(function ($group, string $key): array {
                [$phase, $stage] = explode('|', $key);

                return ['phase' => $phase, 'stage' => $stage, 'count' => $group->count(), 'order' => $group->first()->currentStage?->sort_order ?? 0];
            })
            ->sortBy('order')->values()
            ->map(fn (array $row): array => collect($row)->except('order')->all())
            ->all();
    }
}
