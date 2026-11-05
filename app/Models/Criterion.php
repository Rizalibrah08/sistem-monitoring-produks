<?php

namespace App\Models;

use App\CriterionType;
use App\StaffRole;
use Database\Factories\CriterionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Kriteria prioritas AHP-TOPSIS per antrean role (FR-08). Kode C1 sampai C4 tetap
 * karena dipakai perhitungan antrean; Admin hanya mengubah bobot dan jenisnya.
 */
#[Fillable(['queue', 'code', 'name', 'weight', 'type'])]
class Criterion extends Model
{
    /** @use HasFactory<CriterionFactory> */
    use HasFactory;

    /** @var list<string> */
    public const QUEUES = ['ra', 'marketing', 'rnd', 'qa'];

    /** @var list<string> */
    public const CODES = ['C1', 'C2', 'C3', 'C4'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weight' => 'float',
            'type' => CriterionType::class,
        ];
    }

    public static function queueLabel(string $queue): string
    {
        return StaffRole::from($queue)->label();
    }
}
