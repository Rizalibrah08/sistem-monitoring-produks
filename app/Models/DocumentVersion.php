<?php

namespace App\Models;

use App\DocumentSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu versi berkas dokumen (FR-15) beserta hasil validasi otomatisnya (FR-71).
 * Versi tidak pernah dihapus atau diubah setelah diunggah.
 */
#[Fillable(['document_id', 'version', 'path', 'original_name', 'mime_type', 'size', 'checksum', 'number', 'issued_at', 'expires_at', 'source', 'uploaded_by', 'validation_passed', 'validation_errors'])]
class DocumentVersion extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_id' => 'integer',
            'version' => 'integer',
            'size' => 'integer',
            'issued_at' => 'date',
            'expires_at' => 'date',
            'source' => DocumentSource::class,
            'uploaded_by' => 'integer',
            'validation_passed' => 'boolean',
            'validation_errors' => 'array',
        ];
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
