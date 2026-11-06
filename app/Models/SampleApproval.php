<?php

namespace App\Models;

use App\SampleDecision;
use Database\Factories\SampleApprovalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu keputusan klien atas sampel produk (FR-13). Nomor revisi bertambah per tiket
 * dan catatan lama tidak pernah diubah, sehingga riwayat keputusan dapat ditelusuri.
 */
#[Fillable(['ticket_id', 'revision', 'decision', 'note', 'recorded_by'])]
class SampleApproval extends Model
{
    /** @use HasFactory<SampleApprovalFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ticket_id' => 'integer',
            'revision' => 'integer',
            'decision' => SampleDecision::class,
            'recorded_by' => 'integer',
        ];
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
