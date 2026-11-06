<?php

namespace App\Models;

use App\DocumentRequirement;
use App\DocumentScope;
use App\StaffRole;
use App\StagePhase;
use Database\Factories\DocumentTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Jenis dokumen pada checklist pendaftaran (FR-06). pic_role berisi role staf
 * atau "client" untuk dokumen yang diunggah klien melalui tautan bertoken (FR-18).
 */
#[Fillable(['name', 'phase', 'scope', 'requirement', 'pic_role', 'verifier_role', 'requires_expiry_date', 'max_age_months', 'checks_product_name', 'is_active'])]
class DocumentType extends Model
{
    /** @use HasFactory<DocumentTypeFactory> */
    use HasFactory;

    public const CLIENT_PIC = 'client';

    /** @var list<string> */
    public const PIC_ROLES = ['marketing', 'rnd', 'qa', 'ra', self::CLIENT_PIC];

    /** @var list<string> */
    public const VERIFIER_ROLES = ['qa', 'ra'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'requires_expiry_date' => false,
        'checks_product_name' => false,
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
            'scope' => DocumentScope::class,
            'requirement' => DocumentRequirement::class,
            'requires_expiry_date' => 'boolean',
            'max_age_months' => 'integer',
            'checks_product_name' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public static function roleLabel(?string $role): ?string
    {
        return $role === self::CLIENT_PIC ? 'Klien' : StaffRole::tryFrom($role ?? '')?->label();
    }
}
