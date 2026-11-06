<?php

namespace App\Http\Controllers;

use App\Actions\CreateTicket;
use App\DocumentStatus;
use App\Http\Requests\CreateTicketBatchRequest;
use App\Http\Requests\CreateTicketRequest;
use App\Models\Activity;
use App\Models\BpomRequest;
use App\Models\BpomSubmission;
use App\Models\Category;
use App\Models\Client;
use App\Models\Contract;
use App\Models\DosageForm;
use App\Models\Invoice;
use App\Models\LabTest;
use App\Models\Material;
use App\Models\SampleApproval;
use App\Models\Ticket;
use App\Models\TicketDocument;
use App\Models\TicketReview;
use App\Models\TicketStageLog;
use App\Models\UploadToken;
use App\Models\User;
use App\SampleDecision;
use App\Services\BpomWorkflow;
use App\Services\ChecklistBuilder;
use App\StaffRole;
use App\StagePhase;
use App\SubmissionStatus;
use App\TicketStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    /** @var array<string, string> */
    private const EVENT_LABELS = [
        'ticket.created' => 'Tiket dibuat',
        'document.uploaded' => 'Dokumen diunggah',
        'document.reviewed' => 'Dokumen diverifikasi',
        'bpom.submitted' => 'Pengajuan BPOM dicatat',
        'bpom.pre_registration_result' => 'Hasil pra-registrasi dicatat',
        'bpom.additional_data' => 'Tambahan data BPOM dicatat',
        'bpom.additional_data_responded' => 'Tanggapan tambahan data dikirim',
        'bpom.pre_registration_expired' => 'Pra-registrasi hangus',
        'ticket.registration_opened' => 'Fase registrasi dibuka',
        'ticket.rejected' => 'Pendaftaran ditolak BPOM',
        'lab_test.sent' => 'Sampel dikirim ke laboratorium',
        'lab_test.reanalysis' => 'Analisis ulang laboratorium',
        'lab_test.result' => 'LHU dicatat',
        'ticket.packaging_requested' => 'Desain kemasan diminta',
        'ticket.packaging_uploaded' => 'Desain kemasan diunggah',
        'ticket.nie_recorded' => 'NIE terbit',
        'stage.overdue' => 'Tahap melewati tenggat',
        'checklist.synced' => 'Checklist disinkronkan',
        'ticket.materials_updated' => 'Bahan baku diperbarui',
        'upload_link.created' => 'Tautan unggah klien dibuat',
        'sample.recorded' => 'Keputusan sampel dicatat',
        'ticket.review_flagged' => 'Ditandai perlu ditinjau',
        'ticket.review_resolved' => 'Ditandai sudah ditinjau',
        'contract.created' => 'Kontrak dibuat',
        'contract.updated' => 'Kontrak diubah',
        'contract.sent' => 'Kontrak dikirim',
        'contract.signed_uploaded' => 'Kontrak bertanda tangan diunggah',
        'contract.confirmed' => 'Kontrak dikonfirmasi aktif',
        'invoice.issued' => 'Tagihan diterbitkan',
        'invoice.payment_started' => 'Klien memulai pembayaran',
        'invoice.paid' => 'Tagihan lunas',
        'upload_link.revoked' => 'Tautan unggah klien dicabut',
    ];

    public function index(Request $request): Response
    {
        $user = $request->user();
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'phase' => ['nullable', Rule::enum(StagePhase::class)],
            'status' => ['nullable', Rule::enum(TicketStatus::class)],
        ]);
        $search = $filters['search'] ?? null;

        $tickets = Ticket::query()->visibleTo($user)
            ->with(['client', 'dosageForm', 'category', 'currentStage', 'documents'])
            ->withExists(['reviews as needs_review' => fn (Builder $query) => $query->whereNull('resolved_at')])
            ->withExists(['stageLogs as is_late' => fn (Builder $query) => $query->whereNull('finished_at')->whereNotNull('overdue_at')])
            ->when(filled($search), function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('code', 'like', "%{$search}%")
                        ->orWhere('product_name', 'like', "%{$search}%")
                        ->orWhereHas('client', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when(filled($filters['phase'] ?? null), fn (Builder $query) => $query->where('phase', $filters['phase']))
            ->when(filled($filters['status'] ?? null), fn (Builder $query) => $query->where('status', $filters['status']))
            ->orderByDesc('created_at')->orderByDesc('id')->paginate(25)->withQueryString()
            ->through(fn (Ticket $ticket): array => [
                'id' => $ticket->id,
                'code' => $ticket->code,
                'product_name' => $ticket->product_name,
                'client' => $ticket->client->name,
                'dosage_form' => $ticket->dosageForm->name,
                'category' => $ticket->category->name,
                'phase' => $ticket->phase->value,
                'phase_label' => $ticket->phase->label(),
                'status' => $ticket->status->value,
                'status_label' => $ticket->status->label(),
                'current_stage' => $ticket->currentStage?->name,
                'needs_review' => (bool) $ticket->needs_review,
                'is_late' => (bool) $ticket->is_late,
                'progress' => $this->progress($ticket->documents, $ticket->phase === StagePhase::Initial ? StagePhase::PreRegistration : $ticket->phase),
                'created_at' => $ticket->created_at?->format('d/m/Y'),
                'show_url' => route('tickets.show', $ticket, absolute: false),
            ]);

        $canCreate = $user->can('tickets.create');

        return Inertia::render('Backend/Tickets', [
            'tickets' => $tickets,
            'filters' => ['search' => $search ?? '', 'phase' => $filters['phase'] ?? '', 'status' => $filters['status'] ?? ''],
            'phases' => $this->options(StagePhase::cases()),
            'statuses' => $this->options(TicketStatus::cases()),
            'can' => ['create' => $canCreate],
            'createOptions' => $canCreate ? [
                'clients' => Client::query()
                    ->when($user->hasRole(StaffRole::Marketing->value), fn (Builder $query) => $query->where('marketing_user_id', $user->id))
                    ->orderBy('name')->get()->map(fn (Client $client): array => ['value' => $client->id, 'label' => $client->name]),
                'dosageForms' => DosageForm::query()->where('is_active', true)->orderBy('name')->get()->map(fn (DosageForm $form): array => ['value' => $form->id, 'label' => $form->name]),
                'categories' => Category::query()->where('is_active', true)->orderBy('name')->get()->map(fn (Category $category): array => ['value' => $category->id, 'label' => $category->name]),
            ] : null,
            'urls' => ['store' => route('tickets.store', absolute: false), 'store_batch' => route('tickets.store-batch', absolute: false), 'clients' => route('clients.index', absolute: false)],
        ]);
    }

    public function store(CreateTicketRequest $request, CreateTicket $create): RedirectResponse
    {
        $ticket = $create->handle($request->validated(), $request->user());

        return redirect()->route('tickets.show', $ticket)->with('status', "Tiket {$ticket->code} berhasil dibuat.");
    }

    /**
     * Buat beberapa tiket sekaligus untuk satu klien, satu tiket per produk (FR-23, BR-01).
     */
    public function storeBatch(CreateTicketBatchRequest $request, CreateTicket $create): RedirectResponse
    {
        $clientId = $request->validated('client_id');
        $codes = DB::transaction(fn (): array => array_map(
            fn (array $product): string => $create->handle(['client_id' => $clientId, ...$product], $request->user())->code,
            $request->validated('products'),
        ));

        return redirect()->route('tickets.index')->with('status', count($codes).' tiket dibuat: '.implode(', ', $codes).'.');
    }

    public function show(Request $request, Ticket $ticket, BpomWorkflow $bpom, ChecklistBuilder $checklistBuilder): Response
    {
        $ticket->load(['client.marketingUser', 'dosageForm', 'category', 'currentStage', 'creator', 'documents.documentType', 'documents.material', 'documents.document.currentVersion', 'stageLogs.stage', 'materials', 'sampleApprovals.recorder', 'contract.documentTemplate', 'invoices.ticket.client']);
        $user = $request->user();
        abort_unless($ticket->isVisibleTo($user), 403);
        $canManageMaterials = $user->can('ticket_materials.manage');
        $canManageUploadLinks = $user->can('upload_links.manage');
        $canManageContract = $user->can('contracts.manage');
        $sampleApproved = $ticket->sampleApprovals->contains(fn (SampleApproval $approval): bool => $approval->decision === SampleDecision::Approved);

        return Inertia::render('Backend/TicketShow', [
            'ticket' => [
                'id' => $ticket->id,
                'code' => $ticket->code,
                'product_name' => $ticket->product_name,
                'client' => $ticket->client->name,
                'client_pic' => $ticket->client->pic_name,
                'marketing_user' => $ticket->client->marketingUser->name,
                'dosage_form' => $ticket->dosageForm->name,
                'category' => $ticket->category->name,
                'phase' => $ticket->phase->value,
                'phase_label' => $ticket->phase->label(),
                'status' => $ticket->status->value,
                'status_label' => $ticket->status->label(),
                'current_stage' => $ticket->currentStage?->name,
                'created_by' => $ticket->creator->name,
                'created_at' => $ticket->created_at?->format('d/m/Y H:i'),
                'nie_number' => $ticket->nie_number,
                'nie_issued_on' => $ticket->nie_issued_on?->format('d/m/Y'),
            ],
            'checklist' => collect([StagePhase::PreRegistration, StagePhase::Registration])->map(fn (StagePhase $phase): array => [
                'phase' => $phase->value,
                'label' => $phase->label(),
                'progress' => $this->progress($ticket->documents, $phase),
                'documents' => $ticket->documents
                    ->filter(fn (TicketDocument $document): bool => $document->belongsToPhase($phase))
                    ->sortBy([
                        fn (TicketDocument $a, TicketDocument $b): int => $a->documentType->name <=> $b->documentType->name,
                        fn (TicketDocument $a, TicketDocument $b): int => ($a->material?->name ?? '') <=> ($b->material?->name ?? ''),
                    ])
                    ->map(fn (TicketDocument $document): array => [
                        'id' => $document->id,
                        'name' => $document->documentType->name,
                        'material' => $document->material?->name,
                        'scope' => $document->documentType->scope->label(),
                        'requirement' => $document->requirement->label(),
                        'pic' => $document->documentType::roleLabel($document->documentType->pic_role),
                        'both_phases' => $document->phase === StagePhase::BothPhases,
                        'status' => $document->status->value,
                        'status_label' => $document->status->label(),
                        'revision_reason' => $document->revision_reason,
                        'content_verified' => $document->needsContentReview() && $document->document !== null ? $document->contentApproved() : null,
                        'requires_issued_at' => $document->documentType->max_age_months !== null,
                        'requires_expires_at' => $document->documentType->requires_expiry_date,
                        'can_upload' => $document->canBeUploadedBy($user),
                        'upload_url' => route('tickets.documents.versions.store', [$ticket, $document], absolute: false),
                        'current_version' => ($version = $document->document?->currentVersion) === null ? null : [
                            'version' => $version->version,
                            'number' => $version->number,
                            'original_name' => $version->original_name,
                            'issued_at' => $version->issued_at?->format('d/m/Y'),
                            'expires_at' => $version->expires_at?->format('d/m/Y'),
                            'uploaded_at' => $version->created_at?->format('d/m/Y H:i'),
                            'download_url' => route('document-versions.download', $version, absolute: false),
                        ],
                    ])->values(),
            ]),
            'materials' => $ticket->materials->sortBy('name')->map(fn (Material $material): array => ['id' => $material->id, 'name' => $material->name, 'supplier' => $material->supplier])->values(),
            'materialOptions' => $canManageMaterials ? Material::query()
                ->where('is_active', true)->orWhereIn('id', $ticket->materials->modelKeys())
                ->orderBy('name')->get()
                ->map(fn (Material $material): array => ['value' => $material->id, 'label' => $material->supplier ? "{$material->name} ({$material->supplier})" : $material->name]) : null,
            'can' => ['manage_materials' => $canManageMaterials, 'create_material' => $user->can('materials.create'), 'manage_upload_links' => $canManageUploadLinks,
                'resolve_reviews' => $user->can('ticket_reviews.resolve'),
                'manage_contract' => $canManageContract,
                'create_contract' => $canManageContract && $sampleApproved && $ticket->contract === null && $ticket->status === TicketStatus::Active,
                'record_sample' => $user->can('samples.record') && $ticket->status === TicketStatus::Active && ! $sampleApproved],
            'urls' => [
                'materials' => route('tickets.materials.update', $ticket, absolute: false),
                'create_material' => route('master.materials.store', absolute: false),
                'upload_links' => route('tickets.upload-links.store', $ticket, absolute: false),
                'samples' => route('tickets.samples.store', $ticket, absolute: false),
                'resolve_reviews' => route('tickets.reviews.resolve', $ticket, absolute: false),
                'contract_store' => route('contracts.store', absolute: false),
            ],
            'contract' => ($contract = $ticket->contract) === null ? null : [
                'id' => $contract->id,
                'number' => $contract->number,
                'status' => $contract->status->value,
                'status_label' => $contract->status->label(),
                'service_value' => $contract->service_value,
                'dp_percent' => $contract->dp_percent,
                'payment_due_days' => $contract->payment_due_days,
                'document_template_id' => $contract->document_template_id,
                'template' => $contract->documentTemplate->name,
                'dp_amount' => $contract->dpAmount(),
                'settlement_amount' => $contract->settlementAmount(),
                'is_generated' => $contract->generated_at !== null,
                'generation_error' => $contract->generation_error,
                'signed_at' => $contract->signed_at?->format('d/m/Y H:i'),
                'confirmed_at' => $contract->confirmed_at?->format('d/m/Y H:i'),
                'files' => collect(['word' => $contract->word_path, 'pdf' => $contract->pdf_path, 'signed' => $contract->signed_path])
                    ->filter()->map(fn (string $path, string $file): string => route('contracts.files', [$contract, $file], absolute: false)),
                'urls' => [
                    'update' => route('contracts.update', $contract, absolute: false),
                    'send' => route('contracts.send', $contract, absolute: false),
                    'signed' => route('contracts.signed', $contract, absolute: false),
                    'confirm' => route('contracts.confirm', $contract, absolute: false),
                ],
            ],
            'bpom' => $this->bpom($ticket, $user, $bpom),
            'checklistSync' => $user->can('document_rules.manage') && $ticket->status === TicketStatus::Active ? ChecklistSyncController::row($ticket, $checklistBuilder) : null,
            'labTests' => [
                'can_manage' => $user->can('lab_tests.manage') && $ticket->status === TicketStatus::Active && in_array($ticket->phase, [StagePhase::PreRegistration, StagePhase::Registration], true),
                'store_url' => route('lab-tests.store', $ticket, absolute: false),
                'tests' => $ticket->labTests()->orderBy('attempt')->get()->map(fn (LabTest $test): array => [
                    'id' => $test->id,
                    'attempt' => $test->attempt,
                    'laboratory' => $test->laboratory,
                    'sent_on' => $test->sent_on->format('d/m/Y'),
                    'estimated_on' => $test->estimated_on->format('d/m/Y'),
                    'result_on' => $test->result_on?->format('d/m/Y'),
                    'conforms' => $test->conforms,
                    'note' => $test->note,
                    'file_url' => $test->result_path === null ? null : route('lab-tests.file', $test, absolute: false),
                    'result_url' => route('lab-tests.result', $test, absolute: false),
                ]),
            ],
            'invoices' => $ticket->invoices->sortBy('id')->map(fn (Invoice $invoice): array => InvoiceController::row($invoice))->values(),
            'contractOptions' => $canManageContract ? [
                'templates' => ContractController::templateOptions(),
                'defaults' => ['dp_percent' => Contract::DEFAULT_DP_PERCENT, 'payment_due_days' => Contract::DEFAULT_PAYMENT_DUE_DAYS],
            ] : null,
            'reviews' => $ticket->reviews()->open()->orderBy('id')->get()->map(fn (TicketReview $review): array => [
                'id' => $review->id,
                'trigger_label' => $review->trigger->label(),
                'message' => $review->message,
                'flagged_at' => $review->created_at?->format('d/m/Y H:i'),
            ]),
            'samples' => $ticket->sampleApprovals->sortByDesc('revision')->map(fn (SampleApproval $approval): array => [
                'id' => $approval->id,
                'revision' => $approval->revision,
                'decision' => $approval->decision->value,
                'decision_label' => $approval->decision->label(),
                'note' => $approval->note,
                'recorded_by' => $approval->recorder->name,
                'recorded_at' => $approval->created_at?->format('d/m/Y H:i'),
            ])->values(),
            'uploadLinks' => $canManageUploadLinks ? [
                'default_days' => (int) config('upload_links.default_days'),
                'max_days' => (int) config('upload_links.max_days'),
                'active' => $ticket->uploadTokens()->usable()->with('creator')->orderBy('phase')->get()
                    ->map(fn (UploadToken $link): array => [
                        'id' => $link->id,
                        'phase_label' => $link->phase->label(),
                        'expires_at' => $link->expires_at->format('d/m/Y H:i'),
                        'created_by' => $link->creator->name,
                        'last_used_at' => $link->last_used_at?->format('d/m/Y H:i'),
                        'revoke_url' => route('tickets.upload-links.destroy', [$ticket, $link], absolute: false),
                    ]),
            ] : null,
            'stages' => $ticket->stageLogs->sortBy('started_at')->map(fn (TicketStageLog $log): array => [
                'name' => $log->stage->name,
                'started_at' => $log->started_at->format('d/m/Y H:i'),
                'due_at' => $log->due_at?->format('d/m/Y H:i'),
                'finished_at' => $log->finished_at?->format('d/m/Y H:i'),
                'overdue' => $log->overdue_at !== null,
                'delay_cause' => $log->delay_cause,
            ])->values(),
            'history' => Activity::query()->where('ticket_code', $ticket->code)->orderByDesc('id')->limit(100)->get()
                ->map(fn (Activity $entry): array => [
                    'id' => $entry->id,
                    'event' => $entry->event,
                    'label' => self::EVENT_LABELS[$entry->event] ?? $entry->event,
                    'actor' => $entry->actor_name ?? 'Sistem',
                    'created_at' => $entry->created_at?->format('d/m/Y H:i'),
                ]),
        ]);
    }

    /**
     * Pengajuan BPOM, tambahan data, dan syarat pembukaan fase registrasi (F07, F08).
     *
     * @return array<string, mixed>
     */
    private function bpom(Ticket $ticket, User $user, BpomWorkflow $workflow): array
    {
        $canManage = $user->can('bpom.manage');
        $submissions = $ticket->bpomSubmissions()->with('requests')->withCount('lockedVersions')->orderBy('id')->get();

        return [
            'can_manage' => $canManage && $ticket->status === TicketStatus::Active,
            'can_submit' => in_array($ticket->phase, [StagePhase::PreRegistration, StagePhase::Registration], true)
                && ! $submissions->contains(fn (BpomSubmission $submission): bool => $submission->phase === $ticket->phase && $submission->status !== SubmissionStatus::Expired),
            'can_complete' => $canManage && $ticket->status === TicketStatus::Active && $submissions->contains(fn (BpomSubmission $submission): bool => $submission->phase === StagePhase::Registration && $submission->status === SubmissionStatus::AwaitingResult),
            'packaging_requested_on' => $ticket->packaging_requested_on?->format('d/m/Y'),
            'packaging_uploaded_on' => $ticket->packaging_uploaded_on?->format('d/m/Y'),
            'can_reject' => $submissions->flatMap->requests->contains('sequence', BpomRequest::MAX_SEQUENCE),
            'registration_blockers' => $ticket->phase === StagePhase::PreRegistration ? $workflow->registrationBlockers($ticket, now()) : null,
            'submissions' => $submissions->map(fn (BpomSubmission $submission): array => [
                'id' => $submission->id,
                'phase_label' => $submission->phase->label(),
                'status' => $submission->status->value,
                'status_label' => $submission->status->label(),
                'submitted_on' => $submission->submitted_on->format('d/m/Y'),
                'pnbp' => $submission->pnbp,
                'locked_documents' => $submission->locked_versions_count,
                'result_on' => $submission->result_on?->format('d/m/Y'),
                'registration_deadline' => $submission->registration_deadline?->format('d/m/Y'),
                'result_url' => $submission->result_path === null ? null : route('bpom.result-file', $submission, absolute: false),
                'can_record_result' => $submission->phase === StagePhase::PreRegistration && $submission->status === SubmissionStatus::AwaitingResult,
                'can_request' => $submission->status === SubmissionStatus::AwaitingResult && $submission->requests->count() < BpomRequest::MAX_SEQUENCE,
                'requests' => $submission->requests->sortBy('sequence')->map(fn (BpomRequest $request): array => [
                    'id' => $request->id,
                    'code' => $request->code(),
                    'note' => $request->note,
                    'requested_on' => $request->requested_on->format('d/m/Y'),
                    'deadline' => $request->deadline->format('d/m/Y'),
                    'responded_at' => $request->responded_at?->format('d/m/Y H:i'),
                    'respond_url' => route('bpom.requests.respond', $request, absolute: false),
                ])->values(),
                'urls' => [
                    'result' => route('bpom.result', $submission, absolute: false),
                    'request' => route('bpom.requests.store', $submission, absolute: false),
                ],
            ]),
            'urls' => [
                'submit' => route('bpom.submit', $ticket, absolute: false),
                'open_registration' => route('bpom.open-registration', $ticket, absolute: false),
                'reject' => route('bpom.reject', $ticket, absolute: false),
                'packaging' => route('nie.packaging', $ticket, absolute: false),
                'nie' => route('nie.store', $ticket, absolute: false),
            ],
        ];
    }

    /**
     * Persentase dokumen wajib dan kondisional fase itu yang sudah disetujui (FR-19).
     *
     * @param  Collection<int, TicketDocument>  $documents
     */
    private function progress(Collection $documents, StagePhase $phase): int
    {
        $items = $documents->filter(fn (TicketDocument $document): bool => $document->belongsToPhase($phase));

        if ($items->isEmpty()) {
            return 0;
        }

        $approved = $items->filter(fn (TicketDocument $document): bool => $document->status === DocumentStatus::Approved)->count();

        return (int) round($approved / $items->count() * 100);
    }

    /**
     * @param  list<StagePhase|TicketStatus>  $cases
     * @return list<array{value: string, label: string}>
     */
    private function options(array $cases): array
    {
        return array_map(fn (StagePhase|TicketStatus $case): array => ['value' => $case->value, 'label' => $case->label()], $cases);
    }
}
