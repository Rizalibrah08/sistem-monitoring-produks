<?php

namespace App\Services;

use App\Models\Activity;

class AuditHasher
{
    public function hash(Activity $activity): string
    {
        $payload = [
            'log_name' => $activity->log_name,
            'description' => $activity->description,
            'event' => $activity->event,
            'subject_type' => $activity->subject_type,
            'subject_id' => $activity->subject_id === null ? null : (string) $activity->subject_id,
            'causer_type' => $activity->causer_type,
            'causer_id' => $activity->causer_id === null ? null : (string) $activity->causer_id,
            'attribute_changes' => $activity->attribute_changes?->all() ?? [],
            'properties' => $activity->properties?->all() ?? [],
            'actor_name' => $activity->actor_name,
            'actor_role' => $activity->actor_role,
            'source' => $activity->source,
            'ticket_code' => $activity->ticket_code,
            'reason' => $activity->reason,
            'ip_address' => $activity->ip_address,
            'user_agent' => $activity->user_agent,
            'created_at' => $activity->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $activity->updated_at?->format('Y-m-d H:i:s'),
        ];

        return hash('sha256', $activity->prev_hash.json_encode($this->canonicalize($payload), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** @param array<array-key, mixed> $values
     * @return array<array-key, mixed>
     */
    public function sanitize(array $values): array
    {
        $safe = [];

        foreach ($values as $key => $value) {
            if (is_string($key) && preg_match('/password|token|secret|authorization|cookie/i', $key) === 1) {
                continue;
            }

            $safe[$key] = is_array($value) ? $this->sanitize($value) : $value;
        }

        return $safe;
    }

    /** @param array<array-key, mixed> $values
     * @return array<array-key, mixed>
     */
    private function canonicalize(array $values): array
    {
        if (! array_is_list($values)) {
            ksort($values);
        }

        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $values[$key] = $this->canonicalize($value);
            }
        }

        return $values;
    }
}
