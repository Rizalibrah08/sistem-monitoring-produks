<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Models\Ticket;
use App\Models\TicketDocument;
use App\TicketStatus;
use Inertia\Inertia;
use Inertia\Response;

class IngredientController extends Controller
{
    /**
     * Bahan baku setiap tiket aktif beserta status dokumen asal peredarannya, untuk R&D (FR-14).
     */
    public function __invoke(): Response
    {
        $rows = Ticket::query()->where('status', TicketStatus::Active)->whereHas('materials')
            ->with(['materials', 'documents' => fn ($query) => $query->whereNotNull('material_id')->with(['documentType', 'document.currentVersion'])])
            ->orderBy('code')->get()
            ->flatMap(fn (Ticket $ticket) => $ticket->materials->sortBy('name')->map(fn (Material $material): array => [
                'key' => "{$ticket->id}-{$material->id}",
                'ticket_code' => $ticket->code,
                'product_name' => $ticket->product_name,
                'ticket_url' => route('tickets.show', $ticket, absolute: false),
                'material' => $material->name,
                'supplier' => $material->supplier,
                'documents' => $ticket->documents->where('material_id', $material->id)->values()->map(fn (TicketDocument $item): array => [
                    'name' => $item->documentType->name,
                    'status' => $item->status->value,
                    'status_label' => $item->status->label(),
                    'expires_at' => $item->document?->currentVersion?->expires_at?->format('d/m/Y'),
                ]),
            ]))
            ->values();

        return Inertia::render('Backend/Ingredients', ['rows' => $rows]);
    }
}
