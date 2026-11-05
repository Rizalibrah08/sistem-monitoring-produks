<?php

namespace App\Actions;

use App\Models\Activity;
use App\Models\User;
use App\Services\AuditHasher;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Spatie\Activitylog\Actions\LogActivityAction;

class AppendAuditActivity extends LogActivityAction
{
    public function __construct(private AuditHasher $hasher) {}

    protected function save(Model $activity): void
    {
        if (! $activity instanceof Activity) {
            throw new LogicException('Model audit tidak sesuai konfigurasi.');
        }

        $connection = $activity->getConnection();

        $connection->transaction(function () use ($activity, $connection): void {
            $lock = $connection->table('audit_log_locks')->where('id', 1)->lockForUpdate()->first();

            if ($lock === null) {
                throw new LogicException('Kunci rantai audit belum disiapkan.');
            }

            $actor = $activity->causer;
            $activity->actor_name = $actor instanceof User ? $actor->name : null;
            $activity->actor_role = $actor instanceof User ? $actor->getRoleNames()->first() : null;
            $activity->source ??= 'system';
            $activity->attribute_changes = $this->hasher->sanitize($activity->attribute_changes?->all() ?? []);
            $activity->properties = $this->hasher->sanitize($activity->properties?->all() ?? []);
            $activity->created_at = now()->startOfSecond();
            $activity->updated_at = $activity->created_at;
            $activity->prev_hash = $connection->table('activity_log')->orderByDesc('id')->value('hash') ?? str_repeat('0', 64);
            $activity->hash = $this->hasher->hash($activity);
            $activity->save();
        }, attempts: 5);
    }
}
