<?php

namespace App\Services;

use App\ContractStatus;
use App\DocumentStatus;
use App\Models\DocumentType;
use App\Models\TicketDocument;
use App\Models\TicketStageLog;
use App\StagePhase;

class DelayCause
{
    public const CLIENT = 'klien';

    public const STAFF = 'staf';

    /** @var array<string, StagePhase|null> tahap dokumen dan fase checklist yang dinilainya */
    private const DOCUMENT_STAGES = [
        'dokumen_pra_registrasi' => StagePhase::PreRegistration,
        'dokumen_registrasi' => StagePhase::Registration,
        'perbaikan_dokumen' => null,
    ];

    /**
     * Penyebab keterlambatan tahap (FR-37, BR-11): klien bila yang tertunda hanya bagian
     * klien, yaitu kontrak yang belum ditandatangani atau dokumen ber-PIC klien; selain itu staf.
     */
    public function of(TicketStageLog $log): string
    {
        $ticket = $log->ticket;
        $code = $log->stage->code;

        if ($code === 'kontrak') {
            return $ticket->contract?->status === ContractStatus::Sent ? self::CLIENT : self::STAFF;
        }

        if (! array_key_exists($code, self::DOCUMENT_STAGES)) {
            return self::STAFF;
        }

        $phase = self::DOCUMENT_STAGES[$code];
        $pending = $ticket->documents()->with('documentType')->get()->filter(fn (TicketDocument $item): bool => $phase === null
            ? $item->status === DocumentStatus::NeedsRevision
            : $item->belongsToPhase($phase) && in_array($item->status, [DocumentStatus::Missing, DocumentStatus::NeedsRevision], true));

        return $pending->isNotEmpty() && $pending->every(fn (TicketDocument $item): bool => $item->documentType->pic_role === DocumentType::CLIENT_PIC)
            ? self::CLIENT
            : self::STAFF;
    }
}
