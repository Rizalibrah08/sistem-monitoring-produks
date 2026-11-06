<?php

namespace App\Http\Controllers;

use App\Models\TicketStageLog;
use App\Services\DelayCause;
use App\Services\DurationReport;
use App\TicketStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * Laporan durasi per tahap untuk periode yang dipilih; bawaannya 90 hari terakhir (FR-42).
     */
    public function durations(Request $request, DurationReport $report): Response
    {
        [$from, $until] = $this->period($request);

        return Inertia::render('Backend/Reports', [
            ...$report->build($from, $until),
            'filters' => ['from' => $from->toDateString(), 'until' => $until->toDateString()],
            'urls' => ['export' => route('reports.export', ['from' => $from->toDateString(), 'until' => $until->toDateString()], absolute: false)],
        ]);
    }

    public function export(Request $request, DurationReport $report): StreamedResponse
    {
        [$from, $until] = $this->period($request);
        $data = $report->build($from, $until);

        return response()->streamDownload(function () use ($data): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Tahap', 'SLA (hari kerja)', 'Jumlah', 'Rata-rata (hari kerja)', 'Persentil ke-75', 'Internal', 'Menunggu klien', 'Menunggu pihak luar'], escape: '');

            foreach ($data['stages'] as $row) {
                fputcsv($output, [$row['stage'], $row['sla_days'] ?? '', $row['count'], $row['average'], $row['p75'], $row['internal'], $row['client'], $row['external']], escape: '');
            }

            fclose($output);
        }, "laporan-durasi-{$from->format('Ymd')}-{$until->format('Ymd')}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Tahap tiket aktif yang melewati tenggat karena pihak internal (FR-37), untuk Manajer.
     */
    public function escalations(): Response
    {
        $logs = TicketStageLog::query()
            ->whereNull('finished_at')->whereNotNull('overdue_at')
            ->whereHas('ticket', fn ($query) => $query->where('status', TicketStatus::Active))
            ->with(['ticket.client', 'stage'])
            ->orderBy('due_at')->get()
            ->map(fn (TicketStageLog $log): array => [
                'id' => $log->id,
                'ticket_code' => $log->ticket->code,
                'product_name' => $log->ticket->product_name,
                'client' => $log->ticket->client->name,
                'stage' => $log->stage->name,
                'due_at' => $log->due_at?->format('d/m/Y H:i'),
                'overdue_at' => $log->overdue_at->format('d/m/Y H:i'),
                'by_client' => $log->delay_cause === DelayCause::CLIENT,
                'ticket_url' => route('tickets.show', $log->ticket_id, absolute: false),
            ]);

        return Inertia::render('Backend/Escalations', ['escalations' => $logs]);
    }

    /** @return array{Carbon, Carbon} */
    private function period(Request $request): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ], ['until.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal awal.']);

        return [
            Carbon::parse($data['from'] ?? now()->subDays(90)->toDateString()),
            Carbon::parse($data['until'] ?? now()->toDateString()),
        ];
    }
}
