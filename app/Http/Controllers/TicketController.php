<?php

namespace App\Http\Controllers;

use App\Actions\CreateTicket;
use App\DocumentStatus;
use App\Http\Requests\CreateTicketRequest;
use App\Models\Activity;
use App\Models\Category;
use App\Models\Client;
use App\Models\DosageForm;
use App\Models\Ticket;
use App\Models\TicketDocument;
use App\Models\TicketStageLog;
use App\StaffRole;
use App\StagePhase;
use App\TicketStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    /** @var array<string, string> */
    private const EVENT_LABELS = [
        'ticket.created' => 'Tiket dibuat',
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
            'urls' => ['store' => route('tickets.store', absolute: false), 'clients' => route('clients.index', absolute: false)],
        ]);
    }

    public function store(CreateTicketRequest $request, CreateTicket $create): RedirectResponse
    {
        $ticket = $create->handle($request->validated(), $request->user());

        return redirect()->route('tickets.show', $ticket)->with('status', "Tiket {$ticket->code} berhasil dibuat.");
    }

    public function show(Request $request, Ticket $ticket): Response
    {
        $ticket->load(['client.marketingUser', 'dosageForm', 'category', 'currentStage', 'creator', 'documents.documentType', 'stageLogs.stage']);
        abort_unless($ticket->isVisibleTo($request->user()), 403);

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
            ],
            'checklist' => collect([StagePhase::PreRegistration, StagePhase::Registration])->map(fn (StagePhase $phase): array => [
                'phase' => $phase->value,
                'label' => $phase->label(),
                'progress' => $this->progress($ticket->documents, $phase),
                'documents' => $ticket->documents
                    ->filter(fn (TicketDocument $document): bool => $document->belongsToPhase($phase))
                    ->sortBy(fn (TicketDocument $document): string => $document->documentType->name)
                    ->map(fn (TicketDocument $document): array => [
                        'id' => $document->id,
                        'name' => $document->documentType->name,
                        'scope' => $document->documentType->scope->label(),
                        'requirement' => $document->requirement->label(),
                        'pic' => $document->documentType::roleLabel($document->documentType->pic_role),
                        'both_phases' => $document->phase === StagePhase::BothPhases,
                        'status' => $document->status->value,
                        'status_label' => $document->status->label(),
                    ])->values(),
            ]),
            'stages' => $ticket->stageLogs->sortBy('started_at')->map(fn (TicketStageLog $log): array => [
                'name' => $log->stage->name,
                'started_at' => $log->started_at->format('d/m/Y H:i'),
                'due_at' => $log->due_at?->format('d/m/Y H:i'),
                'finished_at' => $log->finished_at?->format('d/m/Y H:i'),
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
