<?php

namespace App\Actions;

use App\ContractStatus;
use App\InvoiceTerm;
use App\Models\Contract;
use App\Models\Stage;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AuditLogger;
use App\StagePhase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmContract
{
    public function __construct(private AuditLogger $audit, private IssueInvoice $invoices) {}

    /**
     * Marketing mengonfirmasi kontrak bertanda tangan sehingga kontrak aktif (FR-74) dan
     * fase pra-registrasi terbuka (FR-78, BR-16): tiket fase awal berpindah ke tahap
     * dokumen pra-registrasi. Tagihan DP terbit tanpa menahan proses (FR-75).
     */
    public function handle(Contract $contract, User $actor): Contract
    {
        return DB::transaction(function () use ($contract, $actor): Contract {
            $contract = Contract::query()->whereKey($contract->id)->lockForUpdate()->firstOrFail();

            if ($contract->status !== ContractStatus::Signed || $contract->signed_path === null) {
                throw ValidationException::withMessages(['contract' => 'Kontrak bertanda tangan belum diunggah.']);
            }

            $contract->update(['status' => ContractStatus::Active, 'confirmed_at' => now(), 'confirmed_by' => $actor->id]);
            $ticket = $contract->ticket;
            $opened = $this->openPreRegistration($ticket);
            $invoice = $this->invoices->handle($contract, InvoiceTerm::DownPayment);

            $this->audit->record('contract.confirmed', $contract, ['status' => ContractStatus::Signed->value], [
                'status' => ContractStatus::Active->value,
                'pre_registration_opened' => $opened,
                'dp_invoice' => $invoice->number,
            ], actor: $actor, ticketCode: $ticket->code);

            return $contract;
        });
    }

    private function openPreRegistration(Ticket $ticket): bool
    {
        if ($ticket->phase !== StagePhase::Initial) {
            return false;
        }

        $stage = Stage::query()->where('code', 'dokumen_pra_registrasi')->where('is_active', true)->first();
        $ticket->stageLogs()->whereNull('finished_at')->update(['finished_at' => now()]);

        if ($stage !== null) {
            $ticket->stageLogs()->create(['stage_id' => $stage->id, 'started_at' => now()]);
        }

        $ticket->update(['phase' => StagePhase::PreRegistration, 'current_stage_id' => $stage?->id ?? $ticket->current_stage_id]);

        return true;
    }
}
