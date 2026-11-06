<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu pengiriman sampel ke laboratorium uji eksternal beserta lembar hasil uji (LHU)
 * (FR-87). Hasil yang tidak sesuai spesifikasi diikuti analisis ulang sebagai percobaan baru.
 */
#[Fillable(['ticket_id', 'attempt', 'laboratory', 'sent_on', 'estimated_on', 'result_on', 'conforms', 'note', 'result_path', 'result_original_name', 'recorded_by'])]
class LabTest extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ticket_id' => 'integer',
            'attempt' => 'integer',
            'sent_on' => 'date',
            'estimated_on' => 'date',
            'result_on' => 'date',
            'conforms' => 'boolean',
            'recorded_by' => 'integer',
        ];
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
