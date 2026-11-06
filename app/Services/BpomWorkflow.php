<?php

namespace App\Services;

use App\DocumentStatus;
use App\Models\BpomRequest;
use App\Models\BpomSubmission;
use App\Models\Ticket;
use App\Models\TicketDocument;
use App\Models\User;
use App\StagePhase;
use App\SubmissionStatus;
use App\TicketStatus;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BpomWorkflow
{
    /** Tenggat pengajuan registrasi setelah hasil pra-registrasi (BR-22). */
    public const REGISTRATION_DEADLINE_DAYS = 30;

    /** Tenggat pemenuhan tambahan data BPOM (BR-23). */
    public const ADDITIONAL_DATA_DAYS = 60;

    public function __construct(private AuditLogger $audit, private WorkingDays $workingDays, private RepairStage $repair) {}

    /**
     * Catat pengajuan fase berjalan ke BPOM beserta PNBP dan kunci versi setiap dokumen
     * yang diajukan (FR-27, FR-28, BR-06). Pengajuan hanya boleh bila semua dokumen fase
     * itu sudah disetujui; registrasi juga harus sebelum tenggat pra-registrasi (BR-22).
     *
     * @param  array{submitted_on: string, pnbp: int|string}  $data
     */
    public function submit(Ticket $ticket, array $data, User $actor): BpomSubmission
    {
        return DB::transaction(function () use ($ticket, $data, $actor): BpomSubmission {
            $ticket = Ticket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $phase = $ticket->phase;

            if ($ticket->status !== TicketStatus::Active || ! in_array($phase, [StagePhase::PreRegistration, StagePhase::Registration], true)) {
                throw ValidationException::withMessages(['bpom' => 'Tiket belum berada di fase pra-registrasi atau registrasi.']);
            }

            if ($ticket->bpomSubmissions()->where('phase', $phase)->where('status', '!=', SubmissionStatus::Expired)->exists()) {
                throw ValidationException::withMessages(['bpom' => "Pengajuan {$phase->label()} sudah dicatat."]);
            }

            $blockers = $this->unapprovedDocuments($ticket, $phase);

            if ($phase === StagePhase::Registration) {
                $preRegistration = $ticket->bpomSubmissions()->where('phase', StagePhase::PreRegistration)->where('status', SubmissionStatus::ResultIssued)->latest('id')->first();

                if ($preRegistration === null || $preRegistration->registration_deadline->lt(Carbon::parse($data['submitted_on']))) {
                    $blockers[] = 'Hasil pra-registrasi belum terbit atau tenggat 30 hari kerja sudah lewat.';
                }
            }

            if ($blockers !== []) {
                throw ValidationException::withMessages(['bpom' => 'Belum dapat diajukan: '.implode(' ', $blockers)]);
            }

            $submission = $ticket->bpomSubmissions()->create([
                'phase' => $phase,
                'submitted_on' => $data['submitted_on'],
                'pnbp' => (int) $data['pnbp'],
                'status' => SubmissionStatus::AwaitingResult,
                'recorded_by' => $actor->id,
            ]);

            $locked = $ticket->documents()->with('document')->get()
                ->filter(fn (TicketDocument $item): bool => $item->belongsToPhase($phase) && $item->document?->current_version_id !== null)
                ->mapWithKeys(fn (TicketDocument $item): array => [$item->document->current_version_id => ['ticket_document_id' => $item->id]]);
            $submission->lockedVersions()->attach($locked->all());

            $ticket->moveToStage($phase === StagePhase::PreRegistration ? 'menunggu_bpom_pra_registrasi' : 'menunggu_bpom_registrasi');

            $this->audit->record('bpom.submitted', $submission, after: [
                'phase' => $phase->value,
                'submitted_on' => $submission->submitted_on->toDateString(),
                'pnbp' => $submission->pnbp,
                'locked_documents' => $locked->count(),
            ], actor: $actor, ticketCode: $ticket->code);

            return $submission;
        });
    }

    /**
     * Catat hasil pra-registrasi beserta dokumennya (FR-30) dan tetapkan tenggat pengajuan
     * registrasi 30 hari kerja sejak tanggal hasil (FR-89, BR-22).
     */
    public function recordPreRegistrationResult(BpomSubmission $submission, string $resultOn, UploadedFile $file, User $actor): BpomSubmission
    {
        if ($submission->phase !== StagePhase::PreRegistration || $submission->status !== SubmissionStatus::AwaitingResult) {
            throw ValidationException::withMessages(['result' => 'Pengajuan ini tidak sedang menunggu hasil pra-registrasi.']);
        }

        $path = Storage::disk('local')->putFileAs("bpom/{$submission->id}", $file, 'hasil-'.Str::uuid().'.'.strtolower($file->getClientOriginalExtension()));
        $deadline = $this->workingDays->add(Carbon::parse($resultOn), self::REGISTRATION_DEADLINE_DAYS);

        DB::transaction(function () use ($submission, $resultOn, $file, $path, $deadline): void {
            $submission->update([
                'status' => SubmissionStatus::ResultIssued,
                'result_on' => $resultOn,
                'result_path' => $path,
                'result_original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'registration_deadline' => $deadline->toDateString(),
            ]);
            $submission->ticket->finishStage('menunggu_bpom_pra_registrasi');
        });

        $this->audit->record('bpom.pre_registration_result', $submission, after: [
            'result_on' => $resultOn,
            'registration_deadline' => $deadline->toDateString(),
        ], actor: $actor, ticketCode: $submission->ticket->code);

        return $submission;
    }

    /**
     * Catat catatan atau tambahan data BPOM sebagai TD1–TD3 dengan tenggat 60 hari kerja
     * (FR-29, FR-90). Dokumen terkait dikembalikan ke PIC-nya dan tahap perbaikan dibuka.
     *
     * @param  list<int>  $itemIds
     */
    public function requestAdditionalData(BpomSubmission $submission, string $note, string $requestedOn, array $itemIds, User $actor): BpomRequest
    {
        return DB::transaction(function () use ($submission, $note, $requestedOn, $itemIds, $actor): BpomRequest {
            BpomSubmission::query()->whereKey($submission->id)->lockForUpdate()->first();
            $sequence = $submission->requests()->count() + 1;

            if ($submission->status !== SubmissionStatus::AwaitingResult || $sequence > BpomRequest::MAX_SEQUENCE) {
                throw ValidationException::withMessages(['note' => 'Tambahan data hanya dapat dicatat paling banyak tiga kali selama menunggu hasil BPOM.']);
            }

            $request = $submission->requests()->create([
                'ticket_id' => $submission->ticket_id,
                'sequence' => $sequence,
                'note' => $note,
                'requested_on' => $requestedOn,
                'deadline' => $this->workingDays->add(Carbon::parse($requestedOn), self::ADDITIONAL_DATA_DAYS)->toDateString(),
                'recorded_by' => $actor->id,
            ]);

            $items = $submission->ticket->documents()->whereKey($itemIds)->get();
            $items->each(fn (TicketDocument $item) => $item->update([
                'status' => DocumentStatus::NeedsRevision,
                'revision_reason' => "Tambahan data BPOM {$request->code()}: {$note}",
            ]));

            if ($items->isNotEmpty()) {
                $this->repair->open($submission->ticket);
            }

            $this->audit->record('bpom.additional_data', $request, after: [
                'code' => $request->code(),
                'deadline' => $request->deadline->toDateString(),
                'documents' => $items->count(),
                'rejection_risk' => $sequence === BpomRequest::MAX_SEQUENCE,
            ], actor: $actor, ticketCode: $submission->ticket->code);

            return $request;
        });
    }

    public function respond(BpomRequest $request, User $actor): void
    {
        if ($request->responded_at !== null) {
            return;
        }

        $request->update(['responded_at' => now()]);
        $this->audit->record('bpom.additional_data_responded', $request, after: ['code' => $request->code()], actor: $actor, ticketCode: $request->submission->ticket->code);
    }

    /**
     * Tutup tiket sebagai ditolak setelah TD3 tidak terpenuhi (FR-90, BR-23).
     */
    public function reject(Ticket $ticket, string $reason, User $actor): void
    {
        if (! $ticket->bpomRequests()->where('sequence', BpomRequest::MAX_SEQUENCE)->exists()) {
            throw ValidationException::withMessages(['reason' => 'Status ditolak hanya dapat dicatat setelah tambahan data ketiga (TD3).']);
        }

        DB::transaction(function () use ($ticket): void {
            $ticket->stageLogs()->whereNull('finished_at')->update(['finished_at' => now()]);
            $ticket->update(['status' => TicketStatus::Rejected]);
        });

        $this->audit->record('ticket.rejected', $ticket, after: ['status' => TicketStatus::Rejected->value], reason: $reason, actor: $actor, ticketCode: $ticket->code);
    }

    /**
     * Syarat pembukaan fase registrasi yang belum terpenuhi, termasuk LHU uji laboratorium
     * yang sesuai spesifikasi (FR-31, FR-88).
     *
     * @return list<string>
     */
    public function registrationBlockers(Ticket $ticket, CarbonInterface $today): array
    {
        if ($ticket->phase !== StagePhase::PreRegistration) {
            return ['Tiket tidak berada di fase pra-registrasi.'];
        }

        $blockers = $this->unapprovedDocuments($ticket, StagePhase::PreRegistration);
        $submission = $ticket->bpomSubmissions()->where('phase', StagePhase::PreRegistration)->latest('id')->first();

        if ($submission === null || $submission->status === SubmissionStatus::Expired) {
            $blockers[] = 'Pengajuan pra-registrasi belum dicatat.';
        } elseif ($submission->status !== SubmissionStatus::ResultIssued) {
            $blockers[] = 'Hasil pra-registrasi belum dicatat dan diunggah.';
        } elseif ($submission->registration_deadline->lt($today->copy()->startOfDay())) {
            $blockers[] = 'Tenggat pengajuan registrasi sudah lewat; pra-registrasi hangus.';
        }

        if (! $ticket->labTests()->where('conforms', true)->exists()) {
            $blockers[] = 'LHU uji laboratorium yang sesuai spesifikasi belum diunggah.';
        }

        return $blockers;
    }

    public function openRegistration(Ticket $ticket, User $actor): void
    {
        $blockers = $this->registrationBlockers($ticket, now());

        if ($blockers !== []) {
            throw ValidationException::withMessages(['bpom' => 'Fase registrasi belum dapat dibuka: '.implode(' ', $blockers)]);
        }

        DB::transaction(function () use ($ticket): void {
            $ticket->moveToStage('dokumen_registrasi');
            $ticket->update(['phase' => StagePhase::Registration]);
        });

        $this->audit->record('ticket.registration_opened', $ticket, ['phase' => StagePhase::PreRegistration->value], ['phase' => StagePhase::Registration->value],
            actor: $actor, ticketCode: $ticket->code);
    }

    /**
     * Tandai hasil pra-registrasi yang tenggat registrasinya lewat tanpa pengajuan sebagai
     * hangus (FR-89, BR-22); tiket kembali ke fase pra-registrasi untuk diajukan ulang.
     *
     * @return int jumlah pengajuan yang hangus
     */
    public function expirePreRegistrations(CarbonInterface $today): int
    {
        $expired = BpomSubmission::query()
            ->where('phase', StagePhase::PreRegistration)
            ->where('status', SubmissionStatus::ResultIssued)
            ->whereDate('registration_deadline', '<', $today->toDateString())
            ->whereDoesntHave('ticket.bpomSubmissions', fn ($query) => $query->where('phase', StagePhase::Registration)->where('status', '!=', SubmissionStatus::Expired))
            ->with('ticket')
            ->get();

        foreach ($expired as $submission) {
            DB::transaction(function () use ($submission): void {
                $submission->update(['status' => SubmissionStatus::Expired]);
                $ticket = $submission->ticket;

                if ($ticket->status === TicketStatus::Active) {
                    $ticket->moveToStage('dokumen_pra_registrasi');
                    $ticket->update(['phase' => StagePhase::PreRegistration]);
                }
            });

            $this->audit->record('bpom.pre_registration_expired', $submission, after: [
                'registration_deadline' => $submission->registration_deadline->toDateString(),
            ], source: 'system', ticketCode: $submission->ticket->code);
        }

        return $expired->count();
    }

    /** @return list<string> */
    private function unapprovedDocuments(Ticket $ticket, StagePhase $phase): array
    {
        $pending = $ticket->documents()->with('documentType')->get()
            ->filter(fn (TicketDocument $item): bool => $item->belongsToPhase($phase) && $item->status !== DocumentStatus::Approved)
            ->map(fn (TicketDocument $item): string => $item->documentType->name)
            ->sort()->values();

        return $pending->isEmpty() ? [] : ['Dokumen belum disetujui: '.$pending->implode(', ').'.'];
    }
}
