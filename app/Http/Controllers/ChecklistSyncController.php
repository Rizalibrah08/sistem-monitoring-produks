<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketDocument;
use App\Services\AuditLogger;
use App\Services\ChecklistBuilder;
use App\TicketStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ChecklistSyncController extends Controller
{
    /**
     * Tiket aktif yang checklist-nya berbeda dengan jenis dokumen dan aturan yang berlaku
     * hari ini, untuk ditinjau dan disinkronkan RA (FR-21).
     */
    public function index(ChecklistBuilder $checklist): Response
    {
        $rows = Ticket::query()->where('status', TicketStatus::Active)->with('client')->orderBy('code')->get()
            ->map(fn (Ticket $ticket): ?array => $this->row($ticket, $checklist))
            ->filter()->values();

        return Inertia::render('Backend/ChecklistSync', ['tickets' => $rows]);
    }

    public function sync(Request $request, Ticket $ticket, ChecklistBuilder $checklist, AuditLogger $audit): RedirectResponse
    {
        abort_unless($ticket->status === TicketStatus::Active, 404);

        $result = DB::transaction(fn (): array => $checklist->sync($ticket, now()));

        if ($result['added'] !== [] || $result['removed'] !== []) {
            $audit->record('checklist.synced', $ticket, after: $result, ticketCode: $ticket->code);
        }

        return back()->with('status', 'Checklist disinkronkan: '.count($result['added']).' butir ditambahkan, '.count($result['removed']).' butir dilepas.');
    }

    /** @return array<string, mixed>|null */
    public static function row(Ticket $ticket, ChecklistBuilder $checklist): ?array
    {
        ['missing' => $missing, 'obsolete' => $obsolete] = $checklist->differences($ticket, now());

        if ($missing->isEmpty() && $obsolete->isEmpty()) {
            return null;
        }

        return [
            'id' => $ticket->id,
            'code' => $ticket->code,
            'product_name' => $ticket->product_name,
            'client' => $ticket->client->name,
            'add' => $missing->map(fn (array $entry): string => ChecklistBuilder::label($entry['type'], $entry['material']))->values(),
            'remove' => $obsolete->map(fn (TicketDocument $item): string => ChecklistBuilder::label($item->documentType, $item->material))->values(),
            'ticket_url' => route('tickets.show', $ticket, absolute: false),
            'sync_url' => route('checklist_sync.sync', $ticket, absolute: false),
        ];
    }
}
