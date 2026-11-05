<?php

namespace App\Models;

use App\DocumentScope;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Dokumen beserta riwayat versinya. Pemiliknya mengikuti cakupan jenis dokumen (BR-03):
 * tiket, klien, bahan baku, atau perusahaan (tanpa pemilik), sehingga dokumen bersama
 * dapat dipakai ulang oleh tiket lain.
 */
#[Fillable(['document_type_id', 'scope', 'ticket_id', 'client_id', 'material_id', 'current_version_id'])]
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_type_id' => 'integer',
            'scope' => DocumentScope::class,
            'ticket_id' => 'integer',
            'client_id' => 'integer',
            'material_id' => 'integer',
            'current_version_id' => 'integer',
        ];
    }

    /** @return BelongsTo<DocumentType, $this> */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    /** @return HasMany<DocumentVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class);
    }

    /** @return BelongsTo<DocumentVersion, $this> */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'current_version_id');
    }

    /** @return HasMany<TicketDocument, $this> */
    public function ticketDocuments(): HasMany
    {
        return $this->hasMany(TicketDocument::class);
    }
}
