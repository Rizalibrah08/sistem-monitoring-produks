<?php

namespace App\Models;

use App\StagePhase;
use Database\Factories\StageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Tahapan pendaftaran beserta SLA dalam hari kerja (FR-05, BR-08).
 * Kolom code tetap dan dipakai logika tiket, sehingga nama tahap boleh diubah Admin.
 */
#[Fillable(['code', 'name', 'phase', 'sort_order', 'sla_days', 'waits_for_external', 'is_active'])]
class Stage extends Model
{
    /** @use HasFactory<StageFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'waits_for_external' => false,
        'is_active' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'phase' => StagePhase::class,
            'sort_order' => 'integer',
            'sla_days' => 'integer',
            'waits_for_external' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
