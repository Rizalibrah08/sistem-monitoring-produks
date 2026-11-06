<?php

namespace App\Models;

use App\Services\WorkingDays;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Waktu mulai, tenggat, dan selesai setiap tahap yang dilalui tiket (FR-34). */
#[Fillable(['ticket_id', 'stage_id', 'started_at', 'due_at', 'finished_at', 'overdue_at', 'delay_cause'])]
class TicketStageLog extends Model
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
            'stage_id' => 'integer',
            'started_at' => 'datetime',
            'due_at' => 'datetime',
            'finished_at' => 'datetime',
            'overdue_at' => 'datetime',
        ];
    }

    /**
     * Tenggat diisi otomatis dari SLA hari kerja tahapnya (FR-34, FR-35). Tahap yang menunggu
     * BPOM atau pihak luar tidak memiliki tenggat sehingga tidak dihitung (BR-08).
     */
    protected static function booted(): void
    {
        static::creating(function (self $log): void {
            if ($log->due_at !== null) {
                return;
            }

            $stage = Stage::query()->find($log->stage_id);

            if ($stage !== null && $stage->sla_days !== null && ! $stage->waits_for_external) {
                $log->due_at = app(WorkingDays::class)->dueAt($log->started_at ?? now(), $stage->sla_days);
            }
        });
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /** @return BelongsTo<Stage, $this> */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }
}
