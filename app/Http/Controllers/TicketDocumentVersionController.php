<?php

namespace App\Http\Controllers;

use App\Actions\StoreDocumentVersion;
use App\DocumentSource;
use App\Http\Requests\UploadDocumentVersionRequest;
use App\Models\Ticket;
use App\Models\TicketDocument;
use Illuminate\Http\RedirectResponse;

class TicketDocumentVersionController extends Controller
{
    public function store(UploadDocumentVersionRequest $request, Ticket $ticket, TicketDocument $ticketDocument, StoreDocumentVersion $store): RedirectResponse
    {
        abort_unless($ticketDocument->ticket_id === $ticket->id, 404);

        $version = $store->handle($ticketDocument, $request->file('file'), $request->safe()->only(['number', 'issued_at', 'expires_at']), $request->user(), DocumentSource::Internal);

        return back()->with('status', $version->validation_passed
            ? "Versi {$version->version} diunggah dan lolos validasi otomatis."
            : "Versi {$version->version} diunggah, tetapi perlu revisi: ".implode(' ', $version->validation_errors));
    }
}
