<?php

namespace App\Http\Controllers;

use App\Actions\CreateUploadToken;
use App\Http\Requests\CreateUploadTokenRequest;
use App\Models\Ticket;
use App\Models\UploadToken;
use App\Services\AuditLogger;
use App\StagePhase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UploadTokenController extends Controller
{
    public function store(CreateUploadTokenRequest $request, Ticket $ticket, CreateUploadToken $create): RedirectResponse
    {
        $phase = StagePhase::from($request->validated('phase'));
        $result = $create->handle($ticket, $phase, (int) $request->validated('days'), $request->user());

        return back()
            ->with('status', "Tautan unggah {$phase->label()} dibuat, berlaku sampai {$result['token']->expires_at->format('d/m/Y H:i')}.")
            ->with('upload_link', route('client-uploads.show', $result['plain']));
    }

    public function destroy(Request $request, Ticket $ticket, UploadToken $uploadToken, AuditLogger $audit): RedirectResponse
    {
        abort_unless($uploadToken->ticket_id === $ticket->id, 404);
        abort_unless($ticket->isVisibleTo($request->user()), 403);

        if ($uploadToken->revoked_at === null) {
            $uploadToken->update(['revoked_at' => now(), 'revoked_by' => $request->user()->id]);
            $audit->record('upload_link.revoked', $uploadToken, after: ['phase' => $uploadToken->phase->value], ticketCode: $ticket->code);
        }

        return back()->with('status', 'Tautan unggah dicabut.');
    }
}
