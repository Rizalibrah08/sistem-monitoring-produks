<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TicketReviewController extends Controller
{
    /**
     * RA menyatakan tiket sudah ditinjau terhadap perubahan dokumen bersama (FR-20).
     */
    public function resolve(Request $request, Ticket $ticket, AuditLogger $audit): RedirectResponse
    {
        $resolved = $ticket->reviews()->open()->update(['resolved_at' => now(), 'resolved_by' => $request->user()->id]);

        if ($resolved > 0) {
            $audit->record('ticket.review_resolved', $ticket, after: ['resolved_reviews' => $resolved], ticketCode: $ticket->code);
        }

        return back()->with('status', 'Tiket ditandai sudah ditinjau.');
    }
}
