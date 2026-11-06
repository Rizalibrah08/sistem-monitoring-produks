<?php

namespace App\Services;

use App\Actions\IssueInvoice;
use App\ContractStatus;
use App\InvoiceTerm;
use App\Models\BpomSubmission;
use App\Models\Ticket;
use App\Models\User;
use App\StagePhase;
use App\SubmissionStatus;
use App\TicketStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistrationCompletion
{
    public function __construct(private AuditLogger $audit, private IssueInvoice $invoices, private StaffNotifier $notifier) {}

    /**
     * Catat permintaan desain kemasan dari BPOM; tiket menunggu desain diunggah (FR-91).
     */
    public function requestPackaging(Ticket $ticket, string $requestedOn, User $actor): void
    {
        $submission = $this->registrationSubmission($ticket);

        DB::transaction(function () use ($ticket, $requestedOn): void {
            $ticket->update(['packaging_requested_on' => $requestedOn, 'packaging_uploaded_on' => null]);
            $ticket->moveToStage('unggah_desain_kemasan');
        });

        $this->audit->record('ticket.packaging_requested', $ticket, after: ['requested_on' => $requestedOn, 'submission_id' => $submission->id], actor: $actor, ticketCode: $ticket->code);
    }

    /**
     * Catat tanggal desain kemasan diunggah ke BPOM; waktu tunggu NIE setelahnya tidak
     * dihitung dalam SLA (FR-91, BR-24).
     */
    public function packagingUploaded(Ticket $ticket, string $uploadedOn, User $actor): void
    {
        if ($ticket->packaging_requested_on === null) {
            throw ValidationException::withMessages(['uploaded_on' => 'Catat permintaan desain kemasan terlebih dahulu.']);
        }

        DB::transaction(function () use ($ticket, $uploadedOn): void {
            $ticket->update(['packaging_uploaded_on' => $uploadedOn]);
            $ticket->finishStage('unggah_desain_kemasan');
        });

        $this->audit->record('ticket.packaging_uploaded', $ticket, after: ['uploaded_on' => $uploadedOn], actor: $actor, ticketCode: $ticket->code);
    }

    /**
     * RA menginput nomor dan tanggal NIE sehingga tiket selesai (FR-32); tagihan pelunasan
     * terbit dan tetap dipantau sampai lunas (BR-17).
     */
    public function recordNie(Ticket $ticket, string $number, string $issuedOn, User $actor): void
    {
        $submission = $this->registrationSubmission($ticket);
        $settlement = null;

        DB::transaction(function () use ($ticket, $submission, $number, $issuedOn, &$settlement): void {
            $submission->update(['status' => SubmissionStatus::ResultIssued, 'result_on' => $issuedOn]);
            $ticket->stageLogs()->whereNull('finished_at')->update(['finished_at' => now()]);
            $ticket->update(['nie_number' => $number, 'nie_issued_on' => $issuedOn, 'status' => TicketStatus::Completed]);

            $contract = $ticket->contract;

            if ($contract !== null && $contract->status === ContractStatus::Active) {
                $settlement = $this->invoices->handle($contract, InvoiceTerm::Settlement);
            }
        });

        $this->audit->record('ticket.nie_recorded', $ticket, after: [
            'nie_number' => $number,
            'nie_issued_on' => $issuedOn,
            'settlement_invoice' => $settlement?->number,
        ], actor: $actor, ticketCode: $ticket->code);

        $this->notifier->nieRecorded($ticket->refresh(), $actor);
    }

    private function registrationSubmission(Ticket $ticket): BpomSubmission
    {
        $submission = $ticket->bpomSubmissions()->where('phase', StagePhase::Registration)->where('status', SubmissionStatus::AwaitingResult)->latest('id')->first();

        if ($ticket->status !== TicketStatus::Active || $submission === null) {
            throw ValidationException::withMessages(['nie_number' => 'Tiket belum memiliki pengajuan registrasi yang menunggu hasil BPOM.']);
        }

        return $submission;
    }
}
