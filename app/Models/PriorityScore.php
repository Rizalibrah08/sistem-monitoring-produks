<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Nilai kriteria C1–C4, skor TOPSIS, dan peringkat tiket pada satu antrean role (FR-39). */
#[Fillable(['queue', 'ticket_id', 'c1', 'c2', 'c3', 'c4', 'score', 'rank', 'computed_at'])]
class PriorityScore extends Model
{
    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ticket_id' => 'integer',
            'c1' => 'float',
            'c2' => 'float',
            'c3' => 'float',
            'c4' => 'float',
            'score' => 'float',
            'rank' => 'integer',
            'computed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
