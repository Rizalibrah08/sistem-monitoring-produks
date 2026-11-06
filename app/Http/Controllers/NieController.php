<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Services\RegistrationCompletion;
use App\StagePhase;
use App\SubmissionStatus;
use App\TicketStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NieController extends Controller
{
    /**
     * Tiket yang registrasinya sudah diajukan dan menunggu NIE, beserta status desain kemasan.
     */
    public function index(): Response
    {
        $tickets = Ticket::query()
            ->where('status', TicketStatus::Active)
            ->whereHas('bpomSubmissions', fn (Builder $query) => $query->where('phase', StagePhase::Registration)->where('status', SubmissionStatus::AwaitingResult))
            ->with(['client', 'bpomSubmissions' => fn ($query) => $query->where('phase', StagePhase::Registration)->latest('id')])
            ->orderBy('code')->get()
            ->map(fn (Ticket $ticket): array => [
                'id' => $ticket->id,
                'code' => $ticket->code,
                'product_name' => $ticket->product_name,
                'client' => $ticket->client->name,
                'submitted_on' => $ticket->bpomSubmissions->first()?->submitted_on->format('d/m/Y'),
                'packaging_requested_on' => $ticket->packaging_requested_on?->format('d/m/Y'),
                'packaging_uploaded_on' => $ticket->packaging_uploaded_on?->format('d/m/Y'),
                'ticket_url' => route('tickets.show', $ticket, absolute: false),
            ]);

        return Inertia::render('Backend/Nie', ['tickets' => $tickets]);
    }

    public function packaging(Request $request, Ticket $ticket, RegistrationCompletion $completion): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:requested,uploaded'],
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ], ['date.required' => 'Tanggal wajib diisi.', 'date.before_or_equal' => 'Tanggal tidak boleh setelah hari ini.']);

        if ($data['action'] === 'requested') {
            $completion->requestPackaging($ticket, $data['date'], $request->user());

            return back()->with('status', 'Permintaan desain kemasan dicatat.');
        }

        $completion->packagingUploaded($ticket, $data['date'], $request->user());

        return back()->with('status', 'Desain kemasan tercatat sudah diunggah; menunggu NIE.');
    }

    public function store(Request $request, Ticket $ticket, RegistrationCompletion $completion): RedirectResponse
    {
        $data = $request->validate([
            'nie_number' => ['required', 'string', 'max:50', 'unique:tickets,nie_number'],
            'nie_issued_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ], [
            'nie_number.required' => 'Nomor NIE wajib diisi.',
            'nie_number.unique' => 'Nomor NIE ini sudah tercatat pada tiket lain.',
            'nie_issued_on.required' => 'Tanggal terbit NIE wajib diisi.',
            'nie_issued_on.before_or_equal' => 'Tanggal terbit tidak boleh setelah hari ini.',
        ]);

        $completion->recordNie($ticket, $data['nie_number'], $data['nie_issued_on'], $request->user());

        return back()->with('status', "NIE {$data['nie_number']} tercatat; tiket selesai dan tagihan pelunasan diterbitkan.");
    }
}
