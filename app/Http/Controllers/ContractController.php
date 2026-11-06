<?php

namespace App\Http\Controllers;

use App\Actions\ConfirmContract;
use App\Actions\SaveContract;
use App\Actions\StoreSignedContract;
use App\ContractStatus;
use App\Http\Requests\SaveContractRequest;
use App\Http\Requests\UploadSignedContractRequest;
use App\Models\Contract;
use App\Models\DocumentTemplate;
use App\Models\Ticket;
use App\SampleDecision;
use App\Services\AuditLogger;
use App\TemplateKind;
use App\TicketStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContractController extends Controller
{
    /** @var array<string, string> */
    private const FILES = ['word' => 'word_path', 'pdf' => 'pdf_path', 'signed' => 'signed_path'];

    public function index(Request $request): Response
    {
        $user = $request->user();
        $filters = $request->validate(['status' => ['nullable', Rule::enum(ContractStatus::class)]]);

        $contracts = Contract::query()
            ->whereHas('ticket', fn (Builder $query) => $query->visibleTo($user))
            ->with(['ticket.client'])
            ->when(filled($filters['status'] ?? null), fn (Builder $query) => $query->where('status', $filters['status']))
            ->orderByDesc('created_at')->orderByDesc('id')->paginate(25)->withQueryString()
            ->through(fn (Contract $contract): array => [
                'id' => $contract->id,
                'number' => $contract->number,
                'ticket_code' => $contract->ticket->code,
                'product_name' => $contract->ticket->product_name,
                'client' => $contract->ticket->client->name,
                'service_value' => $contract->service_value,
                'dp_percent' => $contract->dp_percent,
                'status' => $contract->status->value,
                'status_label' => $contract->status->label(),
                'show_url' => route('tickets.show', $contract->ticket_id, absolute: false),
            ]);

        return Inertia::render('Backend/Contracts', [
            'contracts' => $contracts,
            'filters' => ['status' => $filters['status'] ?? ''],
            'statuses' => array_map(fn (ContractStatus $status): array => ['value' => $status->value, 'label' => $status->label()], ContractStatus::cases()),
            'createOptions' => [
                'tickets' => Ticket::query()->visibleTo($user)
                    ->where('status', TicketStatus::Active)
                    ->whereDoesntHave('contract')
                    ->whereHas('sampleApprovals', fn (Builder $query) => $query->where('decision', SampleDecision::Approved))
                    ->with('client')->orderBy('code')->get()
                    ->map(fn (Ticket $ticket): array => ['value' => $ticket->id, 'label' => "{$ticket->code} · {$ticket->product_name} · {$ticket->client->name}"]),
                'templates' => $this->templateOptions(),
                'defaults' => ['dp_percent' => Contract::DEFAULT_DP_PERCENT, 'payment_due_days' => Contract::DEFAULT_PAYMENT_DUE_DAYS],
            ],
            'urls' => ['store' => route('contracts.store', absolute: false)],
        ]);
    }

    public function store(SaveContractRequest $request, SaveContract $save): RedirectResponse
    {
        $ticket = Ticket::query()->findOrFail($request->validated('ticket_id'));
        $contract = $save->create($ticket, $request->safe()->except('ticket_id'), $request->user());

        return redirect()->route('tickets.show', $ticket)->with('status', "Kontrak {$contract->number} dibuat. Berkasnya sedang disiapkan.");
    }

    public function update(SaveContractRequest $request, Contract $contract, SaveContract $save): RedirectResponse
    {
        $save->update($contract, $request->safe()->except('ticket_id'), $request->user());

        return back()->with('status', "Kontrak {$contract->number} diperbarui. Berkasnya dibuat ulang.");
    }

    /**
     * Tandai kontrak draf yang berkasnya sudah siap sebagai dikirim ke klien (FR-74).
     */
    public function send(Request $request, Contract $contract, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeManage($request, $contract);
        if ($contract->status !== ContractStatus::Draft) {
            return back()->withErrors(['contract' => 'Kontrak sudah dikirim.']);
        }

        if ($contract->generated_at === null || $contract->word_path === null) {
            return back()->withErrors(['contract' => 'Berkas kontrak belum siap. Muat ulang halaman sebentar lagi.']);
        }

        $contract->update(['status' => ContractStatus::Sent, 'sent_at' => now()]);
        $audit->record('contract.sent', $contract, ['status' => ContractStatus::Draft->value], ['status' => ContractStatus::Sent->value], ticketCode: $contract->ticket->code);

        return back()->with('status', "Kontrak {$contract->number} ditandai dikirim. Bagikan tautan unggah agar klien mengunggah kontrak bertanda tangan.");
    }

    public function signed(UploadSignedContractRequest $request, Contract $contract, StoreSignedContract $store): RedirectResponse
    {
        $store->handle($contract, $request->file('file'), $request->user());

        return back()->with('status', 'Kontrak bertanda tangan tersimpan dan menunggu konfirmasi.');
    }

    public function confirm(Request $request, Contract $contract, ConfirmContract $confirm): RedirectResponse
    {
        $this->authorizeManage($request, $contract);
        $confirm->handle($contract, $request->user());

        return back()->with('status', "Kontrak {$contract->number} aktif. Fase pra-registrasi dibuka.");
    }

    /**
     * Berkas kontrak hanya untuk staf yang dapat melihat tiketnya.
     */
    public function download(Request $request, Contract $contract, string $file): StreamedResponse
    {
        abort_unless($contract->ticket->isVisibleTo($request->user()), 403);
        $path = $contract->{self::FILES[$file]};
        abort_if($path === null, 404);

        $name = $file === 'signed'
            ? $contract->signed_original_name
            : 'Kontrak '.str_replace('/', '-', $contract->number).'.'.pathinfo($path, PATHINFO_EXTENSION);

        return Storage::disk('local')->download($path, $name);
    }

    /** @return list<array{value: int, label: string}> */
    public static function templateOptions(): array
    {
        return DocumentTemplate::query()->where('kind', TemplateKind::Contract)->where('is_active', true)->whereHas('versions')
            ->orderBy('name')->get()
            ->map(fn (DocumentTemplate $template): array => ['value' => $template->id, 'label' => $template->name])->all();
    }

    private function authorizeManage(Request $request, Contract $contract): void
    {
        abort_unless($request->user()->can('contracts.manage') && $contract->ticket->isVisibleTo($request->user()), 403);
    }
}
