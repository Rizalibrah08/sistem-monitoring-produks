<?php

namespace App\Actions;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SaveMasterRecord
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * Simpan data master dan catat nilai sebelum dan sesudahnya di log audit (FR-59).
     * $group menjadi awalan event, misalnya "master.category.updated" atau "client.created".
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Model $record, string $entity, array $attributes, User $actor, string $group = 'master'): Model
    {
        return DB::transaction(function () use ($record, $entity, $attributes, $actor, $group): Model {
            $creating = ! $record->exists;
            $before = $creating ? [] : $record->only(array_keys($attributes));

            $record->fill($attributes)->save();

            $this->audit->record(
                implode('.', array_filter([$group, $entity, $creating ? 'created' : 'updated'])),
                $record,
                $before,
                $record->only(array_keys($attributes)),
                actor: $actor,
            );

            return $record;
        });
    }

    /** Hapus data master yang tidak dirujuk data lain, lalu catat di log audit. */
    public function delete(Model $record, string $entity, User $actor): void
    {
        DB::transaction(function () use ($record, $entity, $actor): void {
            $before = $record->only($record->getFillable());
            $record->delete();

            $this->audit->record("master.{$entity}.deleted", $record, $before, actor: $actor);
        });
    }
}
