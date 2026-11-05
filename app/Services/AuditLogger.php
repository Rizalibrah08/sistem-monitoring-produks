<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use LogicException;

class AuditLogger
{
    public function __construct(private Request $request) {}

    /** @param array<string, mixed> $before
     * @param  array<string, mixed>  $after
     */
    public function record(string $event, ?Model $subject = null, array $before = [], array $after = [], ?string $reason = null, string $source = 'web', ?User $actor = null, ?string $ticketCode = null): Activity
    {
        $actor ??= $this->request->user();
        $pending = activity(strtok($event, '.'))->event($event)
            ->withChanges(['old' => $before, 'attributes' => $after])
            ->tap(function (Activity $activity) use ($reason, $source, $ticketCode): void {
                $activity->source = $source;
                $activity->ticket_code = $ticketCode;
                $activity->reason = $reason;
                $activity->ip_address = $source === 'web' ? $this->request->ip() : null;
                $activity->user_agent = $source === 'web' ? mb_substr($this->request->userAgent() ?? '', 0, 2000) : null;
            });

        if ($actor !== null) {
            $pending->causedBy($actor);
        } else {
            $pending->causedByAnonymous();
        }

        if ($subject !== null) {
            $pending->performedOn($subject);
        }

        $entry = $pending->log($event);

        if (! $entry instanceof Activity) {
            throw new LogicException('Pencatatan audit wajib aktif.');
        }

        return $entry;
    }
}
