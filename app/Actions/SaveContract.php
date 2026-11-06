<?php

namespace App\Actions;

use App\ContractStatus;
use App\Jobs\GenerateContractDocuments;
use App\Models\Contract;
use App\Models\Ticket;
use App\Models\User;
use App\SampleDecision;
use App\Services\AuditLogger;
use App\TicketStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveContract
{
    /** @var list<string> */
    private const TERMS = ['document_template_id', 'service_value', 'dp_percent', 'payment_due_days'];

    public function __construct(private AuditLogger $audit) {}

    /**
     * Buat kontrak (MoU) berkode KTR/TAHUN/NOMOR untuk tiket aktif yang sampelnya sudah
     * disetujui (FR-73), lalu antrekan pembuatan berkas Word dan PDF dari templat.
     *
     * @param  array{document_template_id: int|string, service_value: int|string, dp_percent: int|string, payment_due_days: int|string}  $data
     */
    public function create(Ticket $ticket, array $data, User $actor): Contract
    {
        return DB::transaction(function () use ($ticket, $data, $actor): Contract {
            $ticket = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();

            if ($ticket->status !== TicketStatus::Active || $ticket->contract()->exists()) {
                throw ValidationException::withMessages(['ticket_id' => 'Tiket ini sudah memiliki kontrak atau tidak aktif.']);
            }

            if (! $ticket->sampleApprovals()->where('decision', SampleDecision::Approved)->exists()) {
                throw ValidationException::withMessages(['ticket_id' => 'Kontrak dibuat setelah sampel disetujui klien.']);
            }

            $contract = $ticket->contract()->create([
                ...$this->terms($data),
                'number' => $this->nextNumber(),
                'status' => ContractStatus::Draft,
                'created_by' => $actor->id,
            ]);

            $this->audit->record('contract.created', $contract, after: [
                'number' => $contract->number,
                ...$this->terms($data),
            ], actor: $actor, ticketCode: $ticket->code);

            GenerateContractDocuments::dispatch($contract->id)->afterCommit();

            return $contract;
        }, attempts: 5);
    }

    /**
     * Ubah nilai jasa, termin, atau templat kontrak yang masih draf; setiap perubahan
     * dicatat (BR-15) dan berkasnya dibuat ulang.
     *
     * @param  array{document_template_id: int|string, service_value: int|string, dp_percent: int|string, payment_due_days: int|string}  $data
     */
    public function update(Contract $contract, array $data, User $actor): Contract
    {
        if ($contract->status !== ContractStatus::Draft) {
            throw ValidationException::withMessages(['service_value' => 'Hanya kontrak berstatus draf yang dapat diubah.']);
        }

        return DB::transaction(function () use ($contract, $data, $actor): Contract {
            $terms = $this->terms($data);
            $before = array_intersect_key($contract->only(self::TERMS), $terms);
            $contract->update([...$terms, 'generated_at' => null, 'generation_error' => null]);

            $this->audit->record('contract.updated', $contract, $before, $terms, actor: $actor, ticketCode: $contract->ticket->code);

            GenerateContractDocuments::dispatch($contract->id)->afterCommit();

            return $contract;
        });
    }

    /**
     * @param  array<string, int|string>  $data
     * @return array{document_template_id: int, service_value: int, dp_percent: int, payment_due_days: int}
     */
    private function terms(array $data): array
    {
        return [
            'document_template_id' => (int) $data['document_template_id'],
            'service_value' => (int) $data['service_value'],
            'dp_percent' => (int) $data['dp_percent'],
            'payment_due_days' => (int) $data['payment_due_days'],
        ];
    }

    /** Nomor urut per tahun; kontrak terakhir dikunci agar pembuatan bersamaan tidak bertabrakan. */
    private function nextNumber(): string
    {
        $prefix = 'KTR/'.now()->year.'/';
        $last = Contract::query()->where('number', 'like', $prefix.'%')->orderByDesc('number')->lockForUpdate()->value('number');
        $number = $last === null ? 1 : (int) substr($last, strlen($prefix)) + 1;

        return $prefix.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }
}
