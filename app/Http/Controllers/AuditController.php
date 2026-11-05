<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AuditController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): Response
    {
        $filters = $request->validate([
            'actor_id' => ['nullable', 'integer', Rule::exists(User::class, 'id')],
            'event' => ['nullable', 'string', 'max:100'],
            'ticket_code' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('date_from'), 'after_or_equal:date_from')],
        ]);

        $entries = Activity::query()
            ->when(filled($filters['actor_id'] ?? null), fn (Builder $query) => $query->where('causer_id', $filters['actor_id'])->where('causer_type', (new User)->getMorphClass()))
            ->when(filled($filters['event'] ?? null), fn (Builder $query) => $query->where('event', $filters['event']))
            ->when(filled($filters['ticket_code'] ?? null), fn (Builder $query) => $query->where('ticket_code', $filters['ticket_code']))
            ->when(filled($filters['date_from'] ?? null), fn (Builder $query) => $query->where('created_at', '>=', $filters['date_from'].' 00:00:00'))
            ->when(filled($filters['date_to'] ?? null), fn (Builder $query) => $query->where('created_at', '<=', $filters['date_to'].' 23:59:59'))
            ->orderByDesc('created_at')->orderByDesc('id')->paginate(50)->withQueryString()
            ->through(fn (Activity $entry): array => [
                'id' => $entry->id,
                'created_at' => $entry->created_at->format('d/m/Y H:i:s'),
                'actor_name' => $entry->actor_name,
                'actor_role' => $entry->actor_role,
                'event' => $entry->event,
                'description' => $entry->description,
                'subject_type' => $entry->subject_type,
                'subject_id' => $entry->subject_id,
                'ticket_code' => $entry->ticket_code,
                'source' => $entry->source,
                'before' => $entry->attribute_changes?->get('old', []) ?? [],
                'after' => $entry->attribute_changes?->get('attributes', []) ?? [],
                'reason' => $entry->reason,
                'ip_address' => $entry->ip_address,
                'user_agent' => $entry->user_agent,
                'prev_hash' => $entry->prev_hash,
                'hash' => $entry->hash,
            ]);

        return Inertia::render('Backend/Audit', [
            'entries' => $entries,
            'actors' => User::query()->orderBy('name')->orderBy('id')->get(['id', 'name']),
            'filters' => array_replace(['actor_id' => '', 'event' => '', 'ticket_code' => '', 'date_from' => '', 'date_to' => ''], $filters),
        ]);
    }
}
