<?php

namespace App\Models;

use App\StagePhase;
use Database\Factories\UploadTokenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Token tautan unggah klien per tiket dan fase (FR-18). Hanya hash SHA-256 token yang
 * disimpan; token asli ditampilkan sekali saat dibuat untuk dikirim ke klien.
 */
#[Fillable(['ticket_id', 'phase', 'token_hash', 'expires_at', 'created_by', 'revoked_at', 'revoked_by', 'last_used_at'])]
class UploadToken extends Model
{
    /** @use HasFactory<UploadTokenFactory> */
    use HasFactory;

    /** Panjang token acak, melebihi batas minimal 32 karakter (NFR keamanan tautan klien). */
    public const TOKEN_LENGTH = 48;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ticket_id' => 'integer',
            'phase' => StagePhase::class,
            'expires_at' => 'datetime',
            'created_by' => 'integer',
            'revoked_at' => 'datetime',
            'revoked_by' => 'integer',
            'last_used_at' => 'datetime',
        ];
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @param  Builder<self>  $query */
    #[Scope]
    protected function usable(Builder $query): void
    {
        $query->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    public function isUsable(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }
}
