<?php

namespace App\Models;

use App\DocumentRequirement;
use App\DocumentStatus;
use App\StaffRole;
use App\StagePhase;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu butir checklist dokumen pada tiket (FR-11). Fase dan sifat disalin dari jenis
 * dokumen saat checklist disusun, sehingga perubahan master tidak mengubah tiket lama (BR-07).
 */
#[Fillable(['ticket_id', 'document_type_id', 'material_id', 'phase', 'requirement', 'document_rule_id', 'document_id', 'status', 'revision_reason'])]
class TicketDocument extends Model
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
            'document_type_id' => 'integer',
            'material_id' => 'integer',
            'document_rule_id' => 'integer',
            'document_id' => 'integer',
            'phase' => StagePhase::class,
            'requirement' => DocumentRequirement::class,
            'status' => DocumentStatus::class,
        ];
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /** @return BelongsTo<DocumentType, $this> */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    /** @return BelongsTo<Material, $this> */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    /** @return BelongsTo<DocumentRule, $this> */
    public function documentRule(): BelongsTo
    {
        return $this->belongsTo(DocumentRule::class);
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Pengunggah adalah staf dengan role PIC jenis dokumen, Marketing atas nama klien,
     * atau Regulatory Affairs yang menghimpun berkas.
     */
    public function canBeUploadedBy(User $user): bool
    {
        $pic = $this->documentType->pic_role;
        $role = $user->getRoleNames()->first();

        return $role === $pic
            || $role === StaffRole::Regulatory->value
            || ($pic === DocumentType::CLIENT_PIC && $role === StaffRole::Marketing->value);
    }

    /** Butir ini dihitung pada fase tersebut, termasuk butir yang berlaku untuk kedua fase. */
    public function belongsToPhase(StagePhase $phase): bool
    {
        return $this->phase === $phase || $this->phase === StagePhase::BothPhases;
    }
}
