<?php

namespace App\Models;

use App\StaffRole;
use App\StagePhase;
use App\TicketStatus;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Satu tiket untuk satu produk yang didaftarkan ke BPOM (BR-01). */
#[Fillable(['code', 'client_id', 'product_name', 'dosage_form_id', 'category_id', 'phase', 'current_stage_id', 'status', 'created_by'])]
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'client_id' => 'integer',
            'dosage_form_id' => 'integer',
            'category_id' => 'integer',
            'current_stage_id' => 'integer',
            'created_by' => 'integer',
            'phase' => StagePhase::class,
            'status' => TicketStatus::class,
        ];
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
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

    /** @return BelongsTo<Stage, $this> */
    public function currentStage(): BelongsTo
    {
        return $this->belongsTo(Stage::class, 'current_stage_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<TicketStageLog, $this> */
    public function stageLogs(): HasMany
    {
        return $this->hasMany(TicketStageLog::class);
    }

    /** @return HasMany<TicketDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(TicketDocument::class);
    }

    /** @return HasMany<TicketReview, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(TicketReview::class);
    }

    /** @return HasMany<SampleApproval, $this> */
    public function sampleApprovals(): HasMany
    {
        return $this->hasMany(SampleApproval::class);
    }

    /** @return HasMany<UploadToken, $this> */
    public function uploadTokens(): HasMany
    {
        return $this->hasMany(UploadToken::class);
    }

    /**
     * Bahan baku produk yang dipilih R&D dari master (FR-14).
     *
     * @return BelongsToMany<Material, $this>
     */
    public function materials(): BelongsToMany
    {
        return $this->belongsToMany(Material::class, 'ticket_materials')->withTimestamps();
    }

    /**
     * Marketing hanya melihat tiket klien yang ditanganinya; role lain melihat semua tiket.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if ($user->hasRole(StaffRole::Marketing->value)) {
            $query->whereHas('client', fn (Builder $query) => $query->where('marketing_user_id', $user->id));
        }
    }

    public function isVisibleTo(User $user): bool
    {
        return ! $user->hasRole(StaffRole::Marketing->value) || $this->client->marketing_user_id === $user->id;
    }
}
