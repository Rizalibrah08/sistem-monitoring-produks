<?php

namespace App\Http\Controllers;

use App\Models\DocumentVersion;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentVersionDownloadController extends Controller
{
    /**
     * Berkas hanya dapat diunduh oleh staf yang dapat melihat salah satu tiket
     * yang memakai dokumen tersebut (NFR-07).
     */
    public function __invoke(Request $request, DocumentVersion $documentVersion): StreamedResponse
    {
        $canSeeTicket = Ticket::query()->visibleTo($request->user())
            ->whereHas('documents', fn (Builder $query) => $query->where('document_id', $documentVersion->document_id))
            ->exists();

        abort_unless($canSeeTicket, 403);

        return Storage::disk('local')->download($documentVersion->path, $documentVersion->original_name);
    }
}
