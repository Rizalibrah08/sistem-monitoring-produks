<?php

namespace App\Models;

use App\TemplateKind;
use Database\Factories\DocumentTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Templat Word berversi (FR-67, FR-68). Setiap unggahan menjadi versi baru dan
 * versi lama tidak dapat dihapus, sehingga dokumen hasil templat dapat ditelusuri.
 */
#[Fillable(['name', 'kind', 'is_active'])]
class DocumentTemplate extends Model
{
    /** @use HasFactory<DocumentTemplateFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
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
            'kind' => TemplateKind::class,
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<TemplateVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(TemplateVersion::class);
    }
}
