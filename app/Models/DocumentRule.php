<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\DocumentRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aturan dokumen kondisional per sediaan dan/atau kategori (FR-07). Sediaan atau
 * kategori yang kosong berarti berlaku untuk semua. effective_from disimpan sebagai
 * teks Y-m-d dan hanya berlaku untuk tiket yang dibuat sejak tanggal itu (BR-07).
 */
#[Fillable(['document_type_id', 'dosage_form_id', 'category_id', 'effective_from', 'notes', 'is_active'])]
class DocumentRule extends Model
{
    /** @use HasFactory<DocumentRuleFactory> */
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
            'document_type_id' => 'integer',
            'dosage_form_id' => 'integer',
            'category_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<DocumentType, $this> */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    /** @return BelongsTo<DosageForm, $this> */
    public function dosageForm(): BelongsTo
    {
        return $this->belongsTo(DosageForm::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Aturan aktif yang berlaku untuk produk dengan sediaan dan kategori tersebut
     * pada tanggal pembuatan tiket, dihitung dalam zona waktu aplikasi (WIB).
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function appliesTo(Builder $query, DosageForm $dosageForm, Category $category, CarbonInterface $createdAt): void
    {
        $query->where('is_active', true)
            ->where('effective_from', '<=', $createdAt->copy()->setTimezone(config('app.timezone'))->toDateString())
            ->where(fn (Builder $query) => $query->whereNull('dosage_form_id')->orWhere('dosage_form_id', $dosageForm->id))
            ->where(fn (Builder $query) => $query->whereNull('category_id')->orWhere('category_id', $category->id));
    }
}
