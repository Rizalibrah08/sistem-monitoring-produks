<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\User;
use App\Services\AuditHasher;
use App\Services\AuditLogger;
use App\Services\DocumentRenderer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class AuditController extends Controller
{
    /** Batas entri per ekspor agar berkas tetap dapat dibuka. */
    private const EXPORT_LIMIT = 5000;

    /** Batas entri tidak cocok yang ditampilkan dari verifikasi integritas. */
    private const MISMATCH_LIMIT = 50;

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): Response
    {
        $filters = $this->filters($request);

        $entries = $this->query($filters)
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
            'urls' => [
                'csv' => route('audit.export', ['format' => 'csv', ...$filters], absolute: false),
                'pdf' => route('audit.export', ['format' => 'pdf', ...$filters], absolute: false),
                'verify' => $request->user()->can('users.manage') ? route('audit.verify', absolute: false) : null,
            ],
            'integrity' => $request->session()->get('audit_integrity'),
        ]);
    }

    /**
     * Ekspor log sesuai filter ke CSV atau PDF; setiap ekspor ikut tercatat (FR-64).
     */
    public function export(Request $request, string $format, AuditLogger $audit, DocumentRenderer $renderer): HttpResponse
    {
        $filters = $this->filters($request);
        $entries = $this->query($filters)->orderBy('created_at')->orderBy('id')->limit(self::EXPORT_LIMIT)->get();
        $name = 'log-audit-'.now()->format('Ymd-His');

        if ($format === 'pdf') {
            $html = view('exports.audit', ['entries' => $entries, 'filters' => $filters, 'printedAt' => now(), 'printedBy' => $request->user()->name])->render();

            try {
                $pdf = $renderer->htmlToPdf($html);
            } catch (RequestException $exception) {
                report($exception);

                return back()->withErrors(['export' => 'PDF gagal dibuat. Coba lagi atau gunakan ekspor CSV.']);
            }

            $audit->record('audit.exported', after: ['format' => 'pdf', 'entries' => $entries->count()], properties: ['filters' => $filters]);

            return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => "attachment; filename=\"{$name}.pdf\""]);
        }

        $audit->record('audit.exported', after: ['format' => 'csv', 'entries' => $entries->count()], properties: ['filters' => $filters]);

        return response()->streamDownload(function () use ($entries): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Waktu (WIB)', 'Pelaku', 'Role', 'Aksi', 'Kode tiket', 'Sumber', 'Sebelum', 'Sesudah', 'Alasan', 'IP', 'Hash sebelumnya', 'Hash'], escape: '');

            foreach ($entries as $entry) {
                fputcsv($output, [
                    $entry->created_at->format('d/m/Y H:i:s'),
                    $entry->actor_name ?? 'Sistem',
                    $entry->actor_role,
                    $entry->event,
                    $entry->ticket_code,
                    $entry->source,
                    json_encode($entry->attribute_changes?->get('old', []) ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    json_encode($entry->attribute_changes?->get('attributes', []) ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    $entry->reason,
                    $entry->ip_address,
                    $entry->prev_hash,
                    $entry->hash,
                ], escape: '');
            }

            fclose($output);
        }, "{$name}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Admin memverifikasi rantai hash seluruh log: hash setiap entri dihitung ulang dan
     * kesinambungan dengan hash entri sebelumnya diperiksa (FR-65).
     */
    public function verify(Request $request, AuditHasher $hasher, AuditLogger $audit): RedirectResponse
    {
        $previous = str_repeat('0', 64);
        $checked = 0;
        $mismatches = [];

        Activity::query()->orderBy('id')->chunk(500, function ($entries) use ($hasher, &$previous, &$checked, &$mismatches): void {
            foreach ($entries as $entry) {
                $checked++;
                $reasons = [];

                if ($entry->prev_hash !== $previous) {
                    $reasons[] = 'tidak tersambung ke entri sebelumnya';
                }

                if ($hasher->hash($entry) !== $entry->hash) {
                    $reasons[] = 'isi tidak sesuai hash';
                }

                if ($reasons !== [] && count($mismatches) < self::MISMATCH_LIMIT) {
                    $mismatches[] = ['id' => $entry->id, 'event' => $entry->event, 'created_at' => $entry->created_at?->format('d/m/Y H:i:s'), 'reason' => implode('; ', $reasons)];
                }

                $previous = (string) $entry->hash;
            }
        });

        $audit->record('audit.verified', after: ['checked' => $checked, 'mismatches' => count($mismatches)]);

        return redirect()->route('audit.index')->with('audit_integrity', [
            'checked' => $checked,
            'valid' => $mismatches === [],
            'mismatches' => $mismatches,
            'verified_at' => now()->format('d/m/Y H:i:s'),
        ]);
    }

    /** @return array{actor_id?: ?int, event?: ?string, ticket_code?: ?string, date_from?: ?string, date_to?: ?string} */
    private function filters(Request $request): array
    {
        return $request->validate([
            'actor_id' => ['nullable', 'integer', Rule::exists(User::class, 'id')],
            'event' => ['nullable', 'string', 'max:100'],
            'ticket_code' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('date_from'), 'after_or_equal:date_from')],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Activity>
     */
    private function query(array $filters): Builder
    {
        return Activity::query()
            ->when(filled($filters['actor_id'] ?? null), fn (Builder $query) => $query->where('causer_id', $filters['actor_id'])->where('causer_type', (new User)->getMorphClass()))
            ->when(filled($filters['event'] ?? null), fn (Builder $query) => $query->where('event', $filters['event']))
            ->when(filled($filters['ticket_code'] ?? null), fn (Builder $query) => $query->where('ticket_code', $filters['ticket_code']))
            ->when(filled($filters['date_from'] ?? null), fn (Builder $query) => $query->where('created_at', '>=', $filters['date_from'].' 00:00:00'))
            ->when(filled($filters['date_to'] ?? null), fn (Builder $query) => $query->where('created_at', '<=', $filters['date_to'].' 23:59:59'));
    }
}
