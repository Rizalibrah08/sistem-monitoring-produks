<?php

namespace App\Actions;

use App\Models\Ticket;
use App\Models\UploadToken;
use App\Models\User;
use App\Services\AuditLogger;
use App\StagePhase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateUploadToken
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * Buat tautan unggah klien untuk satu fase tiket (FR-18). Tautan aktif sebelumnya pada
     * fase yang sama dicabut agar klien hanya memegang satu tautan yang berlaku.
     *
     * @return array{token: UploadToken, plain: string}
     */
    public function handle(Ticket $ticket, StagePhase $phase, int $days, User $actor): array
    {
        return DB::transaction(function () use ($ticket, $phase, $days, $actor): array {
            $revoked = $ticket->uploadTokens()->where('phase', $phase)->usable()
                ->update(['revoked_at' => now(), 'revoked_by' => $actor->id]);

            $plain = Str::random(UploadToken::TOKEN_LENGTH);
            $token = $ticket->uploadTokens()->create([
                'phase' => $phase,
                'token_hash' => UploadToken::hash($plain),
                'expires_at' => now()->addDays($days),
                'created_by' => $actor->id,
            ]);

            $this->audit->record('upload_link.created', $token, after: [
                'phase' => $phase->value,
                'expires_at' => $token->expires_at->toIso8601String(),
                'replaced_links' => $revoked,
            ], actor: $actor, ticketCode: $ticket->code);

            return ['token' => $token, 'plain' => $plain];
        });
    }
}
